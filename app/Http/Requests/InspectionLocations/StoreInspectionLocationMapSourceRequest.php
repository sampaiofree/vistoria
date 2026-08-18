<?php

declare(strict_types=1);

namespace App\Http\Requests\InspectionLocations;

use App\Models\InspectionLocationMap;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

final class StoreInspectionLocationMapSourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $map = $this->route('map');

        return $map instanceof InspectionLocationMap
            && ($this->user()?->can('update', $map) ?? false);
    }

    public function rules(): array
    {
        return [
            'file' => ['required', File::types(['png', 'jpg', 'jpeg', 'webp'])->max((int) config('inspection_locations.limits.source_size_kilobytes'))],
            'lock_version' => ['required', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Selecione uma imagem do mapa.',
            'file.mimes' => 'Envie uma imagem PNG, JPEG ou WEBP.',
            'file.mimetypes' => 'Envie uma imagem PNG, JPEG ou WEBP.',
            'file.max' => 'A imagem deve ter no máximo 50 MB.',
            'lock_version.required' => 'Recarregue a página antes de enviar uma nova imagem.',
        ];
    }
}
