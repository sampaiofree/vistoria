<?php

declare(strict_types=1);

namespace App\Http\Requests\Classification;

use App\Models\DefectClassification;
use App\Support\TextNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateDefectClassificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $classification = $this->route('defectClassification');

        return $classification instanceof DefectClassification
            && ($this->user()?->can('update', $classification) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $color = $this->input('color');

        $this->merge([
            'code' => TextNormalizer::technicalCode((string) $this->input('code')),
            'name' => TextNormalizer::text((string) $this->input('name')),
            'description' => TextNormalizer::nullableText($this->input('description')),
            'color' => TextNormalizer::hexColor(is_string($color) ? $color : null),
        ]);
    }

    public function rules(): array
    {
        $classification = $this->route('defectClassification');

        return [
            'code' => [
                'required', 'string', 'max:30', 'regex:/^[A-Z0-9]+(?:-[A-Z0-9]+)*$/',
                Rule::unique('defect_classifications', 'code')
                    ->where('organization_id', $this->user()?->organization_id)
                    ->where('defect_category_id', $classification?->defect_category_id)
                    ->ignore($classification?->getKey()),
            ],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:5000'],
            'color' => ['required', 'string', 'regex:/^#[0-9A-F]{6}$/'],
            'position' => ['nullable', 'integer', 'min:1'],
            'severity_rank' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'color.required' => 'Selecione uma cor para a classificação.',
            'color.regex' => 'Informe uma cor hexadecimal válida no formato #RRGGBB.',
        ];
    }
}
