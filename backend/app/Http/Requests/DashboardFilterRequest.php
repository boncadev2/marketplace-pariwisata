<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DashboardFilterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && (
            $user->platform_role === 'super_admin'
            || $user->partnerMemberships()->where('is_active', true)->exists()
        );
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'timezone' => ['nullable', 'string', Rule::in(\DateTimeZone::listIdentifiers())],
            'partner_id' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', 'string', Rule::in([
                'pending_payment', 'expired', 'paid', 'payment_exception', 'cancelled', 'refunded',
            ])],
            'q' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
