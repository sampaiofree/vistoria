<?php

namespace App\Http\Requests\EquipmentDocuments;

use App\Enums\DocumentStatus;
use App\Models\EquipmentDocument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateEquipmentDocumentStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $document = $this->route('equipmentDocument');

        return $document instanceof EquipmentDocument
            && ($this->user()?->can('updateStatus', $document) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'status' => is_string($this->input('status'))
                ? mb_strtolower(trim($this->input('status')))
                : $this->input('status'),
        ]);
    }

    public function rules(): array
    {
        return [
            'status' => [
                'required',
                Rule::enum(DocumentStatus::class),
            ],
        ];
    }
}
