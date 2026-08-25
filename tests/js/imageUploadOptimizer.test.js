import assert from 'node:assert/strict';
import test from 'node:test';
import {
    buildQualitySteps,
    calculateContainedDimensions,
    encodeCanvasForUpload,
    optimizeImageForUpload,
    optimizedImageName,
} from '../../resources/js/lib/imageUploadOptimizer.js';

test('limits the longest side while preserving landscape and portrait proportions', () => {
    assert.deepEqual(calculateContainedDimensions(4000, 3000), { width: 2048, height: 1536 });
    assert.deepEqual(calculateContainedDimensions(3000, 4000), { width: 1536, height: 2048 });
    assert.deepEqual(calculateContainedDimensions(4096, 4096), { width: 2048, height: 2048 });
});

test('does not upscale an image already inside the dimension limit', () => {
    assert.deepEqual(calculateContainedDimensions(1200, 800), { width: 1200, height: 800 });
});

test('builds deterministic quality attempts down to the configured minimum', () => {
    assert.deepEqual(buildQualitySteps(), [0.8, 0.7, 0.6, 0.5]);
    assert.deepEqual(buildQualitySteps(0.75, 0.5, 0.1), [0.75, 0.65, 0.55, 0.5]);
});

test('uses an extension that matches the encoded format', () => {
    assert.equal(optimizedImageName('vistoria.final.jpeg', 'image/webp'), 'vistoria.final.webp');
    assert.equal(optimizedImageName('vistoria', 'image/jpeg'), 'vistoria.jpg');
});

test('reduces WebP quality progressively until reaching the size target', async () => {
    const calls = [];
    const sizes = new Map([
        [0.8, 300],
        [0.7, 240],
        [0.6, 180],
    ]);
    const canvas = {
        toBlob(callback, mimeType, quality) {
            calls.push({ mimeType, quality });
            callback(new Blob([new Uint8Array(sizes.get(quality) ?? 150)], { type: mimeType }));
        },
    };

    const result = await encodeCanvasForUpload(canvas, { targetBytes: 200 });

    assert.equal(result.mimeType, 'image/webp');
    assert.equal(result.quality, 0.6);
    assert.equal(result.blob.size, 180);
    assert.deepEqual(calls, [
        { mimeType: 'image/webp', quality: 0.8 },
        { mimeType: 'image/webp', quality: 0.7 },
        { mimeType: 'image/webp', quality: 0.6 },
    ]);
});

test('falls back to JPEG when canvas does not honor WebP encoding', async () => {
    const calls = [];
    let preparedJpeg = false;
    const canvas = {
        toBlob(callback, mimeType, quality) {
            calls.push({ mimeType, quality });
            const actualType = mimeType === 'image/webp' ? 'image/png' : mimeType;
            callback(new Blob([new Uint8Array(100)], { type: actualType }));
        },
    };

    const result = await encodeCanvasForUpload(canvas, {
        targetBytes: 200,
        beforeJpeg: () => {
            preparedJpeg = true;
        },
    });

    assert.equal(result.mimeType, 'image/jpeg');
    assert.equal(result.quality, 0.8);
    assert.equal(preparedJpeg, true);
    assert.deepEqual(calls, [
        { mimeType: 'image/webp', quality: 0.8 },
        { mimeType: 'image/jpeg', quality: 0.8 },
    ]);
});

test('creates a resized upload file and releases decoded image resources', async () => {
    const original = new File([new Uint8Array(1000)], 'camera.jpeg', {
        type: 'image/jpeg',
        lastModified: 1234,
    });
    const drawCalls = [];
    let decodedDisposed = false;
    let canvasDisposed = false;

    const result = await optimizeImageForUpload(original, {
        decodeImage: async () => ({
            source: { id: 'decoded-image' },
            width: 4000,
            height: 3000,
            dispose: () => {
                decodedDisposed = true;
            },
        }),
        createCanvas: (width, height) => ({
            canvas: { width, height },
            context: {
                drawImage: (...args) => drawCalls.push(args),
            },
            dispose: () => {
                canvasDisposed = true;
            },
        }),
        encodeImage: async () => ({
            blob: new Blob([new Uint8Array(200)], { type: 'image/webp' }),
            mimeType: 'image/webp',
            quality: 0.8,
        }),
    });

    assert.equal(result.file.name, 'camera.webp');
    assert.equal(result.file.type, 'image/webp');
    assert.equal(result.file.size, 200);
    assert.equal(result.usedOriginal, false);
    assert.deepEqual(drawCalls, [[{ id: 'decoded-image' }, 0, 0, 2048, 1536]]);
    assert.equal(decodedDisposed, true);
    assert.equal(canvasDisposed, true);
});

test('keeps a smaller original when an image does not need resizing', async () => {
    const original = new File([new Uint8Array(100)], 'small.jpg', { type: 'image/jpeg' });

    const result = await optimizeImageForUpload(original, {
        decodeImage: async () => ({ source: {}, width: 800, height: 600 }),
        createCanvas: () => ({
            canvas: {},
            context: { drawImage: () => {} },
        }),
        encodeImage: async () => ({
            blob: new Blob([new Uint8Array(150)], { type: 'image/webp' }),
            mimeType: 'image/webp',
            quality: 0.8,
        }),
    });

    assert.equal(result.file, original);
    assert.equal(result.usedOriginal, true);
    assert.equal(result.warning, null);
});

test('returns the original with a warning when browser compression fails', async () => {
    const original = new File([new Uint8Array(500)], 'camera.jpg', { type: 'image/jpeg' });

    const result = await optimizeImageForUpload(original, {
        decodeImage: async () => {
            throw new Error('decode failed');
        },
    });

    assert.equal(result.file, original);
    assert.equal(result.size, original.size);
    assert.equal(result.usedOriginal, true);
    assert.match(result.warning, /reduzir esta imagem/);
});
