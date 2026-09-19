<?php

declare(strict_types=1);

namespace App\Http\Requests\Equipments;

use App\Models\Equipment;
use Illuminate\Foundation\Http\FormRequest;

final class ConfirmEquipmentImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Equipment::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'max:64'],
            'mapping' => ['required', 'array'],
            'mapping.*' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'token.required' => 'A prévia de importação expirou. Envie o arquivo novamente.',
            'token.string' => 'A prévia de importação é inválida. Envie o arquivo novamente.',
            'token.max' => 'A prévia de importação é inválida. Envie o arquivo novamente.',
            'mapping.required' => 'Confira o mapeamento das colunas antes de confirmar.',
            'mapping.array' => 'O mapeamento das colunas é inválido. Envie o arquivo novamente.',
            'mapping.*.string' => 'Uma coluna selecionada no mapeamento é inválida. Envie o arquivo novamente.',
            'mapping.*.max' => 'Uma coluna selecionada no mapeamento é inválida. Envie o arquivo novamente.',
        ];
    }
}
