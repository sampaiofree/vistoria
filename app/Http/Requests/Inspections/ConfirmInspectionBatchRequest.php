<?php

declare(strict_types=1);

namespace App\Http\Requests\Inspections;

use App\Models\Inspection;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;

final class ConfirmInspectionBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Inspection::class) ?? false;
    }

    protected function failedAuthorization(): void
    {
        throw new AuthorizationException('Somente usuários ativos com papel Planejador podem criar inspeções.');
    }

    public function rules(): array
    {
        return ['token' => ['required', 'uuid']];
    }
}
