<?php

declare(strict_types=1);

namespace App\Services\InspectionLocations;

use Illuminate\Validation\ValidationException;

final class InspectionLocationGeometryValidator
{
    private const MAX_BYTES = 65_536;

    private const DEFAULT_COLOR = '#F1DF00';

    private const SHAPE_KEYS = [
        'point' => ['type', 'x', 'y'],
        'rectangle' => ['type', 'x', 'y', 'width', 'height'],
        'polygon' => ['type', 'points'],
        'polyline' => ['type', 'points'],
    ];

    private const STYLE_KEYS = ['stroke', 'fill', 'stroke_width', 'opacity', 'dashed'];

    /** @return array{stroke:string,fill:string,stroke_width:float,opacity:float,dashed:bool} */
    public function validate(array $geometry, ?array $style = null): array
    {
        $normalizedStyle = $this->normalizeStyle($style);

        try {
            $encoded = json_encode(['geometry' => $geometry, 'style' => $normalizedStyle], JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw ValidationException::withMessages(['geometry' => 'A geometria contém valores inválidos.']);
        }
        $this->ensure(strlen($encoded) <= self::MAX_BYTES, 'A geometria excede o limite permitido.');
        $this->onlyKeys($geometry, ['version', 'shapes', 'callout'], 'geometria');
        $this->ensure(($geometry['version'] ?? null) === 1, 'A versão da geometria deve ser 1.');
        $this->ensure(is_array($geometry['shapes'] ?? null), 'A geometria deve possuir formas.');

        $shapes = $geometry['shapes'];
        $this->ensure(count($shapes) >= 1 && count($shapes) <= 50, 'Cada marcação deve possuir entre 1 e 50 formas.');

        foreach ($shapes as $shape) {
            $this->validateShape($shape);
        }

        if (array_key_exists('callout', $geometry) && $geometry['callout'] !== null) {
            $callout = $geometry['callout'];
            $this->ensure(is_array($callout), 'A chamada da marcação é inválida.');
            $this->onlyKeys($callout, ['x', 'y', 'anchor_x', 'anchor_y'], 'chamada');
            $this->ensure(array_keys($callout) === ['x', 'y', 'anchor_x', 'anchor_y'], 'A chamada deve informar posição e âncora.');
            foreach ($callout as $coordinate) {
                $this->coordinate($coordinate);
            }
        }

        $this->validateStyle($normalizedStyle);
        $this->validateStyleForGeometry($geometry, $normalizedStyle);

        return $normalizedStyle;
    }

    private function validateShape(mixed $shape): void
    {
        $this->ensure(is_array($shape), 'Uma das formas é inválida.');
        $type = $shape['type'] ?? null;
        $this->ensure(is_string($type) && isset(self::SHAPE_KEYS[$type]), 'O tipo da forma é inválido.');
        $this->onlyKeys($shape, self::SHAPE_KEYS[$type], 'forma');

        if ($type === 'point') {
            $this->ensure(array_keys($shape) === ['type', 'x', 'y'], 'O ponto deve informar x e y.');
            $this->coordinate($shape['x']);
            $this->coordinate($shape['y']);

            return;
        }

        if ($type === 'rectangle') {
            foreach (['x', 'y', 'width', 'height'] as $field) {
                $this->ensure(array_key_exists($field, $shape), 'O retângulo está incompleto.');
                $this->coordinate($shape[$field], in_array($field, ['width', 'height'], true));
            }
            $this->ensure($shape['x'] + $shape['width'] <= 1 && $shape['y'] + $shape['height'] <= 1, 'O retângulo deve permanecer dentro do mapa.');

            return;
        }

        $this->ensure(is_array($shape['points'] ?? null), 'A forma deve possuir pontos.');
        $minimum = $type === 'polygon' ? 3 : 2;
        $this->ensure(count($shape['points']) >= $minimum && count($shape['points']) <= 100, 'A quantidade de pontos da forma é inválida.');
        foreach ($shape['points'] as $point) {
            $this->ensure(is_array($point) && count($point) === 2 && array_keys($point) === [0, 1], 'Um dos pontos da forma é inválido.');
            $this->coordinate($point[0]);
            $this->coordinate($point[1]);
        }
    }

    private function validateStyle(?array $style): void
    {
        if ($style === null) {
            return;
        }

        $this->onlyKeys($style, self::STYLE_KEYS, 'estilo');
        foreach (['stroke', 'fill'] as $field) {
            if (isset($style[$field])) {
                $this->ensure(is_string($style[$field]) && ($style[$field] === 'none' || preg_match('/^#[0-9A-Fa-f]{6}$/', $style[$field]) === 1), 'Uma cor do estilo é inválida.');
            }
        }
        if (isset($style['stroke_width'])) {
            $this->ensure($this->finite($style['stroke_width']) && $style['stroke_width'] > 0 && $style['stroke_width'] <= 0.05, 'A espessura do traço é inválida.');
        }
        if (isset($style['opacity'])) {
            $this->coordinate($style['opacity']);
        }
        if (isset($style['dashed'])) {
            $this->ensure(is_bool($style['dashed']), 'A opção de tracejado é inválida.');
        }
    }

    /** @return array{stroke:string,fill:string,stroke_width:float,opacity:float,dashed:bool} */
    private function normalizeStyle(?array $style): array
    {
        $style ??= [];
        $this->onlyKeys($style, self::STYLE_KEYS, 'estilo');
        $this->validateStyle($style);

        $rawStroke = $style['stroke'] ?? null;
        $rawFill = $style['fill'] ?? null;
        $strokeColor = $this->hexColor($rawStroke) ? strtoupper($rawStroke) : null;
        $fillColor = $this->hexColor($rawFill) ? strtoupper($rawFill) : null;
        $color = $fillColor ?? $strokeColor ?? self::DEFAULT_COLOR;

        if ($rawStroke === 'none' && ! $this->hexColor($rawFill)) {
            throw ValidationException::withMessages(['geometry' => 'A marcação sem borda precisa possuir uma cor de preenchimento.']);
        }

        return [
            'stroke' => $rawStroke === 'none' ? 'none' : ($strokeColor ?? $color),
            'fill' => $fillColor ?? $color,
            'stroke_width' => isset($style['stroke_width']) ? (float) $style['stroke_width'] : 0.005,
            'opacity' => isset($style['opacity']) ? (float) $style['opacity'] : 0.9,
            'dashed' => isset($style['dashed']) ? (bool) $style['dashed'] : false,
        ];
    }

    /** @param array<string, mixed> $geometry @param array<string, mixed> $style */
    private function validateStyleForGeometry(array $geometry, array $style): void
    {
        if ($style['stroke'] !== 'none') {
            return;
        }

        $hasOpenShape = collect($geometry['shapes'] ?? [])->contains(
            fn (mixed $shape): bool => is_array($shape)
                && in_array($shape['type'] ?? null, ['point', 'polyline'], true),
        );

        $this->ensure(! $hasOpenShape, 'A borda é obrigatória para pontos, linhas e marcações mistas que contenham essas formas.');
    }

    private function hexColor(mixed $value): bool
    {
        return is_string($value) && preg_match('/^#[0-9A-Fa-f]{6}$/', $value) === 1;
    }

    private function coordinate(mixed $value, bool $positive = false): void
    {
        $this->ensure($this->finite($value) && $value >= 0 && $value <= 1 && (! $positive || $value > 0), 'As coordenadas devem ser números finitos entre 0 e 1.');
    }

    private function finite(mixed $value): bool
    {
        return (is_int($value) || is_float($value)) && is_finite((float) $value);
    }

    private function onlyKeys(array $payload, array $allowed, string $context): void
    {
        $this->ensure(array_diff(array_keys($payload), $allowed) === [], "O {$context} possui propriedades desconhecidas.");
    }

    private function ensure(bool $condition, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages(['geometry' => $message]);
        }
    }
}
