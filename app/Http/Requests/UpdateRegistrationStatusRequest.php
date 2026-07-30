<?php

namespace App\Http\Requests;

use App\Enums\RegistrationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateRegistrationStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        foreach (['client', 'unit', 'area', 'subarea'] as $parameter) {
            $resource = $this->route($parameter);

            if ($resource !== null) {
                return $this->user()?->can('changeStatus', $resource) ?? false;
            }
        }

        return false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'status' => is_string($this->input('status'))
                ? mb_strtolower(trim($this->input('status')))
                : $this->input('status'),
        ]);
    }

    public function rules(): array
    {
        return [
            'status' => [
                'required',
                Rule::enum(RegistrationStatus::class),
            ],
        ];
    }
}
