import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { compileScript, compileTemplate, parse } from '@vue/compiler-sfc';
import * as Vue from 'vue';

function mount(relativePath, props, t) {
    const path = `../../resources/js/components/domain/inspections/${relativePath}`;
    const { descriptor } = parse(readFileSync(new URL(path, import.meta.url), 'utf8'));
    const script = compileScript(descriptor, { id: 'classification-m3-test', genDefaultAs: 'component' });
    const source = script.content.replace(/import \{ computed \} from 'vue';/, 'const { computed } = Vue;');
    const component = new Function('Vue', `${source}\nreturn component;`)(Vue);
    const scope = Vue.effectScope();
    const state = scope.run(() => component.setup(props, { expose() {}, emit() {} }));
    t.after(() => scope.stop());

    const template = compileTemplate({
        source: descriptor.template.content,
        filename: path,
        id: 'classification-m3-test',
        compilerOptions: { bindingMetadata: script.bindings },
    });
    assert.deepEqual(template.errors, []);
    const code = template.code
        .replace(/import \{([^}]+)\} from "vue"/, (_, imports) => `const {${imports.replace(/ as /g, ': ')}} = Vue;`)
        .replace('export function render', 'function render');
    const render = new Function('Vue', `${code}\nreturn render;`)({ ...Vue, withCtx: fn => fn, withDirectives: vnode => vnode });

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

function summary(isM3) {
    return {
        is_m3: isM3,
        categories: [{
            code: 'CV', report_name: 'CIVIL', most_critical: 'CV-2', most_critical_color: '#FFC000',
            report_total: { display: '2,00', value: 2, unit: 'm³' },
            report_rows: [{ classification_code: 'CV-2', color: '#FFC000', defect_count: 1,
                m2_due_date: '01/01/2027', quantity: { display: '2,00' }, sap_m2_number: '12345678' }],
        }],
    };
}

test('M3 classification summary has five columns and a correctly aligned total', t => {
    const tree = mount('ClassificationSummaryTable.vue', { summary: summary(true), categories: null,
        editable: true, m2Links: [], m2Errors: {}, variant: 'report' }, t);
    assert.equal(elements(tree, 'th').length, 5);
    assert.ok(!elements(tree, 'th').some(cell => cell.children === 'NOTA M2 (SAP)'));
    const rows = elements(tree, 'tr');
    assert.equal(elements(rows[1], 'td').length, 5);
    assert.deepEqual(elements(rows[2], 'td').map(cell => Number(cell.props?.colspan ?? 1)), [2, 1, 1, 1]);
    assert.equal(elements(tree, 'input').length, 0);
});

test('common classification summary keeps its M2 column', t => {
    const tree = mount('ClassificationSummaryTable.vue', { summary: summary(false), categories: null,
        editable: false, m2Links: [], m2Errors: {}, variant: 'report' }, t);
    assert.equal(elements(tree, 'th').length, 6);
    assert.equal(elements(tree, 'th')[5].children, 'NOTA M2 (SAP)');
    assert.deepEqual(elements(tree, 'tr')[2] && elements(elements(tree, 'tr')[2], 'td').map(cell => Number(cell.props?.colspan ?? 1)), [2, 1, 2, 1]);
});

test('M3 special treatment renders fixed service in three columns without editing', t => {
    const tree = mount('ClassificationSpecialAssessmentTable.vue', { rows: [{
        assessment_public_id: 'assessment-1', code: 'CV-1', photos: 'ANEXO B - 1 E 2',
        service: 'Análise de Engenharia', priority: null, note: null,
    }], editable: true, values: [], variant: 'report', m3: true }, t);
    assert.equal(elements(tree, 'th').length, 4);
    assert.deepEqual(elements(tree, 'th').slice(1).map(cell => cell.children), ['TIPO', 'FOTOS', 'SERVIÇO']);
    assert.equal(elements(tree, 'tbody')[0] && elements(elements(tree, 'tbody')[0], 'td').length, 3);
    assert.ok(elements(tree, 'td').some(cell => textContent(cell).includes('Análise de Engenharia')));
    assert.equal(elements(tree, 'input').length, 0);
});

test('common special treatment keeps five columns and three editable fields', t => {
    const tree = mount('ClassificationSpecialAssessmentTable.vue', { rows: [{
        assessment_public_id: 'assessment-1', code: 'CV-1', photos: 'ANEXO B - 1 E 2',
        service: 'Serviço', priority: 'Alta', note: 'Nota',
    }], editable: true, values: [], variant: 'workspace', m3: false }, t);
    assert.equal(elements(tree, 'th').length, 6);
    assert.equal(elements(tree, 'input').length, 3);
});
