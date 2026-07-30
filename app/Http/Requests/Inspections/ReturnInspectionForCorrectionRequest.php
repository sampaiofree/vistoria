<?php

declare(strict_types=1);

namespace App\Http\Requests\Inspections;

use App\Models\Inspection;
use Illuminate\Foundation\Http\FormRequest;

final class ReturnInspectionForCorrectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $inspection = $this->route('inspection');

        return $inspection instanceof Inspection
            && ($this->user()?->can('returnForCorrection', $inspection) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'justification' => is_string($this->input('justification'))
                ? trim($this->input('justification'))
                : $this->input('justification'),
        ]);
    }

    public function rules(): array
    {
        return [
            'justification' => ['required', 'string', 'min:10', 'max:5000'],
        ];
    }
}
