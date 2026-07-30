<?php

namespace App\Http\Requests\Areas;

use App\Models\Area;
use App\Support\TextNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateAreaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $area = $this->route('area');

        return $area instanceof Area
            && ($this->user()?->can('update', $area) ?? false);
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
        /** @var Area|null $area */
        $area = $this->route('area');

        return [
            'name' => ['required', 'string', 'max:150'],
            'code' => [
                'nullable',
                'string',
                'max:80',
                Rule::unique('areas', 'normalized_code')
                    ->where(fn ($query) => $query
                        ->where('organization_id', $area?->organization_id)
                        ->where('client_unit_id', $area?->client_unit_id))
                    ->ignore($area?->getKey()),
            ],
            'description' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
