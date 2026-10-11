import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { compileScript, compileTemplate, parse } from '@vue/compiler-sfc';
import * as Vue from 'vue';

function renderComponent(name, props, t) {
    const path = `../../resources/js/components/domain/${name}`;
    const { descriptor } = parse(readFileSync(new URL(path, import.meta.url), 'utf8'));
    const script = compileScript(descriptor, { id: 'report-m3-recovery-test', genDefaultAs: 'component' });
    const component = new Function('Vue', `${script.content}\nreturn component;`)(Vue);
    const scope = Vue.effectScope();
    const state = scope.run(() => component.setup(props, { expose() {}, emit() {} }));
    t.after(() => scope.stop());
    const template = compileTemplate({
        source: descriptor.template.content,
        filename: path,
        id: 'report-m3-recovery-test',
        compilerOptions: { bindingMetadata: script.bindings },
    });
    assert.deepEqual(template.errors, []);
    const code = template.code
        .replace(/import \{([^}]+)\} from "vue"/, (_, imports) => `const {${imports.replace(/ as /g, ': ')}} = Vue;`)
        .replace('export function render', 'function render');
    const render = new Function('Vue', `${code}\nreturn render;`)({
        ...Vue, withCtx: fn => fn, withDirectives: vnode => vnode,
    });

    return render({}, [], props, Vue.proxyRefs(state), {}, {});
}

function elements(vnode, type) {
    if (!vnode || typeof vnode !== 'object') return [];
    const children = Array.isArray(vnode.children) ? vnode.children : vnode.children?.default?.() ?? [];
    return [...(vnode.type === type ? [vnode] : []), ...children.flatMap(child => elements(child, type))];
}

function textContent(vnode) {
    if (typeof vnode === 'string' || typeof vnode === 'number') return String(vnode);
    if (Array.isArray(vnode)) return vnode.map(textContent).join('');
    return vnode && typeof vnode === 'object' ? textContent(vnode.children) : '';
}

test('recovery summary renders category labels, totals and saved colors', t => {
    const tree = renderComponent('inspections/ClassificationRecoveryTable.vue', { rows: [
        { category: 'REC', classification_code: 'total', label: 'RECUPERAÇÃO TOTAL REC', color: '#37174F', text_color: '#FFFFFF', defect_count: 2, total_label: 'Total REC [kg]', quantity: '10,25' },
        { category: 'CV', classification_code: 'opportunity', label: 'REPARO POR OPORTUNIDADE CIVIL', color: '#D8C2E8', text_color: '#111827', defect_count: 1, total_label: 'Total CIVIL [m³]', quantity: 'N/A' },
    ] }, t);
    const rows = elements(tree, 'tbody').flatMap(body => elements(body, 'tr'));
    assert.equal(rows.length, 2);
    assert.equal(elements(rows[0], 'td').length, 4);
    assert.equal(textContent(elements(rows[0], 'td')[0]), 'RECUPERAÇÃO TOTAL REC');
    assert.deepEqual(elements(rows[0], 'td')[0].props.style, { backgroundColor: '#37174F', color: '#FFFFFF' });
    assert.equal(textContent(elements(rows[1], 'td')[3]), 'N/A');
    assert.deepEqual(elements(rows[1], 'td')[0].props.style, { backgroundColor: '#D8C2E8', color: '#111827' });
});

function quantityPage(isM3) {
    return { type: 'rec-quantity', annexTitle: 'ANEXO C – QUANTITATIVO GERAL – REC', is_m3: isM3, items: [
        { key: 'one', code: 'REC-001', registered_on: '01/01/2026', project: 'P1', photos: '1 E 2', item: 'Item', element: 'Perfil', quantity: '1', total_weight_label: '10,00', gravity: { label: 'G', score: 2 }, urgency: { score: 3 }, trend: { label: 'T', score: 2 }, gut_score: 12, classification: { code: 'IE-4', color: '#92D050' }, recommendation: { label: 'Reparo Pontual histórico', color: '#7553CC' } },
        { key: 'two', code: 'REC-002', registered_on: '01/01/2026', project: 'P2', photos: '3', item: 'Item', element: 'Perfil', quantity: '1', total_weight_label: '20,00', gravity: { label: 'G', score: 2 }, urgency: { score: 3 }, trend: { label: 'T', score: 2 }, gut_score: 12, classification: { code: 'IE-4', color: '#92D050' }, recommendation: null },
    ] };
}

test('M3 quantity annex adds colored recommendation and neutral legacy fallback', t => {
    const tree = renderComponent('view-first/ReportQuantityPage.vue', { page: quantityPage(true), measuring: false }, t);
    const rows = elements(tree, 'tbody').flatMap(body => elements(body, 'tr'));
    assert.equal(elements(tree, 'th').length, 14);
    assert.equal(elements(rows[0], 'td').length, 14);
    assert.equal(textContent(elements(rows[0], 'td')[13]), 'Reparo Pontual histórico');
    assert.deepEqual(elements(rows[0], 'td')[13].props.style, { backgroundColor: '#7553CC', color: '#ffffff' });
    assert.equal(textContent(elements(rows[1], 'td')[13]), '—');
    assert.deepEqual(elements(rows[1], 'td')[13].props.style, { backgroundColor: '#e2e8f0', color: '#111827' });
});

test('common quantity annex keeps thirteen columns', t => {
    const tree = renderComponent('view-first/ReportQuantityPage.vue', { page: quantityPage(false), measuring: false }, t);
    assert.equal(elements(tree, 'th').length, 13);
    assert.equal(elements(tree, 'tbody').flatMap(body => elements(body, 'td')).length, 26);
});
