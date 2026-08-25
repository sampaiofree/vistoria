const DEFAULT_MAX_DIMENSION = 2048;
const DEFAULT_TARGET_BYTES = 2 * 1024 * 1024;
const DEFAULT_INITIAL_QUALITY = 0.8;
const DEFAULT_MIN_QUALITY = 0.5;
const DEFAULT_QUALITY_STEP = 0.1;

const MIME_TYPE_EXTENSIONS = {
    'image/jpeg': 'jpg',
    'image/webp': 'webp',
};

export function calculateContainedDimensions(width, height, maxDimension = DEFAULT_MAX_DIMENSION) {
    if (!Number.isFinite(width) || !Number.isFinite(height) || width <= 0 || height <= 0) {
        throw new TypeError('As dimensões da imagem são inválidas.');
    }

    if (!Number.isFinite(maxDimension) || maxDimension <= 0) {
        throw new TypeError('A dimensão máxima da imagem é inválida.');
    }

    const scale = Math.min(1, maxDimension / Math.max(width, height));

    return {
        width: Math.max(1, Math.round(width * scale)),
        height: Math.max(1, Math.round(height * scale)),
    };
}

export function buildQualitySteps(
    initialQuality = DEFAULT_INITIAL_QUALITY,
    minQuality = DEFAULT_MIN_QUALITY,
    qualityStep = DEFAULT_QUALITY_STEP,
) {
    if (
        !Number.isFinite(initialQuality)
        || !Number.isFinite(minQuality)
        || !Number.isFinite(qualityStep)
        || initialQuality <= 0
        || initialQuality > 1
        || minQuality <= 0
        || minQuality > initialQuality
        || qualityStep <= 0
    ) {
        throw new TypeError('Os parâmetros de qualidade da imagem são inválidos.');
    }

    const qualities = [];

    for (let quality = initialQuality; quality >= minQuality; quality -= qualityStep) {
        qualities.push(Number(quality.toFixed(2)));
    }

    if (qualities.at(-1) !== minQuality) {
        qualities.push(minQuality);
    }

    return qualities;
}

export function optimizedImageName(originalName, mimeType) {
    const extension = MIME_TYPE_EXTENSIONS[mimeType];

    if (!extension) {
        throw new TypeError('O formato otimizado não é suportado.');
    }

    const normalizedName = String(originalName || 'fotografia');
    const lastDot = normalizedName.lastIndexOf('.');
    const baseName = lastDot > 0 ? normalizedName.slice(0, lastDot) : normalizedName;

    return `${baseName || 'fotografia'}.${extension}`;
}

function canvasToBlob(canvas, mimeType, quality) {
    return new Promise((resolve, reject) => {
        try {
            canvas.toBlob((blob) => {
                if (!blob) {
                    reject(new Error('O navegador não conseguiu gerar a imagem.'));
                    return;
                }

                resolve(blob);
            }, mimeType, quality);
        } catch (error) {
            reject(error);
        }
    });
}

async function encodeFormat(canvas, mimeType, qualities, targetBytes) {
    let smallest = null;

    for (const quality of qualities) {
        let blob;

        try {
            blob = await canvasToBlob(canvas, mimeType, quality);
        } catch {
            return smallest;
        }

        if (blob.type !== mimeType) {
            return smallest;
        }

        const candidate = { blob, mimeType, quality };

        if (!smallest || blob.size < smallest.blob.size) {
            smallest = candidate;
        }

        if (blob.size <= targetBytes) {
            return candidate;
        }
    }

    return smallest;
}

export async function encodeCanvasForUpload(canvas, {
    targetBytes = DEFAULT_TARGET_BYTES,
    initialQuality = DEFAULT_INITIAL_QUALITY,
    minQuality = DEFAULT_MIN_QUALITY,
    qualityStep = DEFAULT_QUALITY_STEP,
    beforeJpeg = () => {},
} = {}) {
    const qualities = buildQualitySteps(initialQuality, minQuality, qualityStep);
    const webp = await encodeFormat(canvas, 'image/webp', qualities, targetBytes);

    if (webp) {
        return webp;
    }

    beforeJpeg();
    const jpeg = await encodeFormat(canvas, 'image/jpeg', qualities, targetBytes);

    if (!jpeg) {
        throw new Error('O navegador não oferece um formato compatível para comprimir a imagem.');
    }

    return jpeg;
}

async function decodeWithImageBitmap(file) {
    if (typeof createImageBitmap !== 'function') {
        throw new Error('ImageBitmap não está disponível.');
    }

    let bitmap;

    try {
        bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' });
    } catch {
        bitmap = await createImageBitmap(file);
    }

    return {
        source: bitmap,
        width: bitmap.width,
        height: bitmap.height,
        dispose: () => bitmap.close?.(),
    };
}

function decodeWithImageElement(file) {
    return new Promise((resolve, reject) => {
        if (typeof Image !== 'function' || typeof URL === 'undefined' || typeof URL.createObjectURL !== 'function') {
            reject(new Error('O navegador não consegue abrir a imagem.'));
            return;
        }

        const objectUrl = URL.createObjectURL(file);
        const image = new Image();
        let settled = false;

        const releaseOnFailure = (error) => {
            if (settled) return;
            settled = true;
            URL.revokeObjectURL(objectUrl);
            reject(error);
        };

        image.decoding = 'async';
        image.onload = () => {
            if (settled) return;
            settled = true;
            resolve({
                source: image,
                width: image.naturalWidth,
                height: image.naturalHeight,
                dispose: () => URL.revokeObjectURL(objectUrl),
            });
        };
        image.onerror = () => releaseOnFailure(new Error('Não foi possível abrir a imagem selecionada.'));
        image.src = objectUrl;
    });
}

async function decodeBrowserImage(file) {
    try {
        return await decodeWithImageBitmap(file);
    } catch {
        return decodeWithImageElement(file);
    }
}

function createBrowserCanvas(width, height) {
    if (typeof document === 'undefined') {
        throw new Error('Canvas não está disponível.');
    }

    const canvas = document.createElement('canvas');
    canvas.width = width;
    canvas.height = height;

    const context = canvas.getContext('2d');

    if (!context) {
        throw new Error('Não foi possível preparar a área de desenho da imagem.');
    }

    return {
        canvas,
        context,
        dispose: () => {
            canvas.width = 1;
            canvas.height = 1;
        },
    };
}

function originalResult(file, warning = null) {
    return {
        file,
        size: file.size,
        usedOriginal: true,
        warning,
    };
}

export async function optimizeImageForUpload(file, {
    maxDimension = DEFAULT_MAX_DIMENSION,
    targetBytes = DEFAULT_TARGET_BYTES,
    initialQuality = DEFAULT_INITIAL_QUALITY,
    minQuality = DEFAULT_MIN_QUALITY,
    qualityStep = DEFAULT_QUALITY_STEP,
    decodeImage = decodeBrowserImage,
    createCanvas = createBrowserCanvas,
    encodeImage = encodeCanvasForUpload,
} = {}) {
    let decoded = null;
    let drawing = null;

    try {
        decoded = await decodeImage(file);
        const dimensions = calculateContainedDimensions(decoded.width, decoded.height, maxDimension);
        drawing = createCanvas(dimensions.width, dimensions.height);
        drawing.context.drawImage(decoded.source, 0, 0, dimensions.width, dimensions.height);

        const encoded = await encodeImage(drawing.canvas, {
            targetBytes,
            initialQuality,
            minQuality,
            qualityStep,
            beforeJpeg: () => {
                drawing.context.save();
                drawing.context.globalCompositeOperation = 'destination-over';
                drawing.context.fillStyle = '#ffffff';
                drawing.context.fillRect(0, 0, dimensions.width, dimensions.height);
                drawing.context.restore();
            },
        });

        const wasResized = dimensions.width !== decoded.width || dimensions.height !== decoded.height;

        if (!wasResized && encoded.blob.size >= file.size) {
            return originalResult(file);
        }

        const optimizedFile = new File(
            [encoded.blob],
            optimizedImageName(file.name, encoded.mimeType),
            {
                type: encoded.mimeType,
                lastModified: file.lastModified,
            },
        );

        return {
            file: optimizedFile,
            size: optimizedFile.size,
            usedOriginal: false,
            warning: null,
        };
    } catch {
        return originalResult(file, 'Não foi possível reduzir esta imagem.');
    } finally {
        drawing?.dispose?.();
        decoded?.dispose?.();
    }
}
