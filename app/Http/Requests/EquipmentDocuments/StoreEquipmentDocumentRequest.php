<?php

namespace App\Http\Requests\EquipmentDocuments;

use App\Enums\EquipmentDocumentType;
use App\Models\Equipment;
use App\Models\EquipmentDocument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

final class StoreEquipmentDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $equipment = $this->route('equipment');

        return $equipment instanceof Equipment
            && ($this->user()?->can('create', [EquipmentDocument::class, $equipment]) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'document_type' => is_string($this->input('document_type'))
                ? mb_strtolower(trim($this->input('document_type')))
                : $this->input('document_type'),
            'title' => is_string($this->input('title'))
                ? trim($this->input('title'))
                : $this->input('title'),
            'document_number' => is_string($this->input('document_number'))
                ? trim($this->input('document_number'))
                : $this->input('document_number'),
            'revision' => is_string($this->input('revision'))
                ? trim($this->input('revision'))
                : $this->input('revision'),
            'description' => is_string($this->input('description'))
                ? trim($this->input('description'))
                : $this->input('description'),
            'document_group' => is_string($this->input('document_group'))
                ? mb_strtoupper(trim($this->input('document_group')))
                : $this->input('document_group'),
        ]);
    }

    public function rules(): array
    {
        return [
            'document_type' => [
                'required',
                Rule::enum(EquipmentDocumentType::class),
            ],
            'title' => ['required', 'string', 'max:200'],
            'document_number' => ['nullable', 'string', 'max:150'],
            'revision' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:10000'],
            'issued_at' => ['nullable', 'date'],
            'document_group' => ['nullable', 'string', 'size:26'],
            'file' => [
                'required',
                File::types([
                    'pdf',
                    'xlsx',
                    'xlsm',
                    'doc',
                    'docx',
                    'png',
                    'jpg',
                    'jpeg',
                    'webp',
                ])->max(25 * 1024),
            ],
        ];
    }
}
