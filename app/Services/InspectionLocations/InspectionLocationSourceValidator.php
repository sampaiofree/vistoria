<?php

declare(strict_types=1);

namespace App\Services\InspectionLocations;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

final class InspectionLocationSourceValidator
{
    /** @param array<string, mixed> $data */
    public function validate(UploadedFile $file, array $data): void
    {
        $dimensions = @getimagesize($file->getRealPath());
        if ($dimensions === false) {
            throw ValidationException::withMessages(['file' => 'A imagem enviada é inválida.']);
        }

        [$width, $height] = $dimensions;
        $maxDimension = (int) config('inspection_locations.limits.image_dimension');
        $maxPixels = (int) config('inspection_locations.limits.image_pixels');
        if ($width < 1 || $height < 1 || $width > $maxDimension || $height > $maxDimension || ($width * $height) > $maxPixels) {
            throw ValidationException::withMessages(['file' => 'A imagem excede o limite seguro de dimensões.']);
        }

        $crop = $data['source_crop'] ?? null;
        if (is_array($crop)
            && ((float) $crop['x'] + (float) $crop['width'] > 1
                || (float) $crop['y'] + (float) $crop['height'] > 1)) {
            throw ValidationException::withMessages(['source_crop' => 'O recorte deve permanecer dentro da imagem.']);
        }
    }
}
