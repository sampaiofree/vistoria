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

        $this->merge(['links' => $links]);
    }

    public function rules(): array
    {
        return [
            'links' => ['required', 'array'],
            'links.*.category' => ['required', 'string', 'in:TAC,REC,CV,TEL'],
            'links.*.classification_code' => ['required', 'string', 'max:20'],
            'links.*.sap_number' => ['nullable', 'string', 'max:100'],
        ];
    }
}
