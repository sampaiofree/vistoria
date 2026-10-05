<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Services\Tenancy\TenantContext;
use App\Support\TextNormalizer;
use Illuminate\Foundation\Http\FormRequest;

final class ReportResponsiblesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', app(TenantContext::class)->organization()) ?? false;
    }

    protected function prepareForValidation(): void
    {
        foreach (['report_verifier_name', 'report_reviewer_name', 'report_releaser_name'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => TextNormalizer::nullableText($this->input($field))]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'report_verifier_name' => ['present', 'nullable', 'string', 'max:150'],
            'report_reviewer_name' => ['present', 'nullable', 'string', 'max:150'],
            'report_releaser_name' => ['present', 'nullable', 'string', 'max:150'],
        ];
    }

    public function attributes(): array
    {
        return [
            'report_verifier_name' => 'nome do Verificador',
            'report_reviewer_name' => 'nome do Revisor',
            'report_releaser_name' => 'nome do Liberador',
        ];
    }
}
