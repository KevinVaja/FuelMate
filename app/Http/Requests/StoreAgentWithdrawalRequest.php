<?php

namespace App\Http\Requests;

use App\Models\AgentWithdrawal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAgentWithdrawalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAgent() ?? false;
    }

    public function rules(): array
    {
        $minimum = (int) config('wallet.minimum_withdrawal_amount', 500);

        return [
            'amount' => ['required', 'numeric', 'min:' . $minimum],
            'payout_method' => ['required', Rule::in(config('wallet.payout_methods', [AgentWithdrawal::PAYOUT_METHOD_BANK, AgentWithdrawal::PAYOUT_METHOD_UPI]))],
            'account_holder_name' => [Rule::requiredIf(fn () => $this->input('payout_method') === AgentWithdrawal::PAYOUT_METHOD_BANK), 'nullable', 'string', 'max:255'],
            'account_number' => [Rule::requiredIf(fn () => $this->input('payout_method') === AgentWithdrawal::PAYOUT_METHOD_BANK), 'nullable', 'string', 'max:100'],
            'ifsc_code' => [Rule::requiredIf(fn () => $this->input('payout_method') === AgentWithdrawal::PAYOUT_METHOD_BANK), 'nullable', 'string', 'max:20'],
            'upi_id' => [Rule::requiredIf(fn () => $this->input('payout_method') === AgentWithdrawal::PAYOUT_METHOD_UPI), 'nullable', 'string', 'max:255'],
        ];
    }

    public function withdrawalData(): array
    {
        return $this->validated();
    }
}
