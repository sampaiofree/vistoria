<?php

declare(strict_types=1);

namespace App\Services\Reports;

use InvalidArgumentException;
use JsonException;

final class GeneralAspectsDocument
{
    public const SCHEMA_VERSION = 1;

    public const MAX_VISIBLE_CHARACTERS = 100_000;

    public const MAX_JSON_BYTES = 1_048_576;

    public const PENDING_TEXT_COLOR = '#DC2626';

    /** @var array<int, string> */
    private const BLOCK_NODES = ['paragraph', 'heading', 'bulletList', 'orderedList'];

    /** @var array<int, string> */
    private const ALIGNMENTS = ['left', 'center', 'right', 'justify'];

    /** @var array<int, float> */
    private const LINE_HEIGHTS = [1.0, 1.15, 1.5, 2.0];

    /** @var array<int, int> */
    private const PARAGRAPH_SPACING = [0, 4, 8, 12];

    /** @var array<int, int> */
    private const INDENTS = [0, 10, 20, 30];

    /** @return array{type:string, content:array<int, mixed>} */
    public function emptyDocument(): array
    {
        return [
            'type' => 'doc',
            'content' => [['type' => 'paragraph']],
        ];
    }

    /**
     * @return array{schema_version:int, document:array<string, mixed>}|null
     */
    public function fromStored(?string $stored): ?array
    {
        if ($stored === null || trim($stored) === '') {
            return null;
        }

        try {
            $decoded = json_decode($stored, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $this->fromPlainText($stored);
        }

        if (! is_array($decoded)
            || ($decoded['schema_version'] ?? null) !== self::SCHEMA_VERSION
            || ! is_array($decoded['document'] ?? null)) {
            return $this->fromPlainText($stored);
        }

        try {
            return $this->normalize(self::SCHEMA_VERSION, $decoded['document']);
        } catch (InvalidArgumentException) {
            return $this->fromPlainText($stored);
        }
    }

    /**
     * @return array{schema_version:int, document:array<string, mixed>}|null
     */
    public function fromPlainText(?string $text): ?array
    {
        if ($text === null || trim($text) === '') {
            return null;
        }

        $lines = preg_split('/\R/u', $text) ?: [$text];
        $content = array_map(function (string $line): array {
            if ($line === '') {
                return ['type' => 'paragraph'];
            }

            return [
                'type' => 'paragraph',
                'content' => [['type' => 'text', 'text' => $line]],
            ];
        }, $lines);

        return [
            'schema_version' => self::SCHEMA_VERSION,
            'document' => ['type' => 'doc', 'content' => $content],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $document
     * @return array{schema_version:int, document:array<string, mixed>}|null
     */
    public function normalize(int $schemaVersion, ?array $document, bool $allowPendingTextColor = false): ?array
    {
        if ($schemaVersion !== self::SCHEMA_VERSION) {
            throw new InvalidArgumentException('A versão do documento de aspectos gerais não é suportada.');
        }

        if ($document === null) {
            return null;
        }

        $this->assertJsonSize(['schema_version' => $schemaVersion, 'document' => $document]);

        if (($document['type'] ?? null) !== 'doc') {
            throw new InvalidArgumentException('O conteúdo precisa ser um documento válido.');
        }

        $this->assertOnlyKeys($document, ['type', 'content']);
        $blocks = $document['content'] ?? [];
        if (! is_array($blocks)) {
            throw new InvalidArgumentException('Os blocos do documento são inválidos.');
        }

        $visibleCharacters = 0;
        $normalizedBlocks = [];
        foreach ($blocks as $block) {
            if (! is_array($block)) {
                throw new InvalidArgumentException('Um dos blocos do documento é inválido.');
            }

            $normalizedBlocks[] = $this->normalizeBlock($block, $visibleCharacters, 0, $allowPendingTextColor);
        }

        if ($visibleCharacters > self::MAX_VISIBLE_CHARACTERS) {
            throw new InvalidArgumentException('O texto dos aspectos gerais deve ter no máximo 100.000 caracteres.');
        }

        if ($visibleCharacters === 0) {
            return null;
        }

        $normalized = [
            'schema_version' => self::SCHEMA_VERSION,
            'document' => ['type' => 'doc', 'content' => $normalizedBlocks],
        ];

        $this->assertJsonSize($normalized);

        return $normalized;
    }

    /**
     * Canonicalize legacy heading levels only when a document is explicitly saved.
     *
     * @param  array<string, mixed>|null  $document
     * @return array<string, mixed>|null
     */
    public function withFlatHeadings(?array $document): ?array
    {
        if ($document === null) {
            return null;
        }

        return $this->flattenHeadingNode($document);
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private function flattenHeadingNode(array $node): array
    {
        if (($node['type'] ?? null) === 'heading'
            && (! array_key_exists('attrs', $node) || is_array($node['attrs']))) {
            $node['attrs'] = array_merge($node['attrs'] ?? [], ['level' => 1]);
        }

        if (is_array($node['content'] ?? null)) {
            $node['content'] = array_map(
                fn (mixed $child): mixed => is_array($child) ? $this->flattenHeadingNode($child) : $child,
                $node['content'],
            );
        }

        return $node;
    }

    /** @param array<string, mixed> $node */
    private function normalizeBlock(array $node, int &$visibleCharacters, int $listDepth, bool $allowPendingTextColor): array
    {
        $type = $node['type'] ?? null;
        if (! is_string($type) || ! in_array($type, self::BLOCK_NODES, true)) {
            throw new InvalidArgumentException('O documento contém um bloco não permitido.');
        }

        return match ($type) {
            'paragraph' => $this->normalizeTextBlock($node, $visibleCharacters, false, $allowPendingTextColor),
            'heading' => $this->normalizeTextBlock($node, $visibleCharacters, true, $allowPendingTextColor),
            'bulletList', 'orderedList' => $this->normalizeList($node, $visibleCharacters, $listDepth, $allowPendingTextColor),
        };
    }

    /** @param array<string, mixed> $node */
    private function normalizeTextBlock(array $node, int &$visibleCharacters, bool $heading, bool $allowPendingTextColor): array
    {
        $this->assertOnlyKeys($node, ['type', 'attrs', 'content']);
        $attributes = $this->normalizeLayoutAttributes($node['attrs'] ?? [], $heading);
        $content = $node['content'] ?? [];
        if (! is_array($content)) {
            throw new InvalidArgumentException('O conteúdo de um parágrafo é inválido.');
        }

        $normalized = ['type' => $heading ? 'heading' : 'paragraph'];
        if ($attributes !== []) {
            $normalized['attrs'] = $attributes;
        }

        $normalizedContent = [];
        foreach ($content as $inlineNode) {
            if (! is_array($inlineNode)) {
                throw new InvalidArgumentException('O documento contém texto inválido.');
            }

            $normalizedContent[] = $this->normalizeInlineNode($inlineNode, $visibleCharacters, $allowPendingTextColor);
        }

        if ($normalizedContent !== []) {
            $normalized['content'] = $normalizedContent;
        }

        return $normalized;
    }

    /** @param array<string, mixed> $node */
    private function normalizeList(array $node, int &$visibleCharacters, int $listDepth, bool $allowPendingTextColor): array
    {
        if ($listDepth >= 6) {
            throw new InvalidArgumentException('As listas podem ter no máximo seis níveis.');
        }

        $ordered = $node['type'] === 'orderedList';
        $this->assertOnlyKeys($node, ['type', 'attrs', 'content']);
        $attributes = $node['attrs'] ?? [];
        if (! is_array($attributes)) {
            throw new InvalidArgumentException('Os atributos da lista são inválidos.');
        }

        $normalized = ['type' => $node['type']];
        if ($ordered) {
            $this->assertOnlyKeys($attributes, ['start', 'type']);
            if (($attributes['type'] ?? null) !== null) {
                throw new InvalidArgumentException('O estilo da lista numerada não é permitido.');
            }
            $start = $attributes['start'] ?? 1;
            if (! is_int($start) || $start < 1 || $start > 9999) {
                throw new InvalidArgumentException('O início da lista numerada é inválido.');
            }
            if ($start !== 1) {
                $normalized['attrs'] = ['start' => $start];
            }
        } elseif ($attributes !== []) {
            throw new InvalidArgumentException('A lista contém atributos não permitidos.');
        }

        $items = $node['content'] ?? [];
        if (! is_array($items) || $items === []) {
            throw new InvalidArgumentException('A lista precisa possuir ao menos um item.');
        }

        $normalized['content'] = [];
        foreach ($items as $item) {
            if (! is_array($item) || ($item['type'] ?? null) !== 'listItem') {
                throw new InvalidArgumentException('Um dos itens da lista é inválido.');
            }

            $this->assertOnlyKeys($item, ['type', 'content']);
            $children = $item['content'] ?? [];
            if (! is_array($children) || $children === []) {
                throw new InvalidArgumentException('Um item da lista não pode estar vazio.');
            }

            $normalizedChildren = [];
            foreach ($children as $index => $child) {
                if (! is_array($child)) {
                    throw new InvalidArgumentException('O conteúdo de um item da lista é inválido.');
                }

                if ($index === 0 && ($child['type'] ?? null) !== 'paragraph') {
                    throw new InvalidArgumentException('Um item da lista deve começar por um parágrafo.');
                }

                $normalizedChildren[] = $this->normalizeBlock($child, $visibleCharacters, $listDepth + 1, $allowPendingTextColor);
            }

            $normalized['content'][] = ['type' => 'listItem', 'content' => $normalizedChildren];
        }

        return $normalized;
    }

    /** @param array<string, mixed> $node */
    private function normalizeInlineNode(array $node, int &$visibleCharacters, bool $allowPendingTextColor): array
    {
        $type = $node['type'] ?? null;
        if ($type === 'hardBreak') {
            $this->assertOnlyKeys($node, ['type']);

            return ['type' => 'hardBreak'];
        }

        if ($type !== 'text' || ! is_string($node['text'] ?? null)) {
            throw new InvalidArgumentException('O documento contém um elemento de texto não permitido.');
        }

        $this->assertOnlyKeys($node, ['type', 'text', 'marks']);
        $visibleCharacters += mb_strlen($node['text']);
        $normalized = ['type' => 'text', 'text' => $node['text']];
        $marks = $node['marks'] ?? [];
        if (! is_array($marks)) {
            throw new InvalidArgumentException('A formatação do texto é inválida.');
        }

        $normalizedMarks = [];
        foreach ($marks as $mark) {
            if (! is_array($mark)) {
                throw new InvalidArgumentException('O documento contém uma formatação não permitida.');
            }

            if (in_array($mark['type'] ?? null, ['bold', 'italic'], true)) {
                $this->assertOnlyKeys($mark, ['type']);
                $normalizedMarks[] = ['type' => $mark['type']];

                continue;
            }

            if (($mark['type'] ?? null) !== 'textColor') {
                throw new InvalidArgumentException('O documento contém uma formatação não permitida.');
            }

            $this->assertOnlyKeys($mark, ['type', 'attrs']);
            $attributes = $mark['attrs'] ?? null;
            if (! is_array($attributes)) {
                throw new InvalidArgumentException('A cor do texto é inválida.');
            }
            $this->assertOnlyKeys($attributes, ['color']);

            if (($attributes['color'] ?? null) !== self::PENDING_TEXT_COLOR) {
                throw new InvalidArgumentException('Apenas a cor vermelha é permitida nos modelos de aspectos gerais.');
            }
            if (! $allowPendingTextColor) {
                throw new InvalidArgumentException('Conclua todos os trechos destacados em vermelho antes de salvar os aspectos gerais.');
            }

            $normalizedMarks[] = [
                'type' => 'textColor',
                'attrs' => ['color' => self::PENDING_TEXT_COLOR],
            ];
        }

        if ($normalizedMarks !== []) {
            $normalized['marks'] = $normalizedMarks;
        }

        return $normalized;
    }

    /** @return array<string, mixed> */
    private function normalizeLayoutAttributes(mixed $attributes, bool $heading): array
    {
        if (! is_array($attributes)) {
            throw new InvalidArgumentException('Os atributos do parágrafo são inválidos.');
        }

        $allowed = ['textAlign', 'lineHeight', 'spaceBefore', 'spaceAfter', 'indent'];
        if ($heading) {
            $allowed[] = 'level';
        }
        $this->assertOnlyKeys($attributes, $allowed);

        $normalized = [];
        if ($heading) {
            $level = $attributes['level'] ?? 1;
            if (! is_int($level) || $level < 1 || $level > 3) {
                throw new InvalidArgumentException('O nível do título é inválido.');
            }
            $normalized['level'] = $level;
        }

        $alignment = $attributes['textAlign'] ?? 'left';
        $alignment = $alignment === null ? 'left' : $alignment;
        if (! is_string($alignment) || ! in_array($alignment, self::ALIGNMENTS, true)) {
            throw new InvalidArgumentException('O alinhamento do parágrafo é inválido.');
        }
        if ($alignment !== 'left') {
            $normalized['textAlign'] = $alignment;
        }

        $lineHeight = $attributes['lineHeight'] ?? 1.15;
        $lineHeight = (float) ($lineHeight === null ? 1.15 : $lineHeight);
        if (! in_array($lineHeight, self::LINE_HEIGHTS, true)) {
            throw new InvalidArgumentException('O espaçamento entre linhas é inválido.');
        }
        if ($lineHeight !== 1.15) {
            $normalized['lineHeight'] = $lineHeight;
        }

        foreach (['spaceBefore', 'spaceAfter'] as $key) {
            $value = $attributes[$key] ?? 0;
            $value = $value === null ? 0 : $value;
            if (! is_int($value) || ! in_array($value, self::PARAGRAPH_SPACING, true)) {
                throw new InvalidArgumentException('O espaçamento do parágrafo é inválido.');
            }
            if ($value !== 0) {
                $normalized[$key] = $value;
            }
        }

        $indent = $attributes['indent'] ?? 0;
        $indent = $indent === null ? 0 : $indent;
        if (! is_int($indent) || ! in_array($indent, self::INDENTS, true)) {
            throw new InvalidArgumentException('O recuo do parágrafo é inválido.');
        }
        if ($indent !== 0) {
            $normalized['indent'] = $indent;
        }

        return $normalized;
    }

    /** @param array<string, mixed> $value */
    private function assertJsonSize(array $value): void
    {
        try {
            $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new InvalidArgumentException('O documento não pôde ser interpretado.');
        }

        if (strlen($encoded) > self::MAX_JSON_BYTES) {
            throw new InvalidArgumentException('O documento de aspectos gerais deve ter no máximo 1 MB.');
        }
    }

    /**
     * @param  array<string, mixed>  $value
     * @param  array<int, string>  $allowed
     */
    private function assertOnlyKeys(array $value, array $allowed): void
    {
        if (array_diff(array_keys($value), $allowed) !== []) {
            throw new InvalidArgumentException('O documento contém atributos não permitidos.');
        }
    }
}
