<?php

declare(strict_types=1);

namespace App\Http\Requests\Inspections;

use App\Models\Inspection;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateInspectionResponsibleRequest extends FormRequest
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
            'is_primary' => $this->has('is_primary')
                ? $this->boolean('is_primary')
                : null,
            'completed_at' => blank($this->input('completed_at'))
                ? null
                : $this->input('completed_at'),
        ]);
    }

    public function rules(): array
    {
        return [
            'is_primary' => ['nullable', 'boolean'],
            'completed_at' => ['nullable', 'date'],
        ];
    }
}
