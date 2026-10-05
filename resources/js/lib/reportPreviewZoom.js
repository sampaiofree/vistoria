export const MIN_REPORT_ZOOM = 50;
export const MAX_REPORT_ZOOM = 200;
const ZOOM_STEP = 25;

export function reportPreviewScale(width, orientation = 'portrait', percent = null) {
    if (percent !== null) return percent / 100;
    const pageWidth = (orientation === 'landscape' ? 297 : 210) * 96 / 25.4;
    return width > 0 ? width / pageWidth : 1;
}

export function stepReportZoom(percent, direction) {
    // Avoid skipping a step because of fractional CSS pixel rounding.
    const steps = Math.round(percent * 1000) / 1000 / ZOOM_STEP;
    const next = direction > 0 ? Math.floor(steps) + 1 : Math.ceil(steps) - 1;
    return Math.min(MAX_REPORT_ZOOM, Math.max(MIN_REPORT_ZOOM, next * ZOOM_STEP));
}
