const A4_WIDTH_MM = 210;
const A4_HEIGHT_MM = 297;
const A4_WIDTH_PX = 794;
const A4_HEIGHT_PX = 1123;
const A4_WIDTH_TWIPS = 11906;
const A4_HEIGHT_TWIPS = 16838;
const A4_LANDSCAPE_WIDTH_MM = 297;
const A4_LANDSCAPE_HEIGHT_MM = 210;
const A4_LANDSCAPE_WIDTH_PX = 1123;
const A4_LANDSCAPE_HEIGHT_PX = 794;
const A4_LANDSCAPE_WIDTH_TWIPS = 16838;
const A4_LANDSCAPE_HEIGHT_TWIPS = 11906;

export function reportPageDimensions(orientation = 'portrait') {
    return orientation === 'landscape'
        ? {
            orientation: 'landscape', widthMm: A4_LANDSCAPE_WIDTH_MM, heightMm: A4_LANDSCAPE_HEIGHT_MM,
            widthPx: A4_LANDSCAPE_WIDTH_PX, heightPx: A4_LANDSCAPE_HEIGHT_PX,
            widthTwips: A4_LANDSCAPE_WIDTH_TWIPS, heightTwips: A4_LANDSCAPE_HEIGHT_TWIPS,
        }
        : {
            orientation: 'portrait', widthMm: A4_WIDTH_MM, heightMm: A4_HEIGHT_MM,
            widthPx: A4_WIDTH_PX, heightPx: A4_HEIGHT_PX,
            widthTwips: A4_WIDTH_TWIPS, heightTwips: A4_HEIGHT_TWIPS,
        };
}

function nextFrame() {
    return new Promise((resolve) => requestAnimationFrame(() => requestAnimationFrame(resolve)));
}

const REPORT_IMAGE_SELECTOR = 'img, svg image';
const XLINK_NAMESPACE = 'http://www.w3.org/1999/xlink';

export class ReportImageLoadError extends Error {
    constructor(page, label) {
        super(`Não foi possível carregar ${label} na página ${page} do relatório. Verifique a imagem e tente novamente.`);
        this.name = 'ReportImageLoadError';
    }
}

function imageLabel(element) {
    if (element.localName === 'image') {
        const label = element.closest('svg')?.getAttribute('aria-label');
        return label ? `o ${label}` : 'o mapa';
    }

    const label = element.getAttribute('alt');
    return label ? `a ${label}` : 'a fotografia';
}

function imageUrl(element) {
    return element.localName === 'image'
        ? element.getAttribute('href') || element.getAttributeNS(XLINK_NAMESPACE, 'href')
        : element.currentSrc || element.getAttribute('src');
}

function blobDataUrl(blob) {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = () => typeof reader.result === 'string'
            ? resolve(reader.result)
            : reject(new Error('Imagem inválida'));
        reader.onerror = () => reject(reader.error);
        reader.readAsDataURL(blob);
    });
}

function validateImageDataUrl(dataUrl) {
    return new Promise((resolve, reject) => {
        const image = new Image();
        image.onload = () => image.naturalWidth ? resolve() : reject(new Error('Imagem inválida'));
        image.onerror = () => reject(new Error('Imagem inválida'));
        image.src = dataUrl;
    });
}

async function fetchImageDataUrl(url) {
    if (url.startsWith('data:image/')) return url;

    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), 30000);

    try {
        const response = await fetch(url, { credentials: 'same-origin', signal: controller.signal });
        if (!response.ok) throw new Error(`HTTP ${response.status}`);

        const blob = await response.blob();
        if (!blob.size || !blob.type.startsWith('image/')) throw new Error('Resposta sem imagem válida');

        return await blobDataUrl(blob);
    } finally {
        clearTimeout(timeout);
    }
}

export async function prepareReportPageImages(pageElement, pageNumber) {
    const images = [...pageElement.querySelectorAll(REPORT_IMAGE_SELECTOR)];
    const dataUrls = new Map();

    return Promise.all(images.map(async (element) => {
        const url = imageUrl(element)?.trim();
        if (!url) return null;

        try {
            if (!dataUrls.has(url)) dataUrls.set(url, fetchImageDataUrl(url));
            const dataUrl = await dataUrls.get(url);
            if (element.localName === 'image') await validateImageDataUrl(dataUrl);
            return dataUrl;
        } catch {
            throw new ReportImageLoadError(pageNumber, imageLabel(element));
        }
    }));
}

export async function embedReportPageImages(clonedPage, dataUrls, pageNumber) {
    const images = [...clonedPage.querySelectorAll(REPORT_IMAGE_SELECTOR)];
    if (images.length !== dataUrls.length) {
        throw new Error(`A página ${pageNumber} mudou durante a exportação. Tente novamente.`);
    }

    await Promise.all(images.map(async (element, index) => {
        const dataUrl = dataUrls[index];
        if (!dataUrl) return;

        if (element.localName === 'image') {
            element.setAttribute('href', dataUrl);
            if (element.hasAttributeNS(XLINK_NAMESPACE, 'href')) {
                element.setAttributeNS(XLINK_NAMESPACE, 'xlink:href', dataUrl);
            }
            return;
        }

        element.removeAttribute('srcset');
        element.setAttribute('src', dataUrl);

        try {
            if (typeof element.decode === 'function') {
                await element.decode();
            } else if (!element.complete) {
                await new Promise((resolve, reject) => {
                    element.addEventListener('load', resolve, { once: true });
                    element.addEventListener('error', reject, { once: true });
                });
            }
            if (!element.naturalWidth) throw new Error('Imagem inválida');
        } catch {
            throw new ReportImageLoadError(pageNumber, imageLabel(element));
        }
    }));
}

function pngBlob(canvas) {
    return new Promise((resolve, reject) => {
        canvas.toBlob((blob) => {
            if (blob) {
                resolve(blob);
                return;
            }

            reject(new Error('Não foi possível criar a imagem da página.'));
        }, 'image/png');
    });
}

export function reportFilename(value) {
    const normalized = String(value || 'relatorio')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-zA-Z0-9_-]+/g, '-')
        .replace(/^-+|-+$/g, '')
        .toLowerCase();

    return normalized || 'relatorio';
}

export async function captureReportPages(elements, onProgress = () => {}) {
    if (!elements.length) {
        throw new Error('Nenhuma página do relatório foi encontrada.');
    }

    if (document.fonts?.ready) {
        await document.fonts.ready;
    }
    await nextFrame();

    const { default: html2canvas } = await import('html2canvas');
    const pages = [];

    for (let index = 0; index < elements.length; index += 1) {
        onProgress({ current: index + 1, total: elements.length });
        const imageDataUrls = await prepareReportPageImages(elements[index], index + 1);
        const canvas = await html2canvas(elements[index], {
            backgroundColor: '#FFFFFF',
            scale: 2,
            useCORS: true,
            allowTaint: false,
            logging: false,
            imageTimeout: 30000,
            removeContainer: true,
            onclone: async (clonedDocument, clonedPage) => {
                clonedDocument.documentElement.style.setProperty('background-color', '#FFFFFF', 'important');
                clonedDocument.body.style.setProperty('background-color', '#FFFFFF', 'important');
                clonedDocument.querySelector('.report-preview-pages')?.classList.add('report-exporting');
                await embedReportPageImages(clonedPage, imageDataUrls, index + 1);
            },
        });
        const blob = await pngBlob(canvas);
        pages.push({
            data: new Uint8Array(await blob.arrayBuffer()),
            orientation: elements[index].dataset.reportOrientation === 'landscape' ? 'landscape' : 'portrait',
        });
        canvas.width = 1;
        canvas.height = 1;
    }

    return pages;
}

export async function downloadReportPdf(pages, filename) {
    const { jsPDF } = await import('jspdf');
    const firstPage = reportPageDimensions(pages[0]?.orientation);
    const pdf = new jsPDF({
        orientation: firstPage.orientation,
        unit: 'mm',
        format: 'a4',
        compress: true,
        putOnlyUsedFonts: true,
    });

    pages.forEach((page, index) => {
        const dimensions = reportPageDimensions(page.orientation);
        if (index > 0) pdf.addPage('a4', dimensions.orientation);
        pdf.addImage(page.data, 'PNG', 0, 0, dimensions.widthMm, dimensions.heightMm, undefined, 'FAST');
    });

    pdf.save(`${filename}.pdf`);
}

export async function downloadReportDoc(pages, filename) {
    const {
        Document,
        HorizontalPositionRelativeFrom,
        ImageRun,
        Packer,
        Paragraph,
        SectionType,
        TextWrappingType,
        VerticalPositionRelativeFrom,
    } = await import('docx');
    const documentFile = new Document({
        creator: 'Vistoria',
        title: filename,
        sections: pages.map((page, index) => {
            const dimensions = reportPageDimensions(page.orientation);

            return {
            properties: {
                type: index === 0 ? undefined : SectionType.NEXT_PAGE,
                page: {
                    size: { width: dimensions.widthTwips, height: dimensions.heightTwips },
                    margin: { top: 0, right: 0, bottom: 0, left: 0, header: 0, footer: 0, gutter: 0 },
                },
            },
            children: [
                new Paragraph({
                    spacing: { before: 0, after: 0, line: 1 },
                    children: [
                        new ImageRun({
                            data: page.data,
                            type: 'png',
                            transformation: { width: dimensions.widthPx, height: dimensions.heightPx },
                            floating: {
                                horizontalPosition: { relative: HorizontalPositionRelativeFrom.PAGE, offset: 0 },
                                verticalPosition: { relative: VerticalPositionRelativeFrom.PAGE, offset: 0 },
                                wrap: { type: TextWrappingType.NONE },
                                allowOverlap: true,
                                behindDocument: false,
                            },
                        }),
                    ],
                }),
            ],
        };
        }),
    });
    const blob = await Packer.toBlob(documentFile);
    downloadBlob(blob, `${filename}.docx`);
}

function downloadBlob(blob, filename) {
    const url = URL.createObjectURL(blob);
    const anchor = document.createElement('a');
    anchor.href = url;
    anchor.download = filename;
    anchor.style.display = 'none';
    document.body.appendChild(anchor);
    anchor.click();
    anchor.remove();
    window.setTimeout(() => URL.revokeObjectURL(url), 1000);
}
