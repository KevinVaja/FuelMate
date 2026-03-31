<?php

namespace App\Http\Requests;

use App\Models\AgentWithdrawal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminWithdrawalIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'status' => [
                'nullable',
                Rule::in([
                    AgentWithdrawal::STATUS_PENDING,
                    AgentWithdrawal::STATUS_APPROVED,
                    AgentWithdrawal::STATUS_REJECTED,
                    AgentWithdrawal::STATUS_COMPLETED,
                ]),
            ],
        ];
    }
}
