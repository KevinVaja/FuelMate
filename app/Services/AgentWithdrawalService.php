<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\AgentWithdrawal;
use DomainException;
use Illuminate\Support\Facades\DB;

class AgentWithdrawalService
{
    public function __construct(
        private readonly WalletService $walletService,
    ) {
    }

    public function minimumWithdrawalAmount(): float
    {
        return (float) config('wallet.minimum_withdrawal_amount', 500);
    }

    public function createRequest(Agent $agent, array $data): AgentWithdrawal
    {
        $amount = $this->normalizeAmount((float) $data['amount']);

        return DB::transaction(function () use ($agent, $data, $amount) {
            $lockedAgent = Agent::query()->lockForUpdate()->findOrFail($agent->id);

            if ($amount < $this->minimumWithdrawalAmount()) {
                throw new DomainException('Minimum withdrawal amount is ₹' . number_format($this->minimumWithdrawalAmount(), 0) . '.');
            }

            if ($this->walletService->getBalance($lockedAgent) < $amount) {
                throw new DomainException('Withdrawal amount exceeds your available wallet balance.');
            }

            if ($lockedAgent->withdrawals()->where('status', AgentWithdrawal::STATUS_PENDING)->exists()) {
                throw new DomainException('You already have a pending withdrawal request.');
            }

            return $lockedAgent->withdrawals()->create([
                'amount' => $amount,
                'payout_method' => $data['payout_method'],
                'account_holder_name' => $data['payout_method'] === AgentWithdrawal::PAYOUT_METHOD_BANK ? $data['account_holder_name'] : null,
                'account_number' => $data['payout_method'] === AgentWithdrawal::PAYOUT_METHOD_BANK ? $data['account_number'] : null,
                'ifsc_code' => $data['payout_method'] === AgentWithdrawal::PAYOUT_METHOD_BANK ? strtoupper($data['ifsc_code']) : null,
                'upi_id' => $data['payout_method'] === AgentWithdrawal::PAYOUT_METHOD_UPI ? $data['upi_id'] : null,
                'status' => AgentWithdrawal::STATUS_PENDING,
                'requested_at' => now(),
            ]);
        });
    }

    public function approve(AgentWithdrawal $withdrawal): AgentWithdrawal
    {
        if (! $withdrawal->isPending()) {
            throw new DomainException('Only pending withdrawal requests can be approved.');
        }

        $withdrawal->update([
            'status' => AgentWithdrawal::STATUS_APPROVED,
            'processed_at' => now(),
        ]);

        return $withdrawal->fresh(['agent.user']);
    }

    public function reject(AgentWithdrawal $withdrawal, string $adminNote): AgentWithdrawal
    {
        if ($withdrawal->isCompleted()) {
            throw new DomainException('Completed withdrawal requests cannot be rejected.');
        }

        if ($withdrawal->isRejected()) {
            throw new DomainException('This withdrawal request has already been rejected.');
        }

        $withdrawal->update([
            'status' => AgentWithdrawal::STATUS_REJECTED,
            'admin_note' => $adminNote,
            'processed_at' => now(),
        ]);

        return $withdrawal->fresh(['agent.user']);
    }

    public function markCompleted(AgentWithdrawal $withdrawal): AgentWithdrawal
    {
        return DB::transaction(function () use ($withdrawal) {
            $lockedWithdrawal = AgentWithdrawal::query()
                ->with('agent.user')
                ->lockForUpdate()
                ->findOrFail($withdrawal->id);

            if (! $lockedWithdrawal->isApproved()) {
                throw new DomainException('Only approved withdrawal requests can be marked as completed.');
            }

            $this->walletService->debit($lockedWithdrawal->agent, (float) $lockedWithdrawal->amount);

            $lockedWithdrawal->update([
                'status' => AgentWithdrawal::STATUS_COMPLETED,
                'processed_at' => now(),
            ]);

            return $lockedWithdrawal->fresh(['agent.user']);
        });
    }

    private function normalizeAmount(float $amount): float
    {
        return round($amount, 2);
    }
}
