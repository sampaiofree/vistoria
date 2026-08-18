const CANVAS_MARGIN = 8;
const LEGEND_GAP = 12;
const LEGEND_PADDING_X = 10;
const LEGEND_PADDING_Y = 8;
const PHOTO_LINE_HEIGHT = 18;
const ADDITIONAL_LINE_HEIGHT = 16;
const MAX_ADDITIONAL_LINES = 3;
const MAX_CHARACTERS_PER_LINE = 80;

function clamp(value, minimum, maximum) {
    return Math.min(Math.max(value, minimum), maximum);
}

function finite(value, fallback = 0) {
    const parsed = Number(value);

    return Number.isFinite(parsed) ? parsed : fallback;
}

function shapeBounds(shape, viewWidth, viewHeight) {
    if (!shape) return null;

    if (shape.type === 'point') {
        const x = finite(shape.x, 0.5) * viewWidth;
        const y = finite(shape.y, 0.5) * viewHeight;

        return { minX: x - 11, maxX: x + 11, minY: y - 11, maxY: y + 11 };
    }

    if (shape.type === 'rectangle') {
        const x = finite(shape.x) * viewWidth;
        const y = finite(shape.y) * viewHeight;
        const width = Math.max(0, finite(shape.width) * viewWidth);
        const height = Math.max(0, finite(shape.height) * viewHeight);

        return { minX: x, maxX: x + width, minY: y, maxY: y + height };
    }

    const shapePoints = Array.isArray(shape.points) ? shape.points : [];

    if (!shapePoints.length) return null;

    const xs = shapePoints.map((point) => finite(point?.[0], 0.5) * viewWidth);
    const ys = shapePoints.map((point) => finite(point?.[1], 0.5) * viewHeight);

    return {
        minX: Math.min(...xs),
        maxX: Math.max(...xs),
        minY: Math.min(...ys),
        maxY: Math.max(...ys),
    };
}

export function markerBounds(marker, viewWidth, viewHeight) {
    const bounds = (marker?.geometry?.shapes || [])
        .map((shape) => shapeBounds(shape, viewWidth, viewHeight))
        .filter(Boolean);

    if (!bounds.length) {
        return {
            minX: viewWidth / 2,
            maxX: viewWidth / 2,
            minY: viewHeight / 2,
            maxY: viewHeight / 2,
        };
    }

    return {
        minX: Math.min(...bounds.map((item) => item.minX)),
        maxX: Math.max(...bounds.map((item) => item.maxX)),
        minY: Math.min(...bounds.map((item) => item.minY)),
        maxY: Math.max(...bounds.map((item) => item.maxY)),
    };
}

function splitLongWord(word, maximum) {
    const pieces = [];

    for (let index = 0; index < word.length; index += maximum) {
        pieces.push(word.slice(index, index + maximum));
    }

    return pieces;
}

export function wrapAdditionalLegend(value) {
    const normalized = String(value || '').trim().replace(/\s+/g, ' ');

    if (!normalized) return [];

    const words = normalized
        .split(' ')
        .flatMap((word) => word.length > MAX_CHARACTERS_PER_LINE
            ? splitLongWord(word, MAX_CHARACTERS_PER_LINE)
            : [word]);
    const lines = [];
    let current = '';

    for (const word of words) {
        const candidate = current ? `${current} ${word}` : word;

        if (candidate.length <= MAX_CHARACTERS_PER_LINE) {
            current = candidate;
            continue;
        }

        if (current) lines.push(current);
        current = word;
    }

    if (current) lines.push(current);

    if (lines.length <= MAX_ADDITIONAL_LINES) return lines;

    const visible = lines.slice(0, MAX_ADDITIONAL_LINES);
    visible[MAX_ADDITIONAL_LINES - 1] = `${visible[MAX_ADDITIONAL_LINES - 1].slice(0, MAX_CHARACTERS_PER_LINE - 1).trimEnd()}…`;

    return visible;
}

export function legendMetrics(marker) {
    const photoLegend = marker?.photo_legend || 'FOTOS: —';
    const additionalLines = wrapAdditionalLegend(marker?.label);
    const longestAdditionalLine = additionalLines.reduce((maximum, line) => Math.max(maximum, line.length), 0);
    const contentWidth = Math.max(photoLegend.length * 8, longestAdditionalLine * 6.1);
    const width = clamp(contentWidth + (LEGEND_PADDING_X * 2), 140, 500);
    const height = (LEGEND_PADDING_Y * 2) + PHOTO_LINE_HEIGHT + (additionalLines.length * ADDITIONAL_LINE_HEIGHT);

    return { photoLegend, additionalLines, width, height };
}

function intersectionArea(first, second) {
    const width = Math.max(0, Math.min(first.x + first.width, second.x + second.width) - Math.max(first.x, second.x));
    const height = Math.max(0, Math.min(first.y + first.height, second.y + second.height) - Math.max(first.y, second.y));

    return width * height;
}

function candidateBoxes(bounds, metrics, side, viewWidth, viewHeight) {
    const centerX = (bounds.minX + bounds.maxX) / 2;
    const baseY = side === 'below'
        ? bounds.maxY + LEGEND_GAP
        : bounds.minY - LEGEND_GAP - metrics.height;
    const horizontalStep = (metrics.width / 2) + 10;
    const horizontalOffsets = [0, horizontalStep, -horizontalStep, horizontalStep * 2, horizontalStep * -2];
    const candidates = [];

    for (let row = 0; row < 5; row += 1) {
        const verticalOffset = row * (metrics.height + 6) * (side === 'below' ? 1 : -1);

        for (const horizontalOffset of horizontalOffsets) {
            const x = clamp(
                centerX - (metrics.width / 2) + horizontalOffset,
                CANVAS_MARGIN,
                Math.max(CANVAS_MARGIN, viewWidth - metrics.width - CANVAS_MARGIN),
            );
            const y = baseY + verticalOffset;

            if (y < CANVAS_MARGIN || y + metrics.height > viewHeight - CANVAS_MARGIN) continue;

            if (!candidates.some((candidate) => candidate.x === x && candidate.y === y)) {
                candidates.push({ x, y, width: metrics.width, height: metrics.height, side });
            }
        }
    }

    return candidates;
}

export function layoutMarkerLegends(markers, viewWidth, viewHeight) {
    const layouts = {};
    const occupied = [];

    (markers || []).forEach((marker, index) => {
        const key = marker.public_id || marker.presentation_id || `marker-${index}`;
        const bounds = markerBounds(marker, viewWidth, viewHeight);
        const metrics = legendMetrics(marker);
        const fitsBelow = bounds.maxY + LEGEND_GAP + metrics.height <= viewHeight - CANVAS_MARGIN;
        const side = fitsBelow ? 'below' : 'above';
        const candidates = candidateBoxes(bounds, metrics, side, viewWidth, viewHeight);
        const fallbackY = side === 'below'
            ? clamp(bounds.maxY + LEGEND_GAP, CANVAS_MARGIN, viewHeight - metrics.height - CANVAS_MARGIN)
            : clamp(bounds.minY - LEGEND_GAP - metrics.height, CANVAS_MARGIN, viewHeight - metrics.height - CANVAS_MARGIN);
        const fallback = {
            x: clamp((bounds.minX + bounds.maxX - metrics.width) / 2, CANVAS_MARGIN, Math.max(CANVAS_MARGIN, viewWidth - metrics.width - CANVAS_MARGIN)),
            y: fallbackY,
            width: metrics.width,
            height: metrics.height,
            side,
        };
        const selected = candidates.find((candidate) => occupied.every((box) => intersectionArea(candidate, box) === 0))
            || candidates
                .map((candidate) => ({ candidate, overlap: occupied.reduce((sum, box) => sum + intersectionArea(candidate, box), 0) }))
                .sort((first, second) => first.overlap - second.overlap)[0]?.candidate
            || fallback;
        const anchorX = clamp((bounds.minX + bounds.maxX) / 2, 0, viewWidth);
        const anchorY = side === 'below' ? bounds.maxY : bounds.minY;

        layouts[key] = {
            ...selected,
            ...metrics,
            anchorX,
            anchorY,
            connectorX: selected.x + (selected.width / 2),
            connectorY: side === 'below' ? selected.y : selected.y + selected.height,
        };
        occupied.push(selected);
    });

    return layouts;
}

