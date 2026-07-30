<?php

declare(strict_types=1);

namespace App\Http\Requests\Inspections;

use App\Enums\InspectionResponsibility;
use App\Enums\UserAccountType;
use App\Enums\UserStatus;
use App\Models\Inspection;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreInspectionResponsibleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $inspection = $this->route('inspection');

        return $inspection instanceof Inspection
            && ($this->user()?->can('assignResponsibles', $inspection) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'user_id' => blank($this->input('user_id'))
                ? $this->input('user_id')
                : (int) $this->input('user_id'),
            'responsibility' => is_string($this->input('responsibility'))
                ? mb_strtolower(trim($this->input('responsibility')))
                : $this->input('responsibility'),
            'is_primary' => $this->boolean('is_primary'),
        ]);
    }

    public function rules(): array
    {
        $organizationId = app(TenantContext::class)->id();

        return [
            'user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')
                    ->where(fn ($query) => $query
                        ->where('organization_id', $organizationId)
                        ->where('status', UserStatus::Active->value)
                        ->where('account_type', '!=', UserAccountType::SuperAdmin->value)),
            ],
            'responsibility' => [
                'required',
                Rule::enum(InspectionResponsibility::class),
            ],
            'is_primary' => ['sometimes', 'boolean'],
        ];
    }
}
