<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $documentRules = [
            Rule::requiredIf(fn () => $this->input('role', 'user') === 'agent'),
            'file',
            'mimes:jpg,jpeg,png,pdf',
            'max:2048',
        ];

        return [
            'name' => 'required|string|max:100',
            'email' => 'required|email|unique:users,email',
            'phone' => 'nullable|string|max:20',
            'password' => 'required|min:6|confirmed',
            'role' => 'nullable|in:user,agent',
            'petrol_license_photo' => $documentRules,
            'gst_certificate_photo' => $documentRules,
            'owner_id_proof_photo' => $documentRules,
        ];
    }

    public function messages(): array
    {
        return [
            'petrol_license_photo.required' => 'The petrol pump license or dealership certificate is required.',
            'gst_certificate_photo.required' => 'The GST certificate is required.',
            'owner_id_proof_photo.required' => 'The owner Aadhaar or PAN document is required.',
        ];
    }
}
