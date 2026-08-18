<?php

declare(strict_types=1);

namespace App\Http\Requests\Inspections;

use App\Models\Inspection;
use App\Services\Reports\GeneralAspectsDocument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use InvalidArgumentException;

final class UpdateGeneralAspectsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $inspection = $this->route('inspection');

        return $inspection instanceof Inspection
            && ($this->user()?->can('manageGeneralAspects', $inspection) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'schema_version' => ['required', 'integer', 'in:'.GeneralAspectsDocument::SCHEMA_VERSION],
            'document' => ['present', 'nullable', 'array'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            try {
                app(GeneralAspectsDocument::class)->normalize(
                    (int) $this->input('schema_version'),
                    $this->input('document'),
                );
            } catch (InvalidArgumentException $exception) {
                $validator->errors()->add('document', $exception->getMessage());
            }
        }];
    }
}
