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
        { type: 'classification-summary' },
        { type: 'general-aspects', document },
        { type: 'overview' },
        { type: 'photographic' },
        { type: 'location-map', key: 'rec-map', annexTitle: 'ANEXO B – LOCALIZAÇÃO E DOCUMENTAÇÃO FOTOGRÁFICA - REC' },
    ], 1);

    assert.deepEqual(entries.map(({ title, page }) => ({ title, page })), [
        { title: '1 RESUMO DA CLASSIFICAÇÃO DO EQUIPAMENTO – GUT', page: 3 },
        { title: '2 DESCRIÇÃO DOS ASPECTOS GERAIS DO EQUIPAMENTO', page: 4 },
        { title: '2.1 2.1 Título digitado', page: 4 },
        { title: '2.2 Conclusão', page: 4 },
        { title: 'ANEXO A – LOCALIZAÇÃO E DOCUMENTAÇÃO FOTOGRÁFICA - TAC', page: 5 },
        { title: 'ANEXO B – LOCALIZAÇÃO E DOCUMENTAÇÃO FOTOGRÁFICA - REC', page: 7 },
    ]);
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

test('omits defect evolution and includes textual findings only on their first pages', () => {
    const entries = buildReportSummaryEntries([
        { type: 'defect-evolution', continuation: false },
        { type: 'defect-evolution', continuation: true },
        { type: 'overview' },
        { type: 'textual-findings', continuation: false },
        { type: 'textual-findings', continuation: true },
    ], 1);

    assert.deepEqual(entries.map(({ key, title, page }) => ({ key, title, page })), [
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
    assert.ok(entries.every((entry) => !entry.title.includes('QUADRO DE EVOLUÇÃO DAS AVARIAS')));
});

test('includes the REC quantity annex only on its first landscape page', () => {
    const entries = buildReportSummaryEntries([
        { type: 'overview', key: 'report-overview', annexTitle: 'ANEXO A – LOCALIZAÇÃO E DOCUMENTAÇÃO FOTOGRÁFICA - TAC' },
        { type: 'location-map', key: 'rec-map', annexTitle: 'ANEXO B – LOCALIZAÇÃO E DOCUMENTAÇÃO FOTOGRÁFICA - REC' },
        { type: 'rec-quantity', key: 'rec-quantity-0', annexTitle: 'ANEXO C – QUANTITATIVO GERAL – REC', continuation: false },
        { type: 'rec-quantity', key: 'rec-quantity-1', annexTitle: 'ANEXO C – QUANTITATIVO GERAL – REC', continuation: true },
        { type: 'location-map', key: 'civil-map', annexTitle: 'ANEXO D – LOCALIZAÇÃO E DOCUMENTAÇÃO FOTOGRÁFICA - CIVIL' },
    ], 1);

    assert.deepEqual(entries.map(({ title, page }) => ({ title, page })), [
        { title: 'ANEXO A – LOCALIZAÇÃO E DOCUMENTAÇÃO FOTOGRÁFICA - TAC', page: 3 },
        { title: 'ANEXO B – LOCALIZAÇÃO E DOCUMENTAÇÃO FOTOGRÁFICA - REC', page: 4 },
        { title: 'ANEXO C – QUANTITATIVO GERAL – REC', page: 5 },
        { title: 'ANEXO D – LOCALIZAÇÃO E DOCUMENTAÇÃO FOTOGRÁFICA - CIVIL', page: 7 },
    ]);
});

test('includes the CIVIL quantity annex only once and keeps subsequent annex destinations', () => {
    const entries = buildReportSummaryEntries([
        { type: 'overview', key: 'report-overview', annexTitle: 'ANEXO A – LOCALIZAÇÃO E DOCUMENTAÇÃO FOTOGRÁFICA - TAC' },
        { type: 'location-map', key: 'rec-map', annexTitle: 'ANEXO B – LOCALIZAÇÃO E DOCUMENTAÇÃO FOTOGRÁFICA - REC' },
        { type: 'rec-quantity', key: 'rec-quantity-0', annexTitle: 'ANEXO C – QUANTITATIVO GERAL – REC', continuation: false },
        { type: 'location-map', key: 'civil-map', annexTitle: 'ANEXO D – LOCALIZAÇÃO E DOCUMENTAÇÃO FOTOGRÁFICA - CIVIL' },
        { type: 'civil-quantity', key: 'civil-quantity-0', annexTitle: 'ANEXO E – QUANTITATIVO GERAL – CIVIL', continuation: false },
        { type: 'civil-quantity', key: 'civil-quantity-1', annexTitle: 'ANEXO E – QUANTITATIVO GERAL – CIVIL', continuation: true },
        { type: 'location-map', key: 'tel-map', annexTitle: 'ANEXO F – LOCALIZAÇÃO E DOCUMENTAÇÃO FOTOGRÁFICA - TEL' },
    ], 1);

    assert.deepEqual(entries.map(({ title, page }) => ({ title, page })), [
        { title: 'ANEXO A – LOCALIZAÇÃO E DOCUMENTAÇÃO FOTOGRÁFICA - TAC', page: 3 },
        { title: 'ANEXO B – LOCALIZAÇÃO E DOCUMENTAÇÃO FOTOGRÁFICA - REC', page: 4 },
        { title: 'ANEXO C – QUANTITATIVO GERAL – REC', page: 5 },
        { title: 'ANEXO D – LOCALIZAÇÃO E DOCUMENTAÇÃO FOTOGRÁFICA - CIVIL', page: 6 },
        { title: 'ANEXO E – QUANTITATIVO GERAL – CIVIL', page: 7 },
        { title: 'ANEXO F – LOCALIZAÇÃO E DOCUMENTAÇÃO FOTOGRÁFICA - TEL', page: 9 },
    ]);
});
