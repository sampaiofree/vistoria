<?php

declare(strict_types=1);

namespace App\Services\Defects;

use App\Enums\MeasurementUnit;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class CivilQuantityCalculator
{
    public static function rules(): array
    {
        $rules = ['quantity' => ['nullable', 'array:length,height,width,quantity', 'required_array_keys:length,height,width,quantity']];

        foreach (['length', 'height', 'width', 'quantity'] as $field) {
            $rules['quantity.'.$field] = ['required_with:quantity', 'numeric', 'decimal:0,4', 'gt:0', 'max:9999999999.9999'];
        }

        return $rules;
    }

    /** @return array<string, string|MeasurementUnit> */
    public function calculate(array $data): array
    {
        Validator::make(['quantity' => $data], self::rules())->validate();

        $length = BigDecimal::of((string) $data['length']);
        $height = BigDecimal::of((string) $data['height']);
        $width = BigDecimal::of((string) $data['width']);
        $quantity = BigDecimal::of((string) $data['quantity']);
        $unitVolume = $length->multipliedBy($height)->multipliedBy($width);
        $total = $unitVolume->multipliedBy($quantity);

        if ($total->isGreaterThanOrEqualTo('1000000000000')) {
            throw ValidationException::withMessages([
                'quantity' => 'O volume total deve ser menor que 1.000.000.000.000 m³.',
            ]);
        }

        return [
            'length' => (string) $length->toScale(4),
            'height' => (string) $height->toScale(4),
            'width' => (string) $width->toScale(4),
            'quantity' => (string) $quantity->toScale(4),
            'unit_volume' => (string) $unitVolume->toScale(12),
            'measurement_value' => (string) $total->toScale(16),
            'measurement_unit' => MeasurementUnit::CubicMeter,
        ];
    }
}
