function clone(value) {
    return JSON.parse(JSON.stringify(value));
}

function visitNodes(node, callback) {
    if (!node || typeof node !== 'object') return;

    callback(node);
    (node.content || []).forEach((child) => visitNodes(child, callback));
}

export function nodeText(node) {
    const parts = [];

    visitNodes(node, (child) => {
        if (child.type === 'text') parts.push(child.text || '');
        if (child.type === 'hardBreak') parts.push(' ');
    });

    return parts.join('').replace(/\s+/gu, ' ').trim();
}

export function numberGeneralAspectsDocument(document) {
    if (!document) return null;

    const numbered = clone(document);
    let sequence = 0;

    visitNodes(numbered, (node) => {
        if (node.type !== 'heading') return;

        sequence += 1;
        node.reportNumber = `2.${sequence}`;
    });

    return numbered;
}

function headingEntries(document, pageNumber) {
    const entries = [];

    visitNodes(document, (node) => {
        if (node.type !== 'heading' || !node.reportNumber) return;

        const title = nodeText(node);
        if (!title) return;

        entries.push({
            key: `general-aspects-heading-${node.reportNumber}`,
            title: `${node.reportNumber} ${title}`,
            page: pageNumber,
            kind: 'subsection',
        });
    });

    return entries;
}

export function buildReportSummaryEntries(contentPages, summaryPageCount, overviewTitle) {
    const entries = [];
    const includedHeadings = new Set();
    let generalAspectsIncluded = false;

    contentPages.forEach((page, index) => {
        const pageNumber = index + summaryPageCount + 2;

        if (page.type === 'general-aspects') {
            if (!generalAspectsIncluded) {
                entries.push({
                    key: 'general-aspects',
                    title: '2 DESCRIÇÃO DOS ASPECTOS GERAIS DO EQUIPAMENTO',
                    page: pageNumber,
                    kind: 'section',
                });
                generalAspectsIncluded = true;
            }

            headingEntries(page.document, pageNumber).forEach((entry) => {
                if (includedHeadings.has(entry.key)) return;

                includedHeadings.add(entry.key);
                entries.push(entry);
            });
        }

        if (page.type === 'overview' || (page.annexTitle && !page.continuation && ['location-map', 'rec-quantity', 'civil-quantity'].includes(page.type))) {
            entries.push({
                key: page.type === 'overview' ? 'annex-a' : `annex-${page.key}`,
                title: page.annexTitle || overviewTitle || 'ANEXO A – LOCALIZAÇÃO E DOCUMENTAÇÃO FOTOGRÁFICA - TAC',
                page: pageNumber,
                kind: 'annex',
            });
        }

        if (page.type === 'classification-summary') {
            entries.push({
                key: 'classification-summary',
                title: '1 RESUMO DA CLASSIFICAÇÃO DO EQUIPAMENTO – GUT',
                page: pageNumber,
                kind: 'section',
            });
        }

        if (page.type === 'textual-findings' && !page.continuation) {
            entries.push({
                key: 'textual-findings',
                title: 'REGISTROS SEM EVIDÊNCIA FOTOGRÁFICA',
                page: pageNumber,
                kind: 'section',
            });
        }

    });

    return entries;
}

export function reportSummarySignature(entries) {
    return entries.map((entry) => `${entry.key}:${entry.page}:${entry.title}`).join('|');
}
