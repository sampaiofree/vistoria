const A4_WIDTH_MM = 210;
const A4_HEIGHT_MM = 297;
const A4_WIDTH_PX = 794;
const A4_HEIGHT_PX = 1123;
const A4_WIDTH_TWIPS = 11906;
const A4_HEIGHT_TWIPS = 16838;

function nextFrame() {
    return new Promise((resolve) => requestAnimationFrame(() => requestAnimationFrame(resolve)));
}

async function waitForImages(elements) {
    const images = elements.flatMap((element) => [...element.querySelectorAll('img')]);

    await Promise.all(images.map((image) => {
        if (image.complete) return Promise.resolve();

        return new Promise((resolve) => {
            image.addEventListener('load', resolve, { once: true });
            image.addEventListener('error', resolve, { once: true });
        });
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
    await waitForImages(elements);
    await nextFrame();

    const { default: html2canvas } = await import('html2canvas');
    const pages = [];

    for (let index = 0; index < elements.length; index += 1) {
        onProgress({ current: index + 1, total: elements.length });
        const canvas = await html2canvas(elements[index], {
            backgroundColor: '#FFFFFF',
            scale: 2,
            useCORS: true,
            allowTaint: false,
            logging: false,
            imageTimeout: 30000,
            removeContainer: true,
            onclone: (clonedDocument) => {
                clonedDocument.documentElement.style.setProperty('background-color', '#FFFFFF', 'important');
                clonedDocument.body.style.setProperty('background-color', '#FFFFFF', 'important');
                clonedDocument.querySelector('.report-preview-pages')?.classList.add('report-exporting');
            },
        });
        const blob = await pngBlob(canvas);
        pages.push(new Uint8Array(await blob.arrayBuffer()));
        canvas.width = 1;
        canvas.height = 1;
    }

    return pages;
}

export async function downloadReportPdf(pages, filename) {
    const { jsPDF } = await import('jspdf');
    const pdf = new jsPDF({
        orientation: 'portrait',
        unit: 'mm',
        format: 'a4',
        compress: true,
        putOnlyUsedFonts: true,
    });

    pages.forEach((page, index) => {
        if (index > 0) pdf.addPage('a4', 'portrait');
        pdf.addImage(page, 'PNG', 0, 0, A4_WIDTH_MM, A4_HEIGHT_MM, undefined, 'FAST');
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
        sections: pages.map((page, index) => ({
            properties: {
                type: index === 0 ? undefined : SectionType.NEXT_PAGE,
                page: {
                    size: { width: A4_WIDTH_TWIPS, height: A4_HEIGHT_TWIPS },
                    margin: { top: 0, right: 0, bottom: 0, left: 0, header: 0, footer: 0, gutter: 0 },
                },
            },
            children: [
                new Paragraph({
                    spacing: { before: 0, after: 0, line: 1 },
                    children: [
                        new ImageRun({
                            data: page,
                            type: 'png',
                            transformation: { width: A4_WIDTH_PX, height: A4_HEIGHT_PX },
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
        })),
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
