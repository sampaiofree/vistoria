<?php

namespace App\Http\Requests\Account;

use App\Support\TextNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['name' => TextNormalizer::text((string) $this->input('name'))]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'photo' => [
                'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048',
                Rule::dimensions()->maxWidth(4096)->maxHeight(4096),
                static function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! $value instanceof \Illuminate\Http\UploadedFile) {
                        return;
                    }

                    $dimensions = @getimagesize($value->getRealPath());
                    if ($dimensions === false || $dimensions[0] * $dimensions[1] > 16_000_000) {
                        $fail('A foto excede os limites permitidos.');
                    }
                },
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach (array_diff(array_keys($this->all()), ['_token', '_method', 'name', 'photo']) as $field) {
                $validator->errors()->add($field, 'Este campo não pode ser alterado no perfil.');
            }
        });
    }
}
