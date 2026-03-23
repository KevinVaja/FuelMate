<?php

namespace App\Services;

use App\Models\User;
use DomainException;

class UserWalletService
{
    public function getBalance(User $user): float
    {
        return $this->normalizeAmount((float) $user->wallet_balance);
    }

    public function credit(User $user, float $amount): User
    {
        $amount = $this->normalizeAmount($amount);

        if ($amount <= 0) {
            return $user->fresh() ?? $user;
        }

        $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);

        $lockedUser->update([
            'wallet_balance' => $this->normalizeAmount((float) $lockedUser->wallet_balance + $amount),
        ]);

        return $lockedUser->fresh();
    }

    public function debit(User $user, float $amount): User
    {
        $amount = $this->normalizeAmount($amount);

        if ($amount <= 0) {
            return $user->fresh() ?? $user;
        }

        $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
        $currentBalance = (float) $lockedUser->wallet_balance;

        if ($currentBalance < $amount) {
            throw new DomainException('Customer wallet balance is insufficient for this order.');
        }

        $lockedUser->update([
            'wallet_balance' => $this->normalizeAmount($currentBalance - $amount),
        ]);

        return $lockedUser->fresh();
    }

    private function normalizeAmount(float $amount): float
    {
        return round($amount, 2);
    }
}
