<?php

declare(strict_types=1);

namespace App\Http\Requests\InspectionLocations;

use App\Models\DefectAssessment;
use App\Support\TextNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

final class StoreDefectLocationMapRequest extends FormRequest
{
    public function authorize(): bool
    {
        $assessment = $this->route('defectAssessment');

        return $assessment instanceof DefectAssessment
            && ($this->user()?->can('update', $assessment) ?? false);
    }

    public function rules(): array
    {
        return [
            'file' => ['required', File::types(['png', 'jpg', 'jpeg', 'webp'])->max((int) config('inspection_locations.limits.source_size_kilobytes'))],
            'project_number' => ['required', 'string', 'max:150'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'project_number' => TextNormalizer::nullableText($this->input('project_number')),
        ]);
    }
}
