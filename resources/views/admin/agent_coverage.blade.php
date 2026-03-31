@extends('layouts.app')

@section('title', 'Agent Coverage Monitor')

@section('head')
  <link
    rel="stylesheet"
    href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
    integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
    crossorigin=""
  >
  <style>
    .coverage-shell {
      display: grid;
      gap: 1.5rem;
    }

    .coverage-hero {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      gap: 1rem;
      padding: 1.5rem;
      border-radius: 1.5rem;
      background:
        radial-gradient(circle at top left, rgba(20, 184, 166, 0.16), transparent 38%),
        radial-gradient(circle at top right, rgba(251, 146, 60, 0.16), transparent 34%),
        linear-gradient(135deg, rgba(255, 255, 255, 0.96), rgba(247, 244, 239, 0.96));
      box-shadow: 0 24px 60px rgba(15, 23, 42, 0.08);
    }

    .coverage-hero__eyebrow {
      display: inline-flex;
      align-items: center;
      gap: 0.45rem;
      padding: 0.45rem 0.8rem;
      border-radius: 999px;
      background: rgba(15, 23, 42, 0.06);
      color: #475569;
      font-size: 0.78rem;
      font-weight: 700;
      letter-spacing: 0.08em;
      text-transform: uppercase;
    }

    .coverage-hero h4 {
      margin: 0.9rem 0 0.55rem;
      font-size: clamp(1.8rem, 3vw, 2.45rem);
      font-weight: 800;
      letter-spacing: -0.04em;
      color: #1f2937;
    }

    .coverage-hero p {
      margin: 0;
      max-width: 54rem;
      color: #64748b;
      font-size: 0.98rem;
      line-height: 1.7;
    }

    .coverage-hero__meta {
      min-width: 220px;
      padding: 1rem 1.05rem;
      border-radius: 1.1rem;
      background: rgba(255, 255, 255, 0.78);
      border: 1px solid rgba(148, 163, 184, 0.2);
      box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.65);
    }

    .coverage-hero__meta-label {
      display: block;
      color: #64748b;
      font-size: 0.78rem;
      text-transform: uppercase;
      letter-spacing: 0.08em;
      font-weight: 700;
      margin-bottom: 0.35rem;
    }

    .coverage-hero__meta-value {
      color: #111827;
      font-weight: 700;
    }

    .coverage-summary {
      display: grid;
      grid-template-columns: repeat(5, minmax(0, 1fr));
      gap: 1rem;
    }

    .coverage-summary-card {
      padding: 1.05rem 1.15rem;
      border-radius: 1.2rem;
      background: rgba(255, 255, 255, 0.94);
      border: 1px solid rgba(226, 232, 240, 0.9);
      box-shadow: 0 18px 40px rgba(15, 23, 42, 0.05);
    }

    .coverage-summary-card__label {
      display: block;
      margin-bottom: 0.45rem;
      color: #64748b;
      font-size: 0.8rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.08em;
    }

    .coverage-summary-card__value {
      font-size: 1.7rem;
      font-weight: 800;
      letter-spacing: -0.05em;
      color: #111827;
    }

    .coverage-summary-card__hint {
      margin-top: 0.35rem;
      color: #94a3b8;
      font-size: 0.86rem;
    }

    .coverage-summary-card[data-tone="active"] .coverage-summary-card__value {
      color: #1d9b63;
    }

    .coverage-summary-card[data-tone="busy"] .coverage-summary-card__value {
      color: #f08c00;
    }

    .coverage-summary-card[data-tone="offline"] .coverage-summary-card__value {
      color: #d9485f;
    }

    .coverage-summary-card[data-tone="coverage"] .coverage-summary-card__value {
      color: #1769aa;
    }

    .coverage-card {
      border: 0;
      border-radius: 1.35rem;
      background: rgba(255, 255, 255, 0.96);
      box-shadow: 0 20px 45px rgba(15, 23, 42, 0.07);
      overflow: hidden;
    }

    .coverage-card__header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 1rem;
      padding: 1.2rem 1.35rem 0;
    }

    .coverage-card__title {
      margin: 0;
      font-size: 1.1rem;
      font-weight: 800;
      color: #111827;
    }

    .coverage-card__subtitle {
      margin: 0.3rem 0 0;
      color: #64748b;
      font-size: 0.92rem;
    }

    .coverage-filters {
      padding: 1.25rem 1.35rem;
    }

    .coverage-map {
      width: 100%;
      min-height: 520px;
      background:
        linear-gradient(135deg, rgba(241, 245, 249, 0.95), rgba(248, 250, 252, 0.98));
    }

    .coverage-toolbar {
      display: flex;
      flex-wrap: wrap;
      gap: 0.85rem;
      align-items: center;
      justify-content: space-between;
      padding: 0 1.35rem 1rem;
    }

    .coverage-toolbar__toggles {
      display: flex;
      flex-wrap: wrap;
      gap: 0.8rem;
      align-items: center;
    }

    .coverage-toggle {
      display: inline-flex;
      align-items: center;
      gap: 0.45rem;
      padding: 0.55rem 0.85rem;
      border-radius: 999px;
      background: rgba(15, 23, 42, 0.04);
      color: #334155;
      font-size: 0.88rem;
      font-weight: 600;
    }

    .coverage-toggle input {
      accent-color: #111827;
    }

    .coverage-legend {
      display: flex;
      flex-wrap: wrap;
      gap: 0.85rem;
      align-items: center;
      color: #475569;
      font-size: 0.9rem;
    }

    .coverage-legend__item {
      display: inline-flex;
      align-items: center;
      gap: 0.45rem;
    }

    .coverage-legend__swatch {
      width: 0.9rem;
      height: 0.9rem;
      border-radius: 999px;
      flex: 0 0 auto;
      box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.95);
    }

    .coverage-pump-icon {
      width: 26px;
      height: 26px;
      border-radius: 0.8rem 0.8rem 0.8rem 0.2rem;
      background: #0f172a;
      color: #fff;
      display: grid;
      place-items: center;
      border: 2px solid rgba(255, 255, 255, 0.95);
      box-shadow: 0 12px 20px rgba(15, 23, 42, 0.22);
      transform: rotate(45deg);
    }

    .coverage-pump-icon i {
      transform: rotate(-45deg);
      font-size: 0.72rem;
    }

    .coverage-agent-icon {
      width: 18px;
      height: 18px;
      border-radius: 999px;
      border: 3px solid rgba(255, 255, 255, 0.98);
      background: var(--agent-color, #1d9b63);
      box-shadow: 0 10px 18px rgba(15, 23, 42, 0.2);
    }

    .coverage-popup {
      min-width: 220px;
    }

    .coverage-popup__title {
      font-weight: 800;
      color: #111827;
      margin-bottom: 0.45rem;
    }

    .coverage-popup__line {
      color: #475569;
      font-size: 0.9rem;
      line-height: 1.55;
    }

    .coverage-table thead th {
      border: 0;
      padding: 0.95rem 1rem;
      color: #475569;
      font-size: 0.78rem;
      font-weight: 800;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      white-space: nowrap;
    }

    .coverage-table tbody td {
      padding: 1rem;
      vertical-align: middle;
      border-color: rgba(226, 232, 240, 0.85);
    }

    .coverage-row {
      cursor: pointer;
      transition: background-color 0.2s ease, transform 0.2s ease;
    }

    .coverage-row:hover {
      background: rgba(249, 250, 251, 0.95);
    }

    .coverage-row.is-selected {
      background: rgba(14, 116, 144, 0.08);
      box-shadow: inset 4px 0 0 #0f766e;
    }

    .coverage-table__primary {
      display: block;
      color: #111827;
      font-weight: 700;
      line-height: 1.4;
    }

    .coverage-table__secondary {
      display: block;
      margin-top: 0.22rem;
      color: #64748b;
      font-size: 0.88rem;
      line-height: 1.5;
    }

    .coverage-status-badge {
      display: inline-flex;
      align-items: center;
      gap: 0.45rem;
      padding: 0.45rem 0.7rem;
      border-radius: 999px;
      font-size: 0.82rem;
      font-weight: 700;
      border: 1px solid rgba(148, 163, 184, 0.22);
      background: rgba(248, 250, 252, 0.95);
      color: #1f2937;
    }

    .coverage-status-badge__dot {
      width: 0.7rem;
      height: 0.7rem;
      border-radius: 999px;
      flex: 0 0 auto;
      background: currentColor;
    }

    .coverage-status-badge[data-status="active"] {
      color: #1d9b63;
      background: rgba(29, 155, 99, 0.08);
    }

    .coverage-status-badge[data-status="busy"] {
      color: #f08c00;
      background: rgba(240, 140, 0, 0.1);
    }

    .coverage-status-badge[data-status="offline"] {
      color: #d9485f;
      background: rgba(217, 72, 95, 0.1);
    }

    .coverage-empty {
      padding: 2.8rem 1rem;
      text-align: center;
      color: #64748b;
    }

    @media (max-width: 1199px) {
      .coverage-summary {
        grid-template-columns: repeat(2, minmax(0, 1fr));
      }
    }

    @media (max-width: 991px) {
      .coverage-hero {
        flex-direction: column;
      }

      .coverage-hero__meta {
        width: 100%;
      }

      .coverage-map {
        min-height: 460px;
      }
    }

    @media (max-width: 767px) {
      .coverage-summary {
        grid-template-columns: minmax(0, 1fr);
      }

      .coverage-card__header,
      .coverage-toolbar,
      .coverage-filters {
        padding-left: 1rem;
        padding-right: 1rem;
      }

      .coverage-map {
        min-height: 390px;
      }
    }
  </style>
@endsection

@section('content')
  <div class="coverage-shell">
    <section class="coverage-hero">
      <div>
        <span class="coverage-hero__eyebrow"><i class="fas fa-bullseye"></i> Coverage Intelligence</span>
        <h4>Agent Coverage &amp; Activity Monitor</h4>
        <p>
          Track each petrol pump’s 25 km delivery radius, see where agents are currently reporting from,
          and spot who is active, busy, or offline without leaving the admin panel.
        </p>
      </div>
      <div class="coverage-hero__meta">
        <span class="coverage-hero__meta-label">Last refreshed</span>
        <div class="coverage-hero__meta-value" id="monitorUpdatedAt">{{ $mapPayload['generated_at_label'] }}</div>
        <div class="coverage-summary-card__hint mt-2">Auto-refreshes every 12 seconds while you keep this page open.</div>
      </div>
    </section>

    <section class="coverage-summary">
      <article class="coverage-summary-card">
        <span class="coverage-summary-card__label">Visible agents</span>
        <div class="coverage-summary-card__value" id="summaryTotalAgents">{{ $summary['total_agents'] }}</div>
        <div class="coverage-summary-card__hint">Current filtered result set</div>
      </article>
      <article class="coverage-summary-card" data-tone="active">
        <span class="coverage-summary-card__label">Active</span>
        <div class="coverage-summary-card__value" id="summaryActiveAgents">{{ $summary['active'] }}</div>
        <div class="coverage-summary-card__hint">Available and recently reporting</div>
      </article>
      <article class="coverage-summary-card" data-tone="busy">
        <span class="coverage-summary-card__label">Busy</span>
        <div class="coverage-summary-card__value" id="summaryBusyAgents">{{ $summary['busy'] }}</div>
        <div class="coverage-summary-card__hint">On an active delivery lifecycle</div>
      </article>
      <article class="coverage-summary-card" data-tone="offline">
        <span class="coverage-summary-card__label">Offline</span>
        <div class="coverage-summary-card__value" id="summaryOfflineAgents">{{ $summary['offline'] }}</div>
        <div class="coverage-summary-card__hint">Not available or not recently active</div>
      </article>
      <article class="coverage-summary-card" data-tone="coverage">
        <span class="coverage-summary-card__label">Coverage ready</span>
        <div class="coverage-summary-card__value" id="summaryCoverageReady">{{ $summary['coverage_ready'] }}</div>
        <div class="coverage-summary-card__hint">Pump coverage centers with map coordinates</div>
      </article>
    </section>

    <section class="coverage-card">
      <div class="coverage-card__header">
        <div>
          <h5 class="coverage-card__title">Filters</h5>
          <p class="coverage-card__subtitle">Slice the monitor by operational status or a specific petrol pump.</p>
        </div>
      </div>
      <div class="coverage-filters">
        <form method="GET" action="{{ route('admin.agent_coverage') }}" class="row g-3 align-items-end">
          <div class="col-12 col-lg-4">
            <label for="statusFilter" class="form-label">Status</label>
            <select name="status" id="statusFilter" class="form-select">
              <option value="">All statuses</option>
              @foreach($filterOptions['statuses'] as $statusOption)
                <option value="{{ $statusOption['value'] }}" @selected(($filters['status'] ?? null) === $statusOption['value'])>
                  {{ $statusOption['label'] }}
                </option>
              @endforeach
            </select>
          </div>
          <div class="col-12 col-lg-5">
            <label for="pumpFilter" class="form-label">Petrol Pump</label>
            <select name="pump_id" id="pumpFilter" class="form-select">
              <option value="">All petrol pumps</option>
              @foreach($filterOptions['pumps'] as $pumpOption)
                <option value="{{ $pumpOption['id'] }}" @selected(($filters['pump_id'] ?? null) === $pumpOption['id'])>
                  {{ $pumpOption['name'] }}{{ $pumpOption['has_coordinates'] ? '' : ' (location missing)' }}
                </option>
              @endforeach
            </select>
          </div>
          <div class="col-12 col-lg-3 d-flex gap-2">
            <button type="submit" class="btn btn-primary flex-fill">
              <i class="fas fa-filter me-2"></i>Apply Filters
            </button>
            <a href="{{ route('admin.agent_coverage') }}" class="btn btn-outline-secondary">Reset</a>
          </div>
        </form>
      </div>
    </section>

    <section class="coverage-card">
      <div class="coverage-card__header">
        <div>
          <h5 class="coverage-card__title">Correct Pump Location</h5>
          <p class="coverage-card__subtitle">
            If an older agent shows up in the wrong place, update the saved petrol pump coordinates here.
            Active and offline agent markers will use this corrected pump location.
          </p>
        </div>
      </div>
      <div class="coverage-filters">
        <form
          method="POST"
          id="pumpLocationForm"
          data-action-template="{{ route('admin.agents.pump_location.update', ['id' => '__AGENT__']) }}"
          class="row g-3 align-items-end"
        >
          @csrf
          @method('PATCH')
          <div class="col-12 col-lg-4">
            <label for="pumpLocationAgentId" class="form-label">Agent / Petrol Pump</label>
            <select id="pumpLocationAgentId" class="form-select" required>
              <option value="">Select an agent</option>
              @foreach($filterOptions['pumps'] as $pumpOption)
                <option value="{{ $pumpOption['id'] }}">{{ $pumpOption['name'] }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-12 col-md-6 col-lg-3">
            <label for="pumpLatitudeInput" class="form-label">Pump Latitude</label>
            <input
              type="number"
              step="any"
              min="-90"
              max="90"
              id="pumpLatitudeInput"
              name="pump_latitude"
              class="form-control"
              placeholder="21.0537000"
              required
            >
          </div>
          <div class="col-12 col-md-6 col-lg-3">
            <label for="pumpLongitudeInput" class="form-label">Pump Longitude</label>
            <input
              type="number"
              step="any"
              min="-180"
              max="180"
              id="pumpLongitudeInput"
              name="pump_longitude"
              class="form-control"
              placeholder="70.5165000"
              required
            >
          </div>
          <div class="col-12 col-lg-2 d-grid">
            <button type="submit" class="btn btn-primary">
              <i class="fas fa-location-dot me-2"></i>Save Location
            </button>
          </div>
          <div class="col-12 d-flex flex-wrap gap-2 align-items-center">
            <button type="button" class="btn btn-outline-secondary btn-sm" id="useCurrentMarkerLocationBtn">
              Use selected marker location
            </button>
            <span class="text-muted small" id="pumpLocationHelper">
              Click an agent row or choose an agent here to load the current saved coordinates.
            </span>
          </div>
        </form>
      </div>
    </section>

    <section class="coverage-card">
      <div class="coverage-card__header">
        <div>
          <h5 class="coverage-card__title">Coverage Map</h5>
          <p class="coverage-card__subtitle">Coverage circles are always anchored to the petrol pump center with a fixed 25 km radius.</p>
        </div>
      </div>
      <div class="coverage-toolbar">
        <div class="coverage-toolbar__toggles">
          <label class="coverage-toggle" for="toggleCoverageCircles">
            <input type="checkbox" id="toggleCoverageCircles" checked>
            <span>Show 25 km circles</span>
          </label>
          <span class="coverage-toggle">
            <i class="fas fa-shield-halved"></i>
            <span>Visualization only. Billing rules still enforce the 25 km backend check.</span>
          </span>
        </div>
        <div class="coverage-legend">
          <span class="coverage-legend__item">
            <span class="coverage-legend__swatch" style="background:#1d9b63;"></span>
            Active
          </span>
          <span class="coverage-legend__item">
            <span class="coverage-legend__swatch" style="background:#f08c00;"></span>
            Busy
          </span>
          <span class="coverage-legend__item">
            <span class="coverage-legend__swatch" style="background:#d9485f;"></span>
            Offline
          </span>
          <span class="coverage-legend__item">
            <span class="coverage-pump-icon"><i class="fas fa-gas-pump"></i></span>
            Petrol Pump
          </span>
        </div>
      </div>
      <div id="agentCoverageMap" class="coverage-map"></div>
    </section>

    <div class="alert alert-warning d-none mb-0" id="coverageLocationWarning"></div>

    <section class="coverage-card">
      <div class="coverage-card__header">
        <div>
          <h5 class="coverage-card__title">Agent Table</h5>
          <p class="coverage-card__subtitle">
            Click any row to focus the agent on the map and inspect the pump coverage circle.
          </p>
        </div>
        <span class="coverage-toggle">
          <i class="fas fa-table-list"></i>
          <span><strong id="coverageTableCount">{{ $summary['total_agents'] }}</strong> visible entries</span>
        </span>
      </div>
      <div class="table-responsive">
        <table class="table table-hover mb-0 coverage-table">
          <thead class="table-light">
            <tr>
              <th>Agent Name</th>
              <th>Petrol Pump Name</th>
              <th>Status</th>
              <th>Last Active Time</th>
            </tr>
          </thead>
          <tbody id="coverageTableBody"></tbody>
        </table>
      </div>
    </section>
  </div>
@endsection

@section('scripts')
  <script
    src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
    integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
    crossorigin=""
  ></script>
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const initialPayload = @json($mapPayload);
      const agentDirectory = @json($agentDirectory);
      const dataEndpoint = new URL(@json(route('admin.agent_coverage.data')), window.location.origin);
      dataEndpoint.search = window.location.search;

      const mapElement = document.getElementById('agentCoverageMap');
      const tableBody = document.getElementById('coverageTableBody');
      const updatedAt = document.getElementById('monitorUpdatedAt');
      const coverageTableCount = document.getElementById('coverageTableCount');
      const coverageLocationWarning = document.getElementById('coverageLocationWarning');
      const toggleCoverageCircles = document.getElementById('toggleCoverageCircles');
      const pumpLocationForm = document.getElementById('pumpLocationForm');
      const pumpLocationAgentId = document.getElementById('pumpLocationAgentId');
      const pumpLatitudeInput = document.getElementById('pumpLatitudeInput');
      const pumpLongitudeInput = document.getElementById('pumpLongitudeInput');
      const pumpLocationHelper = document.getElementById('pumpLocationHelper');
      const useCurrentMarkerLocationBtn = document.getElementById('useCurrentMarkerLocationBtn');

      const summaryElements = {
        total: document.getElementById('summaryTotalAgents'),
        active: document.getElementById('summaryActiveAgents'),
        busy: document.getElementById('summaryBusyAgents'),
        offline: document.getElementById('summaryOfflineAgents'),
        coverageReady: document.getElementById('summaryCoverageReady'),
      };

      const map = L.map(mapElement, {
        zoomControl: true,
        scrollWheelZoom: true,
      });

      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors',
      }).addTo(map);

      const coverageLayer = L.layerGroup().addTo(map);
      const pumpLayer = L.layerGroup().addTo(map);
      const agentLayer = L.layerGroup().addTo(map);

      const pumpIcon = L.divIcon({
        className: '',
        html: '<span class="coverage-pump-icon"><i class="fas fa-gas-pump"></i></span>',
        iconSize: [26, 26],
        iconAnchor: [13, 13],
        popupAnchor: [0, -12],
      });

      const markersByAgentId = new Map();
      const pumpMarkersByAgentId = new Map();
      const agentDirectoryById = new Map(agentDirectory.map((agent) => [Number(agent.id), agent]));
      let selectedAgentId = null;
      let refreshInFlight = false;

      const defaultMapCenter = [22.9734, 78.6569];
      map.setView(defaultMapCenter, 5);

      const escapeHtml = (value) => String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

      const agentIcon = (color) => L.divIcon({
        className: '',
        html: `<span class="coverage-agent-icon" style="--agent-color:${escapeHtml(color)};"></span>`,
        iconSize: [18, 18],
        iconAnchor: [9, 9],
        popupAnchor: [0, -12],
      });

      const statusBadge = (agent) => `
        <span class="coverage-status-badge" data-status="${escapeHtml(agent.status)}">
          <span class="coverage-status-badge__dot"></span>
          ${escapeHtml(agent.status_label)}
        </span>
      `;

      const buildAgentPopup = (agent) => `
        <div class="coverage-popup">
          <div class="coverage-popup__title">${escapeHtml(agent.agent_name)}</div>
          <div class="coverage-popup__line"><strong>Status:</strong> ${escapeHtml(agent.status_label)}</div>
          <div class="coverage-popup__line"><strong>Assigned Petrol Pump:</strong> ${escapeHtml(agent.pump_name)}</div>
          <div class="coverage-popup__line"><strong>Map Position:</strong> ${escapeHtml(agent.marker_location_label)}</div>
          <div class="coverage-popup__line"><strong>Last Active:</strong> ${escapeHtml(agent.last_active_label)} (${escapeHtml(agent.last_active_relative)})</div>
          <div class="coverage-popup__line"><strong>Address:</strong> ${escapeHtml(agent.address)}</div>
        </div>
      `;

      const buildPumpPopup = (agent, coverageRadiusKm) => `
        <div class="coverage-popup">
          <div class="coverage-popup__title">${escapeHtml(agent.pump_name)}</div>
          <div class="coverage-popup__line"><strong>Coverage Radius:</strong> ${coverageRadiusKm} km</div>
          <div class="coverage-popup__line"><strong>Current Agent Status:</strong> ${escapeHtml(agent.status_label)}</div>
          <div class="coverage-popup__line"><strong>Coverage Center:</strong> ${escapeHtml(agent.address)}</div>
        </div>
      `;

      const updateSummary = (payload) => {
        summaryElements.total.textContent = payload.summary.total_agents;
        summaryElements.active.textContent = payload.summary.active;
        summaryElements.busy.textContent = payload.summary.busy;
        summaryElements.offline.textContent = payload.summary.offline;
        summaryElements.coverageReady.textContent = payload.summary.coverage_ready;
        coverageTableCount.textContent = payload.summary.total_agents;
        updatedAt.textContent = payload.generated_at_label;

        if (payload.summary.needs_location > 0) {
          coverageLocationWarning.classList.remove('d-none');
          coverageLocationWarning.innerHTML = `<i class="fas fa-location-crosshairs me-2"></i>${payload.summary.needs_location} agent account(s) still need a saved pump location before the 25 km coverage circle can be drawn.`;
        } else {
          coverageLocationWarning.classList.add('d-none');
          coverageLocationWarning.textContent = '';
        }
      };

      const syncPumpLocationForm = (agentId) => {
        const agent = agentDirectoryById.get(Number(agentId));

        if (!agent) {
          return;
        }

        pumpLocationAgentId.value = agent.id;
        pumpLatitudeInput.value = agent.coverage_center?.lat ?? '';
        pumpLongitudeInput.value = agent.coverage_center?.lng ?? '';
        pumpLocationHelper.textContent = `${agent.pump_name}: ${agent.marker_location_label}. Update these values if the saved pump pin is incorrect.`;
        pumpLocationForm.action = pumpLocationForm.dataset.actionTemplate.replace('__AGENT__', agent.id);
      };

      const setSelectedRow = (agentId) => {
        selectedAgentId = agentId;

        tableBody.querySelectorAll('.coverage-row').forEach((row) => {
          row.classList.toggle('is-selected', Number(row.dataset.agentId) === Number(agentId));
        });
      };

      const focusAgent = (agentId, shouldOpenPopup = true) => {
        const marker = markersByAgentId.get(Number(agentId)) || pumpMarkersByAgentId.get(Number(agentId));

        if (!marker) {
          return;
        }

        setSelectedRow(agentId);
        syncPumpLocationForm(agentId);
        map.flyTo(marker.getLatLng(), Math.max(map.getZoom(), 11), {
          animate: true,
          duration: 0.65,
        });

        if (shouldOpenPopup) {
          marker.openPopup();
        }
      };

      const renderEmptyTable = () => {
        tableBody.innerHTML = `
          <tr>
            <td colspan="4" class="coverage-empty">
              No agents matched the current filters.
            </td>
          </tr>
        `;
      };

      const renderTable = (agents) => {
        if (agents.length === 0) {
          renderEmptyTable();
          return;
        }

        tableBody.innerHTML = agents.map((agent) => `
          <tr class="coverage-row" data-agent-id="${agent.id}">
            <td>
              <span class="coverage-table__primary">${escapeHtml(agent.agent_name)}</span>
              <span class="coverage-table__secondary">Approval: ${escapeHtml(agent.approval_label)}</span>
            </td>
            <td>
              <span class="coverage-table__primary">${escapeHtml(agent.pump_name)}</span>
              <span class="coverage-table__secondary">${escapeHtml(agent.address)}</span>
            </td>
            <td>${statusBadge(agent)}</td>
            <td>
              <span class="coverage-table__primary">${escapeHtml(agent.last_active_label)}</span>
              <span class="coverage-table__secondary">${escapeHtml(agent.last_active_relative)}</span>
            </td>
          </tr>
        `).join('');

        tableBody.querySelectorAll('.coverage-row').forEach((row) => {
          row.addEventListener('click', () => focusAgent(Number(row.dataset.agentId)));
        });

        if (selectedAgentId !== null) {
          setSelectedRow(selectedAgentId);
        }
      };

      const renderMap = (payload, preserveViewport = false) => {
        coverageLayer.clearLayers();
        pumpLayer.clearLayers();
        agentLayer.clearLayers();
        markersByAgentId.clear();
        pumpMarkersByAgentId.clear();

        const bounds = [];
        const coverageRadiusKm = Math.round(payload.coverage_radius_m / 1000);

        payload.agents.forEach((agent) => {
          if (agent.coverage_center) {
            const coverageLatLng = [agent.coverage_center.lat, agent.coverage_center.lng];

            const circle = L.circle(coverageLatLng, {
              radius: payload.coverage_radius_m,
              color: agent.status_color,
              weight: 2,
              opacity: 0.55,
              fillColor: agent.status_color,
              fillOpacity: 0.08,
            });

            circle.addTo(coverageLayer);

            const pumpMarker = L.marker(coverageLatLng, {
              icon: pumpIcon,
            }).bindPopup(buildPumpPopup(agent, coverageRadiusKm));

            pumpMarker.on('popupopen', () => setSelectedRow(agent.id));
            pumpMarker.addTo(pumpLayer);
            pumpMarkersByAgentId.set(agent.id, pumpMarker);
            bounds.push(coverageLatLng);
          }

          if (agent.marker_location) {
            const agentLatLng = [agent.marker_location.lat, agent.marker_location.lng];
            const marker = L.marker(agentLatLng, {
              icon: agentIcon(agent.status_color),
              zIndexOffset: 600,
            }).bindPopup(buildAgentPopup(agent));

            marker.on('popupopen', () => setSelectedRow(agent.id));
            marker.addTo(agentLayer);
            markersByAgentId.set(agent.id, marker);
            bounds.push(agentLatLng);
          }
        });

        if (!toggleCoverageCircles.checked && map.hasLayer(coverageLayer)) {
          map.removeLayer(coverageLayer);
        } else if (toggleCoverageCircles.checked && !map.hasLayer(coverageLayer)) {
          map.addLayer(coverageLayer);
        }

        if (bounds.length === 0) {
          map.setView(defaultMapCenter, 5);
          return;
        }

        if (!preserveViewport) {
          map.fitBounds(bounds, { padding: [38, 38] });
        }
      };

      const renderMonitor = (payload, preserveViewport = false) => {
        updateSummary(payload);
        renderMap(payload, preserveViewport);
        renderTable(payload.agents);

        if (selectedAgentId !== null) {
          const selectedMarkerExists = markersByAgentId.has(selectedAgentId) || pumpMarkersByAgentId.has(selectedAgentId);

          if (selectedMarkerExists) {
            setSelectedRow(selectedAgentId);
          } else {
            selectedAgentId = null;
          }
        }
      };

      toggleCoverageCircles.addEventListener('change', () => {
        if (toggleCoverageCircles.checked) {
          map.addLayer(coverageLayer);
        } else {
          map.removeLayer(coverageLayer);
        }
      });

      pumpLocationAgentId.addEventListener('change', () => {
        if (pumpLocationAgentId.value === '') {
          return;
        }

        syncPumpLocationForm(Number(pumpLocationAgentId.value));
      });

      useCurrentMarkerLocationBtn.addEventListener('click', () => {
        const agentId = Number(pumpLocationAgentId.value || selectedAgentId);
        const agent = agentDirectoryById.get(agentId);

        if (!agent || !agent.marker_location) {
          pumpLocationHelper.textContent = 'Select an agent with a visible map position first.';
          return;
        }

        pumpLatitudeInput.value = agent.marker_location.lat;
        pumpLongitudeInput.value = agent.marker_location.lng;
        pumpLocationHelper.textContent = `${agent.pump_name}: copied ${agent.marker_location_label.toLowerCase()} into the form. Save to make it the new pump location.`;
      });

      const refreshMonitor = async () => {
        if (refreshInFlight) {
          return;
        }

        refreshInFlight = true;

        try {
          const response = await fetch(dataEndpoint.toString(), {
            headers: {
              'Accept': 'application/json',
              'X-Requested-With': 'XMLHttpRequest',
            },
          });

          if (!response.ok) {
            throw new Error('Unable to refresh the coverage map right now.');
          }

          const payload = await response.json();
          renderMonitor(payload, true);
        } catch (error) {
          console.warn(error);
        } finally {
          refreshInFlight = false;
        }
      };

      renderMonitor(initialPayload, false);
      if (agentDirectory.length > 0) {
        syncPumpLocationForm(agentDirectory[0].id);
      }
      window.setTimeout(() => map.invalidateSize(), 120);
      window.addEventListener('resize', () => map.invalidateSize());
      window.setInterval(refreshMonitor, 12000);
    });
  </script>
@endsection
