<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdminRejectWithdrawalRequest;
use App\Http\Requests\AdminWithdrawalIndexRequest;
use App\Models\AgentWithdrawal;
use App\Services\AgentWithdrawalService;
use DomainException;

class AdminWithdrawalController extends Controller
{
    public function __construct(
        private readonly AgentWithdrawalService $agentWithdrawalService,
    ) {
    }

    public function index(AdminWithdrawalIndexRequest $request)
    {
        $status = $request->validated('status');

        $withdrawals = AgentWithdrawal::query()
            ->with('agent.user')
            ->when($status, fn ($query) => $query->where('status', $status))
            ->latestRequested()
            ->paginate(20)
            ->withQueryString();

        return view('admin.withdrawals.index', compact('withdrawals', 'status'));
    }

    public function approve(int $id)
    {
        $withdrawal = AgentWithdrawal::query()->findOrFail($id);

        try {
            $this->agentWithdrawalService->approve($withdrawal);
        } catch (DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Withdrawal request approved.');
    }

    public function reject(AdminRejectWithdrawalRequest $request, int $id)
    {
        $withdrawal = AgentWithdrawal::query()->findOrFail($id);

        try {
            $this->agentWithdrawalService->reject($withdrawal, $request->validated('admin_note'));
        } catch (DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Withdrawal request rejected.');
    }

    public function markCompleted(int $id)
    {
        $withdrawal = AgentWithdrawal::query()->findOrFail($id);

        try {
            $this->agentWithdrawalService->markCompleted($withdrawal);
        } catch (DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Withdrawal marked as completed and wallet balance updated.');
    }
}
