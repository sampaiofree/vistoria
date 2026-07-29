<?php

namespace App\Http\Requests\ClientUnits;

use App\Models\Client;
use App\Support\TextNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreClientUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => TextNormalizer::text((string) $this->input('name')),
            'code' => TextNormalizer::technicalCode($this->input('code')),
            'timezone' => TextNormalizer::nullableText($this->input('timezone')),
            'address_line' => TextNormalizer::nullableText($this->input('address_line')),
            'address_number' => TextNormalizer::nullableText($this->input('address_number')),
            'district' => TextNormalizer::nullableText($this->input('district')),
            'postal_code' => TextNormalizer::document($this->input('postal_code')),
            'city' => TextNormalizer::nullableText($this->input('city')),
            'state' => TextNormalizer::nullableText($this->input('state')),
            'country_code' => mb_strtoupper((string) $this->input('country_code', 'BR')),
            'notes' => TextNormalizer::nullableText($this->input('notes')),
        ]);
    }

    public function rules(): array
    {
        /** @var Client|null $client */
        $client = $this->route('client');

        return [
            'name' => ['required', 'string', 'max:150'],
            'code' => ['nullable', 'string', 'max:80'],
            'timezone' => ['nullable', 'timezone'],
            'address_line' => ['nullable', 'string', 'max:200'],
            'address_number' => ['nullable', 'string', 'max:30'],
            'district' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'country_code' => ['required', 'string', 'size:2'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'code' => [
                'nullable',
                'string',
                'max:80',
                Rule::unique('client_units', 'normalized_code')
                    ->where(fn ($query) => $query
                        ->where('organization_id', $client?->organization_id)
                        ->where('client_id', $client?->getKey())),
            ],
        ];
    }
}
