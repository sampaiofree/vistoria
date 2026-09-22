<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Services\Reports\GeneralAspectsDocument;
use App\Support\TextNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use InvalidArgumentException;

final class GeneralAspectsTemplateRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('name')) {
            $this->merge(['name' => TextNormalizer::text((string) $this->input('name'))]);
        }
    }

    public function authorize(): bool
    {
        return $this->user()?->isCompanyAdmin() ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'schema_version' => ['required', 'integer', 'in:'.GeneralAspectsDocument::SCHEMA_VERSION],
            'document' => ['required', 'array'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            try {
                $document = app(GeneralAspectsDocument::class)->normalize(
                    (int) $this->input('schema_version'),
                    $this->input('document'),
                    allowPendingTextColor: true,
                );

                if ($document === null) {
                    $validator->errors()->add('document', 'O conteúdo do modelo não pode estar vazio.');
                }
            } catch (InvalidArgumentException $exception) {
                $validator->errors()->add('document', $exception->getMessage());
            }
        }];
    }
}
