export const DEFAULT_MARKER_COLOR = '#F1DF00';
export const MARKER_AREA_FILL_OPACITY = 0.18;

export function isMarkerColor(value) {
    return typeof value === 'string' && /^#[0-9A-F]{6}$/i.test(value.trim());
}

export function normalizeMarkerColor(value, fallback = DEFAULT_MARKER_COLOR) {
    return isMarkerColor(value) ? value.trim().toUpperCase() : fallback;
}

export function markerLegendTextColor(backgroundColor) {
    const color = normalizeMarkerColor(backgroundColor);
    const channels = [1, 3, 5].map((index) => {
        const value = Number.parseInt(color.slice(index, index + 2), 16) / 255;

        return value <= 0.04045 ? value / 12.92 : ((value + 0.055) / 1.055) ** 2.4;
    });
    const luminance = (0.2126 * channels[0]) + (0.7152 * channels[1]) + (0.0722 * channels[2]);

    return luminance <= 0.179 ? '#FFFFFF' : '#000000';
}

export function markerSupportsOptionalBorder(geometry) {
    const shapes = geometry?.shapes || [];

    return shapes.length > 0 && shapes.every((shape) => ['rectangle', 'polygon'].includes(shape?.type));
}

export function normalizeMarkerStyle(style = {}, geometry = null) {
    const fillColor = style?.fill !== 'none' ? normalizeMarkerColor(style?.fill, null) : null;
    const strokeColor = style?.stroke !== 'none' ? normalizeMarkerColor(style?.stroke, null) : null;
    const color = fillColor || strokeColor || DEFAULT_MARKER_COLOR;
    const borderWasDisabled = style?.stroke === 'none' && isMarkerColor(style?.fill);
    const borderEnabled = !borderWasDisabled || !markerSupportsOptionalBorder(geometry);
    const strokeWidth = Number(style?.stroke_width);
    const opacity = Number(style?.opacity);

    return {
        stroke: borderEnabled ? color : 'none',
        fill: color,
        stroke_width: Number.isFinite(strokeWidth) && strokeWidth > 0 ? strokeWidth : 0.005,
        opacity: Number.isFinite(opacity) && opacity >= 0 && opacity <= 1 ? opacity : 0.9,
        dashed: style?.dashed === true,
    };
}

export function markerPresentationStyle(style = {}, geometry = null, viewWidth = 1000) {
    const normalized = normalizeMarkerStyle(style, geometry);

    return {
        ...normalized,
        color: normalized.fill,
        borderEnabled: normalized.stroke !== 'none',
        strokeWidth: normalized.stroke_width * viewWidth,
        dashArray: normalized.dashed ? '12 8' : undefined,
        fillOpacity: MARKER_AREA_FILL_OPACITY,
    };
}
