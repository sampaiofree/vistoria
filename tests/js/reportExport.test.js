import assert from 'node:assert/strict';
import test from 'node:test';
import {
    embedReportPageImages,
    prepareReportPageImages,
    ReportImageLoadError,
    reportPageDimensions,
} from '../../resources/js/lib/reportExport.js';

const xlinkNamespace = 'http://www.w3.org/1999/xlink';

function element(localName, attributes = {}, options = {}) {
    const values = new Map(Object.entries(attributes));

    return {
        localName,
        currentSrc: options.currentSrc,
        naturalWidth: options.naturalWidth ?? 640,
        complete: true,
        decoded: false,
        getAttribute: (name) => values.get(name) ?? null,
        getAttributeNS: (namespace, name) => values.get(`${namespace}:${name}`) ?? null,
        hasAttributeNS: (namespace, name) => values.has(`${namespace}:${name}`),
        setAttribute: (name, value) => values.set(name, value),
        setAttributeNS: (namespace, name, value) => values.set(`${namespace}:${name.split(':').at(-1)}`, value),
        removeAttribute: (name) => values.delete(name),
        closest: () => ({ getAttribute: () => options.mapLabel ?? 'Mapa REC' }),
        async decode() {
            this.decoded = true;
            if (options.decodeFails) throw new Error('Imagem inválida');
        },
    };
}

function page(...images) {
    return { querySelectorAll: () => images };
}

function mockImageLoading(t, fetchResponse = () => ({
    ok: true,
    blob: async () => new Blob(['image'], { type: 'image/webp' }),
})) {
    const originalFetch = globalThis.fetch;
    const originalReader = globalThis.FileReader;
    const originalImage = globalThis.Image;
    const requests = [];

    globalThis.fetch = async (url, options) => {
        requests.push({ url, options });
        return fetchResponse(url);
    };
    globalThis.FileReader = class {
        async readAsDataURL(blob) {
            this.result = `data:${blob.type};base64,${Buffer.from(await blob.arrayBuffer()).toString('base64')}`;
            this.onload();
        }
    };
    globalThis.Image = class {
        naturalWidth = 640;

        set src(value) {
            this.value = value;
            queueMicrotask(() => this.onload?.());
        }
    };

    t.after(() => {
        globalThis.fetch = originalFetch;
        globalThis.FileReader = originalReader;
        globalThis.Image = originalImage;
    });

    return requests;
}

test('uses A4 dimensions appropriate to each report page orientation', () => {
    assert.deepEqual(reportPageDimensions('portrait'), {
        orientation: 'portrait', widthMm: 210, heightMm: 297,
        widthPx: 794, heightPx: 1123,
        widthTwips: 11906, heightTwips: 16838,
    });
    assert.deepEqual(reportPageDimensions('landscape'), {
        orientation: 'landscape', widthMm: 297, heightMm: 210,
        widthPx: 1123, heightPx: 794,
        widthTwips: 16838, heightTwips: 11906,
    });
});

test('embeds photos and SVG map backgrounds for any report category', async (t) => {
    const requests = mockImageLoading(t);
    const originalPhoto = element('img', { src: '/photos/rec', alt: 'Foto REC' });
    const originalMap = element('image', { href: '/maps/tac', [`${xlinkNamespace}:href`]: '/maps/tac' }, { mapLabel: 'Mapa TAC' });
    const repeatedPhoto = element('img', { src: '/photos/rec', alt: 'Foto REC' });
    const prepared = await prepareReportPageImages(page(originalPhoto, originalMap, repeatedPhoto), 4);

    assert.equal(requests.length, 2);
    assert.deepEqual(requests.map(({ url }) => url), ['/photos/rec', '/maps/tac']);
    assert.ok(requests.every(({ options }) => options.credentials === 'same-origin' && options.signal));
    assert.deepEqual(prepared, Array(3).fill('data:image/webp;base64,aW1hZ2U='));

    const clonedPhoto = element('img', { src: '/photos/rec', srcset: '/photos/rec-small 1x' });
    const clonedMap = element('image', { href: '/maps/tac', [`${xlinkNamespace}:href`]: '/maps/tac' });
    const clonedRepeatedPhoto = element('img', { src: '/photos/rec' });
    await embedReportPageImages(page(clonedPhoto, clonedMap, clonedRepeatedPhoto), prepared, 4);

    assert.equal(clonedPhoto.getAttribute('src'), prepared[0]);
    assert.equal(clonedPhoto.getAttribute('srcset'), null);
    assert.equal(clonedMap.getAttribute('href'), prepared[1]);
    assert.equal(clonedMap.getAttributeNS(xlinkNamespace, 'href'), prepared[1]);
    assert.equal(clonedPhoto.decoded, true);
    assert.equal(clonedRepeatedPhoto.decoded, true);
});

test('allows pages without images', async (t) => {
    const requests = mockImageLoading(t);
    const prepared = await prepareReportPageImages(page(), 1);

    await embedReportPageImages(page(), prepared, 1);
    assert.deepEqual(prepared, []);
    assert.equal(requests.length, 0);
});

test('rejects missing map files before capturing the page', async (t) => {
    mockImageLoading(t, () => ({ ok: false, status: 404 }));

    await assert.rejects(
        prepareReportPageImages(page(element('image', { href: '/maps/rec' }, { mapLabel: 'Mapa REC' })), 7),
        (error) => error instanceof ReportImageLoadError && /Mapa REC.*página 7/.test(error.message),
    );
});

test('rejects responses that are not images', async (t) => {
    mockImageLoading(t, () => ({
        ok: true,
        blob: async () => new Blob(['<html>login</html>'], { type: 'text/html' }),
    }));

    await assert.rejects(
        prepareReportPageImages(page(element('img', { src: '/photos/civil', alt: 'Foto CIVIL' })), 8),
        (error) => error instanceof ReportImageLoadError && /Foto CIVIL.*página 8/.test(error.message),
    );
});

test('rejects photos that cannot be decoded in the cloned page', async () => {
    await assert.rejects(
        embedReportPageImages(page(element('img', { alt: 'Foto TEL' }, { decodeFails: true })), ['data:image/webp;base64,aW1hZ2U='], 9),
        (error) => error instanceof ReportImageLoadError && /Foto TEL.*página 9/.test(error.message),
    );
});
