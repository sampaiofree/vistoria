<?php

declare(strict_types=1);

namespace App\Http\Requests\Defects;

use App\Models\DefectAssessment;
use App\Models\DefectAssessmentQuantity;
use App\Services\Defects\NativeDefectQuantityCalculator;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateDefectAssessmentQuantityRequest extends FormRequest
{
    public function authorize(): bool
    {
        $assessment = $this->assessment();

        return $assessment instanceof DefectAssessment
            && ($this->user()?->can('update', $assessment) ?? false);
    }

    public function rules(): array
    {
        $assessment = $this->assessment();
        abort_unless($assessment instanceof DefectAssessment, 404);

        $rules = NativeDefectQuantityCalculator::rules(
            $assessment->defect->category,
            $this->input('quantity.element'),
        );
        $rules['quantity'] = collect($rules['quantity'])
            ->reject(fn (mixed $rule): bool => $rule === 'nullable')
            ->prepend('required')
            ->values()
            ->all();
        $rules['description'] = ['nullable', 'string', 'max:180'];

        return $rules;
    }

    public function attributes(): array
    {
        return [
            'quantity.length' => 'comprimento',
            'quantity.height' => 'altura',
            'quantity.width' => 'largura',
            'quantity.quantity' => 'quantidade',
            'quantity.area' => 'área',
            'quantity.element' => 'elemento REC',
            'quantity.flange_width' => 'mesa',
            'quantity.flange_thickness' => 'espessura da mesa',
            'quantity.web_height' => 'alma',
            'quantity.web_thickness' => 'espessura da alma',
            'quantity.thickness' => 'espessura',
            'quantity.fold_width' => 'dobra da mesa',
            'quantity.outer_diameter' => 'diâmetro externo',
            'quantity.leg_1' => 'aba 1',
            'quantity.leg_2' => 'aba 2',
            'quantity.side_1' => 'aba 1',
            'quantity.side_2' => 'aba 2',
            'quantity.total_weight' => 'peso total',
        ];
    }

    private function assessment(): ?DefectAssessment
    {
        $assessment = $this->route('defectAssessment');
        if ($assessment instanceof DefectAssessment) {
            return $assessment->loadMissing('defect');
        }

        $quantity = $this->route('defectAssessmentQuantity');

        return $quantity instanceof DefectAssessmentQuantity
            ? $quantity->assessment()->with('defect')->first()
            : null;
    }
}
