<?php

declare(strict_types=1);

namespace App\Services\Reports;

use InvalidArgumentException;

final class ResolveGeneralAspectsTemplate
{
    public function __construct(
        private readonly GeneralAspectsDocument $documents,
        private readonly EquipmentTemplateFields $fields,
    ) {}

    /** @param array<string, mixed> $template @param array<string, mixed> $snapshot @return array<string, mixed> */
    public function resolve(array $template, array $snapshot, int $schemaVersion = GeneralAspectsDocument::SCHEMA_VERSION): array
    {
        $validated = $this->documents->normalize(
            $schemaVersion,
            $template,
            allowPendingTextColor: true,
            allowEquipmentFields: true,
        );
        if ($validated === null) {
            throw new InvalidArgumentException('O modelo está vazio.');
        }

        $resolved = $this->resolveNode($validated['document'], $snapshot);
        $this->documents->normalize(
            $schemaVersion,
            $resolved,
            allowPendingTextColor: true,
        );

        return $resolved;
    }

    /** @param array<string, mixed> $node @param array<string, mixed> $snapshot @return array<string, mixed> */
    private function resolveNode(array $node, array $snapshot): array
    {
        if (! is_array($node['content'] ?? null)) {
            return $node;
        }

        $content = [];
        foreach ($node['content'] as $child) {
            if (($child['type'] ?? null) !== 'equipmentField') {
                $content[] = $this->resolveNode($child, $snapshot);

                continue;
            }

            $key = $child['attrs']['key'];
            $value = $snapshot[$key] ?? null;
            $missing = ! is_scalar($value) || trim((string) $value) === '';
            $text = $missing ? '[Preencher: '.$this->fields->label($key).']' : (string) $value;
            $marks = array_values(array_filter(
                $child['marks'] ?? [],
                fn (array $mark): bool => in_array($mark['type'], ['bold', 'italic'], true),
            ));
            if ($missing) {
                $marks[] = ['type' => 'textColor', 'attrs' => ['color' => GeneralAspectsDocument::PENDING_TEXT_COLOR]];
            }

            $lines = preg_split('/\R/u', $text) ?: [$text];
            foreach ($lines as $index => $line) {
                if ($index > 0) {
                    $content[] = ['type' => 'hardBreak'];
                }
                if ($line !== '') {
                    $textNode = ['type' => 'text', 'text' => $line];
                    if ($marks !== []) {
                        $textNode['marks'] = $marks;
                    }
                    $content[] = $textNode;
                }
            }
        }

        $node['content'] = $content;

        return $node;
    }
}
