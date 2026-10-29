<?php

namespace App\Http\Requests\Approval;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;

final class RejectApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(UserRole::BranchManager, UserRole::Admin) ?? false;
    }

    public function rules(): array
    {
        return [
            'note' => ['required', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'note.required' => 'Say why the charge is being rejected so the reversal is on the record.',
        ];
    }
}
