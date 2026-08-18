<?php

declare(strict_types=1);

namespace App\Http\Requests\Classification;

use App\Enums\GutCriterion;
use App\Models\DefectCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateDefectCategoryGutRequest extends FormRequest
{
    public function authorize(): bool
    {
        $category = $this->route('defectCategory');

        return $category instanceof DefectCategory
            && ($this->user()?->can('update', $category) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $options = collect($this->input('gut_options', []))
            ->map(function (mixed $option): mixed {
                if (! is_array($option)) {
                    return $option;
                }

                if (isset($option['color'])) {
                    $option['color'] = strtoupper(trim((string) $option['color']));
                }

                return $option;
            })
            ->all();

        $this->merge(['gut_options' => $options]);
    }

    public function rules(): array
    {
        return [
            'gut_options' => ['present', 'array'],
            'gut_options.*' => ['required', 'array'],
            'gut_options.*.criterion' => ['required', Rule::enum(GutCriterion::class)],
            'gut_options.*.score' => ['required', 'integer', 'min:0', 'max:65535'],
            'gut_options.*.color' => ['required', 'string', 'regex:/^#[0-9A-F]{6}$/'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $seen = [];

            foreach ($this->input('gut_options', []) as $index => $option) {
                if (! is_array($option) || ! isset($option['criterion'], $option['score'])) {
                    continue;
                }

                $key = $option['criterion'].'|'.$option['score'];

                if (isset($seen[$key])) {
                    $validator->errors()->add(
                        "gut_options.{$index}.score",
                        'A nota já existe para este critério.',
                    );
                }

                $seen[$key] = true;
            }
        });
    }
}
