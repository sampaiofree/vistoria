<?php

declare(strict_types=1);

namespace Tests\Feature\Defects;

use Tests\TestCase;

final class DefectAssessmentGutPreviewTest extends TestCase
{
    public function test_automatic_gut_preview_uses_the_classification_color_on_its_badge(): void
    {
        $source = file_get_contents(resource_path('js/pages/DefectAssessments/Show.vue'));

        $this->assertStringContainsString('v-if="previewClassification"', $source);
        $this->assertStringContainsString('backgroundColor: previewClassification.color', $source);
        $this->assertStringContainsString('{{ previewClassification.code }}', $source);
    }

    public function test_saving_gut_refreshes_the_location_map_preview_data(): void
    {
        $source = file_get_contents(resource_path('js/pages/DefectAssessments/Show.vue'));
        $saveGutStart = strpos($source, 'function saveGut()');
        $saveGutEnd = strpos($source, 'function saveTel()', $saveGutStart);
        $saveGut = substr($source, $saveGutStart, $saveGutEnd - $saveGutStart);

        $this->assertStringContainsString("'location_map'", $saveGut);
    }
}
