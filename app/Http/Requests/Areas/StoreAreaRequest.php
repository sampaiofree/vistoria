<?php

namespace App\Http\Requests\Areas;

use App\Models\Area;
use App\Models\ClientUnit;
use App\Support\TextNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreAreaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $unit = $this->route('unit');

        return $unit instanceof ClientUnit
            && $unit->organization_id === $this->user()?->organization_id
            && ($this->user()?->can('create', Area::class) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => TextNormalizer::text((string) $this->input('name')),
            'code' => TextNormalizer::technicalCode($this->input('code')),
            'description' => TextNormalizer::nullableText($this->input('description')),
        ]);
    }

    public function rules(): array
    {
        /** @var ClientUnit|null $unit */
        $unit = $this->route('unit');

        return [
            'name' => ['required', 'string', 'max:150'],
            'code' => [
                'nullable',
                'string',
                'max:80',
                Rule::unique('areas', 'normalized_code')
                    ->where(fn ($query) => $query
                        ->where('organization_id', $unit?->organization_id)
                        ->where('client_unit_id', $unit?->getKey())),
            ],
            'description' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
