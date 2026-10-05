export const MIN_PHOTO_ZOOM = 1;
export const MAX_PHOTO_ZOOM = 4;

export function clampPhotoOffset(offset, scale, viewport, image) {
    if (!viewport.width || !viewport.height || !image.width || !image.height) return { x: 0, y: 0 };

    const fit = Math.min(viewport.width / image.width, viewport.height / image.height);
    const maxX = Math.max(0, (image.width * fit * scale - viewport.width) / 2);
    const maxY = Math.max(0, (image.height * fit * scale - viewport.height) / 2);

    return {
        x: Math.max(-maxX, Math.min(maxX, offset.x)),
        y: Math.max(-maxY, Math.min(maxY, offset.y)),
    };
}

export function zoomPhotoAt(offset, currentScale, nextScale, point, viewport, image) {
    const scale = Math.max(MIN_PHOTO_ZOOM, Math.min(MAX_PHOTO_ZOOM, nextScale));
    const ratio = scale / currentScale;
    return {
        scale,
        offset: clampPhotoOffset({
            x: offset.x * ratio + point.x * (1 - ratio),
            y: offset.y * ratio + point.y * (1 - ratio),
        }, scale, viewport, image),
    };
}
