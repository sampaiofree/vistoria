import assert from 'node:assert/strict';
import test from 'node:test';
import {
    buildReportSummaryEntries,
    numberGeneralAspectsDocument,
    reportSummarySignature,
} from '../../resources/js/lib/reportSummary.js';

test('numbers only semantic headings and preserves a typed number in the title', () => {
    const document = numberGeneralAspectsDocument({
        type: 'doc',
        content: [
            { type: 'paragraph', content: [{ type: 'text', text: '2.1 Parágrafo antigo' }] },
            {
                type: 'heading',
                attrs: { level: 2 },
                content: [{ type: 'text', text: '2.1 Título digitado' }],
            },
            {
                type: 'heading',
                attrs: { level: 3 },
                content: [{ type: 'text', text: 'Conclusão' }],
            },
        ],
    });

    assert.equal(document.content[0].reportNumber, undefined);
    assert.equal(document.content[1].reportNumber, '2.1');
    assert.equal(document.content[2].reportNumber, '2.2');

    const entries = buildReportSummaryEntries([
        { type: 'general-aspects', document },
        { type: 'overview' },
        { type: 'photographic' },
        { type: 'location-map', key: 'rec-map', annexTitle: 'ANEXO B – LOCALIZAÇÃO E DOCUMENTAÇÃO FOTOGRÁFICA - REC' },
    ], 1);

    assert.deepEqual(entries.map(({ title, page }) => ({ title, page })), [
        { title: '2 DESCRIÇÃO DOS ASPECTOS GERAIS DO EQUIPAMENTO', page: 3 },
        { title: '2.1 2.1 Título digitado', page: 3 },
        { title: '2.2 Conclusão', page: 3 },
        { title: 'ANEXO A – LOCALIZAÇÃO E DOCUMENTAÇÃO FOTOGRÁFICA - TAC', page: 4 },
        { title: 'ANEXO B – LOCALIZAÇÃO E DOCUMENTAÇÃO FOTOGRÁFICA - REC', page: 6 },
    ]);
    assert.ok(entries.every((entry) => !entry.title.includes('RESUMO DA CLASSIFICAÇÃO')));
    assert.ok(entries.every((entry) => !entry.title.includes('Parágrafo antigo')));
});

test('shifts every destination when the summary gains another page', () => {
    const contentPages = [
        { type: 'overview' },
        { type: 'location-map', key: 'civil-map', annexTitle: 'ANEXO B – LOCALIZAÇÃO E DOCUMENTAÇÃO FOTOGRÁFICA - CIVIL' },
    ];
    const onePage = buildReportSummaryEntries(contentPages, 1);
    const twoPages = buildReportSummaryEntries(contentPages, 2);

    assert.deepEqual(onePage.map((entry) => entry.page), [3, 4]);
    assert.deepEqual(twoPages.map((entry) => entry.page), [4, 5]);
    assert.notEqual(reportSummarySignature(onePage), reportSummarySignature(twoPages));
});

test('includes defect evolution and textual findings only on their first pages', () => {
    const entries = buildReportSummaryEntries([
        { type: 'defect-evolution', continuation: false },
        { type: 'defect-evolution', continuation: true },
        { type: 'overview' },
        { type: 'textual-findings', continuation: false },
        { type: 'textual-findings', continuation: true },
    ], 1);

    assert.deepEqual(entries.map(({ key, title, page }) => ({ key, title, page })), [
        {
            key: 'defect-evolution',
            title: '3 QUADRO DE EVOLUÇÃO DAS AVARIAS',
            page: 3,
        },
        {
            key: 'annex-a',
            title: 'ANEXO A – LOCALIZAÇÃO E DOCUMENTAÇÃO FOTOGRÁFICA - TAC',
            page: 5,
        },
        {
            key: 'textual-findings',
            title: 'REGISTROS SEM EVIDÊNCIA FOTOGRÁFICA',
            page: 6,
        },
    ]);
});
