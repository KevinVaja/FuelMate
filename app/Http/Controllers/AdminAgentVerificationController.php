<?php

namespace App\Http\Controllers;

use App\Http\Requests\RejectAgentVerificationRequest;
use App\Models\Agent;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class AdminAgentVerificationController extends Controller
{
    public function index()
    {
        $agents = Agent::query()
            ->with('user')
            ->pendingVerification()
            ->latest()
            ->paginate(20);

        return view('admin.agent_verifications.index', compact('agents'));
    }

    public function show($id)
    {
        $agent = Agent::query()
            ->with('user')
            ->findOrFail($id);

        return view('admin.agent_verifications.show', compact('agent'));
    }

    public function approve($id)
    {
        $agent = Agent::query()->findOrFail($id);
        $agent->markVerificationApproved();

        return redirect()
            ->route('admin.agents.pending')
            ->with('success', 'Petrol pump account approved successfully.');
    }

    public function reject(RejectAgentVerificationRequest $request, $id)
    {
        $agent = Agent::query()->findOrFail($id);
        $agent->markVerificationRejected($request->validated('rejection_reason'));

        return redirect()
            ->route('admin.agents.pending')
            ->with('success', 'Petrol pump account rejected successfully.');
    }

    public function document($id, string $document): Response
    {
        $agent = Agent::query()->findOrFail($id);

        $allowedDocuments = [
            'petrol_license_photo',
            'gst_certificate_photo',
            'owner_id_proof_photo',
        ];

        abort_unless(in_array($document, $allowedDocuments, true), 404);

        $path = $agent->getAttribute($document);

        abort_unless(is_string($path) && $path !== '', 404);
        abort_unless(Storage::disk('public')->exists($path), 404);

        return Storage::disk('public')->response($path, basename($path), disposition: 'inline');
    }
}
