<?php

declare(strict_types=1);

namespace App\Http\Requests\Equipments;

use App\Models\Equipment;
use Illuminate\Foundation\Http\FormRequest;

final class PreviewEquipmentImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Equipment::class) ?? false;
    }

    public function rules(): array
    {
        return ['file' => ['required', 'file', 'mimes:csv,txt', 'max:10240']];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Selecione um arquivo CSV para importar.',
            'file.file' => 'Não foi possível ler o arquivo selecionado.',
            'file.mimes' => 'Envie um arquivo CSV válido.',
            'file.max' => 'O arquivo CSV deve ter no máximo 10 MB.',
            'file.uploaded' => 'Não foi possível enviar o arquivo CSV. Tente novamente.',
        ];
    }
}
