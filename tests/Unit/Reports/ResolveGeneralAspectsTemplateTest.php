<?php

declare(strict_types=1);

namespace Tests\Unit\Reports;

use App\Services\Reports\EquipmentTemplateFields;
use App\Services\Reports\GeneralAspectsDocument;
use App\Services\Reports\ResolveGeneralAspectsTemplate;
use InvalidArgumentException;
use Tests\TestCase;

final class ResolveGeneralAspectsTemplateTest extends TestCase
{
    private ResolveGeneralAspectsTemplate $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new ResolveGeneralAspectsTemplate(new GeneralAspectsDocument, new EquipmentTemplateFields);
    }

    public function test_all_fields_resolve_from_snapshot_including_repeated_fields_and_multiline_text(): void
    {
        $fields = new EquipmentTemplateFields;
        $values = [];
        $content = [];
        foreach ($fields->options() as $option) {
            $values[$option['key']] = '00-'.$option['key'];
            $content[] = ['type' => 'equipmentField', 'attrs' => ['key' => $option['key']]];
        }
        $values['description'] = "Linha 1\nLinha 2 <&>";
        $content[] = ['type' => 'equipmentField', 'attrs' => ['key' => 'tag'], 'marks' => [['type' => 'bold']]];
        $document = ['type' => 'doc', 'content' => [[
            'type' => 'bulletList', 'content' => [[
                'type' => 'listItem', 'content' => [[
                    'type' => 'paragraph', 'content' => $content,
                ]],
            ]],
        ]]];

        $resolved = $this->resolver->resolve($document, $values);
        $inline = $resolved['content'][0]['content'][0]['content'][0]['content'];
        $this->assertSame('00-numero_cliente', $inline[0]['text']);
        $this->assertSame('00-defect_code_prefix', $inline[11]['text']);
        $this->assertSame('Linha 1', $inline[13]['text']);
        $this->assertSame(['type' => 'hardBreak'], $inline[14]);
        $this->assertSame('Linha 2 <&>', $inline[15]['text']);
        $this->assertSame('00-installation_location', $inline[17]['text']);
        $this->assertSame('00-tag', $inline[18]['text']);
        $this->assertSame([['type' => 'bold']], $inline[18]['marks']);
    }

    public function test_missing_values_are_pending_and_resolved_values_do_not_inherit_red(): void
    {
        $document = ['type' => 'doc', 'content' => [[
            'type' => 'heading', 'attrs' => ['level' => 1], 'content' => [
                ['type' => 'equipmentField', 'attrs' => ['key' => 'tag'], 'marks' => [
                    ['type' => 'bold'],
                    ['type' => 'textColor', 'attrs' => ['color' => '#DC2626']],
                ]],
                ['type' => 'equipmentField', 'attrs' => ['key' => 'defect_code_prefix']],
                ['type' => 'text', 'text' => 'Completar', 'marks' => [
                    ['type' => 'textColor', 'attrs' => ['color' => '#DC2626']],
                ]],
            ],
        ]]];
        $resolved = $this->resolver->resolve($document, ['tag' => '0', 'defect_code_prefix' => '   ']);
        $inline = $resolved['content'][0]['content'];
        $this->assertSame('0', $inline[0]['text']);
        $this->assertSame([['type' => 'bold']], $inline[0]['marks']);
        $this->assertSame('[Preencher: Prefixo de avaria]', $inline[1]['text']);
        $this->assertSame('#DC2626', $inline[1]['marks'][0]['attrs']['color']);
        $this->assertSame('#DC2626', $inline[2]['marks'][0]['attrs']['color']);
    }

    public function test_expanded_document_over_limit_is_rejected(): void
    {
        $document = ['type' => 'doc', 'content' => [[
            'type' => 'paragraph', 'content' => [[
                'type' => 'equipmentField', 'attrs' => ['key' => 'description'],
            ]],
        ]]];

        $this->expectException(InvalidArgumentException::class);
        $this->resolver->resolve($document, ['description' => str_repeat('a', 100_001)]);
    }
}
