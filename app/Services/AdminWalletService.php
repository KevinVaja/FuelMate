<?php

namespace App\Services;

use App\Models\AdminAccount;
use App\Models\User;

class AdminWalletService
{
    public function getBalance(?AdminAccount $adminAccount = null): float
    {
        $adminAccount ??= $this->resolvePrimaryAdminAccount();

        return $this->normalizeAmount((float) $adminAccount->wallet_balance);
    }

    public function credit(float $amount, ?AdminAccount $adminAccount = null): AdminAccount
    {
        $amount = $this->normalizeAmount($amount);
        $adminAccount ??= $this->resolvePrimaryAdminAccount();

        if ($amount <= 0) {
            return $adminAccount->fresh() ?? $adminAccount;
        }

        $lockedAdmin = AdminAccount::query()->lockForUpdate()->findOrFail($adminAccount->getKey());

        $lockedAdmin->update([
            'wallet_balance' => $this->normalizeAmount((float) $lockedAdmin->wallet_balance + $amount),
        ]);

        return $lockedAdmin->fresh();
    }

    public function debit(float $amount, ?AdminAccount $adminAccount = null): AdminAccount
    {
        $amount = $this->normalizeAmount($amount);
        $adminAccount ??= $this->resolvePrimaryAdminAccount();

        if ($amount <= 0) {
            return $adminAccount->fresh() ?? $adminAccount;
        }

        $lockedAdmin = AdminAccount::query()->lockForUpdate()->findOrFail($adminAccount->getKey());

        $lockedAdmin->update([
            'wallet_balance' => $this->normalizeAmount((float) $lockedAdmin->wallet_balance - $amount),
        ]);

        return $lockedAdmin->fresh();
    }

    public function resolvePrimaryAdminAccount(): AdminAccount
    {
        $adminAccount = AdminAccount::query()->orderBy('adminId')->first();

        if ($adminAccount) {
            return $adminAccount;
        }

        $adminUser = User::query()
            ->where('role', 'admin')
            ->orderBy('id')
            ->firstOrFail();

        return $adminUser->adminAccount()->firstOrCreate(
            ['user_id' => $adminUser->id],
            [
                'username' => strtolower(strtok((string) $adminUser->email, '@') ?: 'admin' . $adminUser->id),
                'password' => $adminUser->password,
                'email' => $adminUser->email,
                'phone' => $adminUser->phone,
                'wallet_balance' => 0,
            ]
        );
    }

    private function normalizeAmount(float $amount): float
    {
        return round($amount, 2);
    }
}
