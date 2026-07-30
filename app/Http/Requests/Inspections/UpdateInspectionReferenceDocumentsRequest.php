<?php

declare(strict_types=1);

namespace App\Http\Requests\Inspections;

use App\Models\Inspection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateInspectionReferenceDocumentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $inspection = $this->route('inspection');

        return $inspection instanceof Inspection
            && ($this->user()?->can('manageReferences', $inspection) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $documentIds = $this->input('reference_document_ids', $this->input('document_ids', []));

        if (! is_array($documentIds)) {
            $documentIds = [];
        }

        $this->merge([
            'reference_document_ids' => collect($documentIds)
                ->filter(fn ($value) => filled($value))
                ->map(fn ($value) => (int) $value)
                ->unique()
                ->values()
                ->all(),
        ]);
    }

    public function rules(): array
    {
        return [
            'reference_document_ids' => ['array'],
            'reference_document_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('equipment_documents', 'id'),
            ],
        ];
    }
}
