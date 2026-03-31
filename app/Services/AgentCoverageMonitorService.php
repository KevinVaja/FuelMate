<?php

namespace App\Services;

use App\Models\Agent;
use Illuminate\Support\Collection;

class AgentCoverageMonitorService
{
    public const COVERAGE_RADIUS_METERS = 25000;

    private const STATUS_COLORS = [
        Agent::STATUS_ACTIVE => '#1d9b63',
        Agent::STATUS_BUSY => '#f08c00',
        Agent::STATUS_OFFLINE => '#d9485f',
    ];

    public function build(?string $statusFilter = null, ?int $pumpFilter = null): array
    {
        $normalizedStatus = $this->normalizeStatusFilter($statusFilter);

        $agents = Agent::query()
            ->with('user')
            ->withCount([
                'fuelRequests as active_deliveries_count' => fn ($query) => $query->whereIn('status', Agent::ACTIVE_DELIVERY_STATUSES),
            ])
            ->orderByDesc('approved_at')
            ->orderBy('id')
            ->get();

        $pumpOptions = $agents
            ->map(fn (Agent $agent) => [
                'id' => $agent->id,
                'name' => $this->pumpName($agent),
                'has_coordinates' => $agent->hasCoverageCenter(),
            ])
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        $directory = $agents
            ->map(fn (Agent $agent) => $this->presentAgent($agent))
            ->values();

        $entries = $directory
            ->when($normalizedStatus !== null, fn (Collection $collection) => $collection->where('status', $normalizedStatus))
            ->when($pumpFilter !== null, fn (Collection $collection) => $collection->where('pump_id', $pumpFilter))
            ->values();

        $summary = [
            'total_agents' => $entries->count(),
            'active' => $entries->where('status', Agent::STATUS_ACTIVE)->count(),
            'busy' => $entries->where('status', Agent::STATUS_BUSY)->count(),
            'offline' => $entries->where('status', Agent::STATUS_OFFLINE)->count(),
            'coverage_ready' => $entries->where('has_coverage_center', true)->count(),
            'needs_location' => $entries->where('has_coverage_center', false)->count(),
        ];

        $payload = [
            'generated_at' => now()->toIso8601String(),
            'generated_at_label' => now()->format('d M Y, h:i A'),
            'coverage_radius_m' => self::COVERAGE_RADIUS_METERS,
            'status_colors' => self::STATUS_COLORS,
            'summary' => $summary,
            'agents' => $entries->all(),
        ];

        return [
            'filters' => [
                'status' => $normalizedStatus,
                'pump_id' => $pumpFilter,
            ],
            'filter_options' => [
                'statuses' => [
                    ['value' => Agent::STATUS_ACTIVE, 'label' => 'Active'],
                    ['value' => Agent::STATUS_BUSY, 'label' => 'Busy'],
                    ['value' => Agent::STATUS_OFFLINE, 'label' => 'Offline'],
                ],
                'pumps' => $pumpOptions->all(),
            ],
            'agent_directory' => $directory->all(),
            'summary' => $summary,
            'payload' => $payload,
        ];
    }

    private function presentAgent(Agent $agent): array
    {
        $hasActiveDelivery = (int) ($agent->active_deliveries_count ?? 0) > 0;
        $status = $agent->computedMonitorStatus($hasActiveDelivery);
        $lastSeenAt = $agent->lastSeenAt();
        $coverageLat = $agent->coverageLatitude();
        $coverageLng = $agent->coverageLongitude();
        $liveLat = $agent->liveLatitude();
        $liveLng = $agent->liveLongitude();
        [$markerLocation, $markerLocationLabel] = $this->resolveMarkerLocation(
            $status,
            $coverageLat,
            $coverageLng,
            $liveLat,
            $liveLng,
        );
        $agentName = trim((string) ($agent->user?->name ?? ''));
        $pumpName = $this->pumpName($agent);
        $address = trim((string) $agent->address);

        return [
            'id' => $agent->id,
            'agent_name' => $agentName !== '' ? $agentName : 'Agent #' . $agent->id,
            'pump_id' => $agent->id,
            'pump_name' => $pumpName,
            'status' => $status,
            'status_label' => ucfirst($status),
            'status_color' => self::STATUS_COLORS[$status],
            'approval_status' => $agent->approval_status,
            'approval_label' => ucfirst((string) $agent->approval_status),
            'is_available' => (bool) $agent->is_available,
            'has_live_location' => $agent->hasLiveLocation(),
            'has_coverage_center' => $coverageLat !== null && $coverageLng !== null,
            'active_deliveries_count' => (int) ($agent->active_deliveries_count ?? 0),
            'last_active_at_iso' => $lastSeenAt?->toIso8601String(),
            'last_active_label' => $lastSeenAt?->format('d M Y, h:i A') ?? 'Never reported',
            'last_active_relative' => $lastSeenAt?->diffForHumans() ?? 'No heartbeat yet',
            'coverage_center' => $coverageLat !== null && $coverageLng !== null
                ? ['lat' => $coverageLat, 'lng' => $coverageLng]
                : null,
            'live_location' => $liveLat !== null && $liveLng !== null
                ? ['lat' => $liveLat, 'lng' => $liveLng]
                : null,
            'marker_location' => $markerLocation,
            'marker_location_label' => $markerLocationLabel,
            'address' => $address !== '' ? $address : 'Address not available',
        ];
    }

    private function resolveMarkerLocation(
        string $status,
        ?float $coverageLat,
        ?float $coverageLng,
        ?float $liveLat,
        ?float $liveLng,
    ): array {
        $hasCoverageCenter = $coverageLat !== null && $coverageLng !== null;
        $hasLiveLocation = $liveLat !== null && $liveLng !== null;

        if ($status === Agent::STATUS_BUSY && $hasLiveLocation) {
            return [[
                'lat' => $liveLat,
                'lng' => $liveLng,
            ], 'Live delivery position'];
        }

        if ($hasCoverageCenter) {
            return [[
                'lat' => $coverageLat,
                'lng' => $coverageLng,
            ], 'Petrol pump location'];
        }

        if ($hasLiveLocation) {
            return [[
                'lat' => $liveLat,
                'lng' => $liveLng,
            ], 'Last known agent location'];
        }

        return [null, 'Location unavailable'];
    }

    private function pumpName(Agent $agent): string
    {
        $storedName = trim((string) ($agent->name ?? ''));
        if ($storedName !== '') {
            return $storedName;
        }

        $userName = trim((string) ($agent->user?->name ?? ''));
        if ($userName !== '') {
            return $userName;
        }

        return 'Pump #' . $agent->id;
    }

    private function normalizeStatusFilter(?string $statusFilter): ?string
    {
        if (! is_string($statusFilter)) {
            return null;
        }

        $normalized = strtolower(trim($statusFilter));

        return in_array($normalized, [
            Agent::STATUS_ACTIVE,
            Agent::STATUS_BUSY,
            Agent::STATUS_OFFLINE,
        ], true) ? $normalized : null;
    }
}
