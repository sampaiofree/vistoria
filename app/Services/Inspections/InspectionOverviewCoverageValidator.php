<?php

declare(strict_types=1);

namespace App\Services\Inspections;

use App\Enums\PhotoProcessingStatus;
use App\Models\Inspection;
use App\Models\InspectionOverviewPhoto;
use Illuminate\Validation\ValidationException;

final class InspectionOverviewCoverageValidator
{
    public function validate(Inspection $inspection): void
    {
        $inspection->loadMissing(['overviewBlocks.photos']);
        $blocks = $inspection->overviewBlocks->keyBy('position');
        $missingPhotos = [];
        $unreadyPhotos = [];
        $missingTexts = [];

        foreach ([1, 2] as $position) {
            $block = $blocks->get($position);

            if (blank($block?->comment)) {
                $missingTexts[] = "comentário do bloco {$position}";
            }

            if (blank($block?->recommendation)) {
                $missingTexts[] = "recomendação do bloco {$position}";
            }

            $photos = $block?->photos->keyBy('slot') ?? collect();

            foreach ([1, 2] as $slot) {
                $number = (($position - 1) * 2) + $slot;
                $photo = $photos->get($slot);

                if (! $photo instanceof InspectionOverviewPhoto) {
                    $missingPhotos[] = $number;

                    continue;
                }

                if ($photo->processing_status !== PhotoProcessingStatus::Ready) {
                    $unreadyPhotos[] = $number;
                }
            }
        }

        $messages = [];

        if ($missingPhotos !== []) {
            $messages[] = 'Adicione as fotografias da Vista geral nos slots: '.implode(', ', $missingPhotos).'.';
        }

        if ($unreadyPhotos !== []) {
            $messages[] = 'Aguarde o processamento ou substitua as fotografias da Vista geral nos slots: '.implode(', ', $unreadyPhotos).'.';
        }

        if ($missingTexts !== []) {
            $messages[] = 'Preencha os seguintes campos da Vista geral: '.implode(', ', $missingTexts).'.';
        }

        if ($messages !== []) {
            throw ValidationException::withMessages(['inspection' => $messages]);
        }
    }
}
