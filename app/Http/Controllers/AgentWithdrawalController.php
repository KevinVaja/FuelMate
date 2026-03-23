<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAgentWithdrawalRequest;
use App\Services\AgentWithdrawalService;
use App\Services\WalletService;
use DomainException;

class AgentWithdrawalController extends Controller
{
    public function __construct(
        private readonly AgentWithdrawalService $agentWithdrawalService,
        private readonly WalletService $walletService,
    ) {
    }

    public function index()
    {
        $agent = auth()->user()->agent;
        $withdrawals = $agent->withdrawals()->latestRequested()->paginate(10);
        $walletBalance = $this->walletService->getBalance($agent);
        $minimumWithdrawalAmount = $this->agentWithdrawalService->minimumWithdrawalAmount();

        return view('agent.withdrawals.index', compact('withdrawals', 'walletBalance', 'minimumWithdrawalAmount'));
    }

    public function create()
    {
        $agent = auth()->user()->agent;
        $walletBalance = $this->walletService->getBalance($agent);
        $minimumWithdrawalAmount = $this->agentWithdrawalService->minimumWithdrawalAmount();

        return view('agent.withdrawals.create', compact('walletBalance', 'minimumWithdrawalAmount'));
    }

    public function store(StoreAgentWithdrawalRequest $request)
    {
        try {
            $this->agentWithdrawalService->createRequest($request->user()->agent, $request->withdrawalData());
        } catch (DomainException $exception) {
            return back()
                ->withInput()
                ->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('agent.withdrawals.index')
            ->with('success', 'Withdrawal request submitted successfully.');
    }
}
