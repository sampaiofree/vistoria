<?php

declare(strict_types=1);

namespace App\Http\Requests\Inspections;

use App\Models\Inspection;
use App\Support\TextNormalizer;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateInspectionClassificationM2LinksRequest extends FormRequest
{
    public function authorize(): bool
    {
        $inspection = $this->route('inspection');

        return $inspection instanceof Inspection
            && ($this->user()?->can('manageClassificationM2', $inspection) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $links = collect($this->input('links', []))->map(fn (mixed $link): array => [
            'category' => strtoupper(trim((string) data_get($link, 'category'))),
            'classification_code' => strtoupper(trim((string) data_get($link, 'classification_code'))),
            'sap_number' => TextNormalizer::nullableText(data_get($link, 'sap_number')),
        ])->all();
        $specialRows = collect($this->input('special_rows', []))->map(fn (mixed $row): array => [
            'assessment_public_id' => trim((string) data_get($row, 'assessment_public_id')),
            'service' => TextNormalizer::nullableText(data_get($row, 'service')),
            'priority' => TextNormalizer::nullableText(data_get($row, 'priority')),
            'note' => TextNormalizer::nullableText(data_get($row, 'note')),
        ])->all();

        $this->merge(['links' => $links, 'special_rows' => $specialRows]);
    }

    public function rules(): array
    {
        return [
            'links' => ['present', 'array'],
            'links.*.category' => ['required', 'string', 'in:TAC,REC,CV,TEL'],
            'links.*.classification_code' => ['required', 'string', 'max:20'],
            'links.*.sap_number' => ['nullable', 'string', 'size:8'],
            'special_rows' => ['sometimes', 'array'],
            'special_rows.*.assessment_public_id' => ['required', 'string', 'max:26', 'distinct'],
            'special_rows.*.service' => ['nullable', 'string', 'max:100'],
            'special_rows.*.priority' => ['nullable', 'string', 'max:100'],
            'special_rows.*.note' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'links.*.sap_number.size' => 'A Nota M2 deve ter exatamente 8 caracteres.',
        ];
    }
}
