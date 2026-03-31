<?php

namespace App\Services;

use App\Models\Agent;
use DomainException;

class WalletService
{
    public function getBalance(Agent $agent): float
    {
        return $this->normalizeAmount((float) $agent->wallet_balance);
    }

    public function credit(Agent $agent, float $amount): Agent
    {
        $amount = $this->normalizeAmount($amount);

        if ($amount <= 0) {
            return $agent->fresh() ?? $agent;
        }

        $lockedAgent = Agent::query()->lockForUpdate()->findOrFail($agent->id);

        $lockedAgent->update([
            'wallet_balance' => $this->normalizeAmount((float) $lockedAgent->wallet_balance + $amount),
        ]);

        return $lockedAgent->fresh();
    }

    public function debit(Agent $agent, float $amount): Agent
    {
        $amount = $this->normalizeAmount($amount);

        if ($amount <= 0) {
            return $agent->fresh() ?? $agent;
        }

        $lockedAgent = Agent::query()->lockForUpdate()->findOrFail($agent->id);
        $currentBalance = (float) $lockedAgent->wallet_balance;

        if ($currentBalance < $amount) {
            throw new DomainException('Agent wallet balance is insufficient for this payout.');
        }

        $lockedAgent->update([
            'wallet_balance' => $this->normalizeAmount($currentBalance - $amount),
        ]);

        return $lockedAgent->fresh();
    }

    private function normalizeAmount(float $amount): float
    {
        return round($amount, 2);
    }
}
