import assert from 'node:assert/strict';
import test from 'node:test';
import { clampPhotoOffset, zoomPhotoAt } from '../../resources/js/lib/reportPhotoZoom.js';

test('pan stops at the visible image edge and never moves an image that fits', () => {
    const viewport = { width: 400, height: 300 };
    const wideImage = { width: 800, height: 400 };
    assert.deepEqual(clampPhotoOffset({ x: 500, y: 500 }, 1, viewport, wideImage), { x: 0, y: 0 });
    assert.deepEqual(clampPhotoOffset({ x: 500, y: -500 }, 2, viewport, wideImage), { x: 200, y: -50 });
    assert.deepEqual(clampPhotoOffset({ x: -500, y: 500 }, 2, viewport, wideImage), { x: -200, y: 50 });
    assert.deepEqual(clampPhotoOffset({ x: 10, y: 10 }, 4, viewport, { width: 0, height: 0 }), { x: 0, y: 0 });
});

test('pinch keeps its focal point while honoring the zoom limits', () => {
    const viewport = { width: 400, height: 300 };
    const image = { width: 400, height: 300 };
    assert.deepEqual(zoomPhotoAt({ x: 0, y: 0 }, 1, 2, { x: 100, y: 0 }, viewport, image), {
        scale: 2, offset: { x: -100, y: 0 },
    });
    assert.deepEqual(zoomPhotoAt({ x: -100, y: 0 }, 2, 1, { x: 100, y: 0 }, viewport, image), {
        scale: 1, offset: { x: 0, y: 0 },
    });
    assert.equal(zoomPhotoAt({ x: 0, y: 0 }, 1, 10, { x: 0, y: 0 }, viewport, image).scale, 4);
    assert.equal(zoomPhotoAt({ x: 0, y: 0 }, 4, 0, { x: 0, y: 0 }, viewport, image).scale, 1);
});
