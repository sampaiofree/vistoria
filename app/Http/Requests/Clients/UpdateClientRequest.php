<?php

namespace App\Http\Requests\Clients;

use App\Models\Client;
use App\Support\TextNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        $client = $this->route('client');

        return $client instanceof Client
            && ($this->user()?->can('update', $client) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => TextNormalizer::text((string) $this->input('name')),
            'legal_name' => TextNormalizer::nullableText($this->input('legal_name')),
            'document' => TextNormalizer::document($this->input('document')),
            'email' => TextNormalizer::email($this->input('email')),
            'phone' => TextNormalizer::nullableText($this->input('phone')),
            'notes' => TextNormalizer::nullableText($this->input('notes')),
        ]);
    }

    public function rules(): array
    {
        /** @var Client|null $client */
        $client = $this->route('client');
        $organizationId = $this->user()?->organization_id;

        return [
            'name' => ['required', 'string', 'max:150'],
            'legal_name' => ['nullable', 'string', 'max:200'],
            'document' => [
                'nullable',
                'string',
                'max:20',
                Rule::unique('clients', 'document')
                    ->where('organization_id', $organizationId)
                    ->ignore($client?->getKey()),
            ],
            'email' => ['nullable', 'email:rfc', 'max:254'],
            'phone' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
