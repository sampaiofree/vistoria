<?php

declare(strict_types=1);

namespace App\Http\Requests\Inspections;

use App\Models\Inspection;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateInspectionClassificationHeaderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $inspection = $this->route('inspection');

        return $inspection instanceof Inspection
            && ($this->user()?->can('manageClassificationM2', $inspection) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'inspected_on' => blank($this->input('inspected_on')) ? null : $this->input('inspected_on'),
        ]);
    }

    public function rules(): array
    {
        return [
            'general_drawing' => ['missing'],
            'procedure_number' => ['missing'],
            'inspected_on' => ['nullable', 'date'],
        ];
    }
}
