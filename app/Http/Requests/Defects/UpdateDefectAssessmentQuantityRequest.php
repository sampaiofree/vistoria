<?php

declare(strict_types=1);

namespace App\Http\Requests\Defects;

use App\Enums\DefectCategory;
use App\Enums\MeasurementUnit;
use App\Models\DefectAssessment;
use App\Services\Defects\CivilQuantityCalculator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateDefectAssessmentQuantityRequest extends FormRequest
{
    public function authorize(): bool
    {
        $assessment = $this->route('defectAssessment');

        return $assessment instanceof DefectAssessment
            && ($this->user()?->can('update', $assessment) ?? false);
    }

    public function rules(): array
    {
        if ($this->route('defectAssessment')->defect->category === DefectCategory::Civil) {
            return CivilQuantityCalculator::rules();
        }

        return [
            'quantity' => ['nullable', 'array:measurement_value,measurement_unit'],
            'quantity.measurement_value' => ['required_with:quantity', 'numeric', 'gt:0', 'max:999999999999'],
            'quantity.measurement_unit' => ['required_with:quantity', Rule::enum(MeasurementUnit::class)],
        ];
    }

    public function attributes(): array
    {
        return [
            'quantity.length' => 'comprimento',
            'quantity.height' => 'altura',
            'quantity.width' => 'largura',
            'quantity.quantity' => 'quantidade',
        ];
    }
}
