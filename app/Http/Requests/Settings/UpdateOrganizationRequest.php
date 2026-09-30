<?php

namespace App\Http\Requests\Settings;

use App\Models\Organization;
use App\Rules\ValidCnpj;
use App\Support\TextNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

final class UpdateOrganizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $organization = $this->user()?->organization;

        return $organization instanceof Organization
            && ($this->user()?->can('update', $organization) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => TextNormalizer::text((string) $this->input('name')),
            'legal_name' => TextNormalizer::nullableText($this->input('legal_name')),
            'document' => TextNormalizer::document($this->input('document')),
            'primary_color' => TextNormalizer::hexColor(
                is_string($this->input('primary_color')) ? $this->input('primary_color') : null,
            ),
        ]);
    }

    public function rules(): array
    {
        $organization = $this->user()?->organization;
        $maxIconSide = min(
            (int) config('photos.limits.max_dimension'),
            (int) floor(sqrt((int) config('photos.limits.max_pixels'))),
        );

        return [
            'name' => ['required', 'string', 'max:150'],
            'legal_name' => ['nullable', 'string', 'max:200'],
            'document' => [
                'nullable', 'digits:14', new ValidCnpj,
                Rule::unique('organizations', 'document')->ignore($organization?->getKey()),
            ],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'primary_color' => ['required', 'regex:/^#[0-9A-F]{6}$/'],
            'icon' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'pwa_icon' => [
                'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048',
                Rule::dimensions()->maxWidth($maxIconSide)->maxHeight($maxIconSide),
                static function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! $value instanceof UploadedFile) {
                        return;
                    }

                    $dimensions = @getimagesize($value->getRealPath());
                    if ($dimensions !== false && $dimensions[0] !== $dimensions[1]) {
                        $fail('A imagem do ícone do aplicativo deve ter proporção 1:1.');
                    }
                },
            ],
        ];
    }
}
