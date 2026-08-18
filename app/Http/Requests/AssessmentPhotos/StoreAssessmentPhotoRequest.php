<?php

declare(strict_types=1);

namespace App\Http\Requests\AssessmentPhotos;

use App\Enums\AssessmentPhotoType;
use App\Models\DefectAssessment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreAssessmentPhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $assessment = $this->route('defectAssessment');

        return $assessment instanceof DefectAssessment
            && ($this->user()?->can('uploadPhoto', $assessment) ?? false);
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:25600'],
            'photo_type' => ['nullable', Rule::enum(AssessmentPhotoType::class)],
            'caption' => ['nullable', 'string', 'max:500'],
            'captured_at' => ['nullable', 'date'],
        ];
    }
}
