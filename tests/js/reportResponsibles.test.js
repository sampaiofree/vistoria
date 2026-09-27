import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { compileScript, compileTemplate, parse } from '@vue/compiler-sfc';
import * as Vue from 'vue';

function mount(t) {
    const path = '../../resources/js/pages/Settings/InspectionReports/Responsibles.vue';
    const { descriptor } = parse(readFileSync(new URL(path, import.meta.url), 'utf8'));
    const script = compileScript(descriptor, { id: 'report-responsibles-test', genDefaultAs: 'component' });
    const source = script.content
        .replace(/import \{([^}]+)\} from 'vue';/, 'const {$1} = Vue;')
        .replace(/import \{ useForm \} from '@inertiajs\/vue3';/, 'const { useForm } = inertia;')
        .replace(/import AppLayout from '[^']+';/, 'const AppLayout = "AppLayout";');
    const visits = [];
    const useForm = initial => {
        let defaults = { ...initial };
        const form = Vue.reactive({
            ...initial, errors: {},
            defaults(values) { defaults = values; },
            reset() { Object.assign(form, defaults); },
            put(url, options) {
                visits.push({ url, options, data: Object.fromEntries(Object.keys(initial).map(key => [key, form[key]])) });
            },
        });
        return form;
    };
    const component = new Function('Vue', 'inertia', `${source}\nreturn component;`)(Vue, { useForm });
    const props = Vue.reactive({ names: { report_reviewer_name: null, report_releaser_name: null }, action: '/settings/inspection-report/responsibles' });
    const scope = Vue.effectScope();
    const state = scope.run(() => component.setup(props, { expose() {} }));
    t.after(() => scope.stop());
    const template = compileTemplate({ source: descriptor.template.content, filename: path, id: 'report-responsibles-test', compilerOptions: { bindingMetadata: script.bindings } });
    assert.deepEqual(template.errors, []);
    const code = template.code
        .replace(/import \{([^}]+)\} from "vue"/, (_, imports) => `const {${imports.replace(/ as /g, ': ')}} = Vue;`)
        .replace('export function render', 'function render');
    const render = new Function('Vue', `${code}\nreturn render;`)({ ...Vue, withCtx: fn => fn, withDirectives: vnode => vnode });
    return { props, state, visits, render: () => render({}, [], props, Vue.proxyRefs(state), {}, {}) };
}

function elements(vnode, type) {
    if (!vnode || typeof vnode !== 'object') return [];
    const children = Array.isArray(vnode.children) ? vnode.children : vnode.children?.default?.() ?? [];
    return [...(vnode.type === type ? [vnode] : []), ...children.flatMap(child => elements(child, type))];
}

test('settings form submits only document names once and displays normalized saved values', t => {
    const { props, state, visits, render } = mount(t);
    const inputs = elements(render(), 'input');
    assert.equal(inputs.length, 2);
    assert.ok(inputs.every(input => input.props.maxlength === '150' && !input.props.required));
    inputs[0].props['onUpdate:modelValue']('  João   Silva ');
    inputs[1].props['onUpdate:modelValue']('Lúcia');
    const submit = elements(render(), 'form')[0].props.onSubmit;
    submit({ preventDefault() {} });
    submit({ preventDefault() {} });
    assert.equal(visits.length, 1);
    assert.equal(visits[0].url, props.action);
    assert.deepEqual(visits[0].data, { report_reviewer_name: '  João   Silva ', report_releaser_name: 'Lúcia' });
    assert.equal(elements(render(), 'button')[0].props.disabled, true);
    assert.equal(elements(render(), 'button')[0].children, 'Salvando…');
    props.names = { report_reviewer_name: 'João Silva', report_releaser_name: 'Lúcia' };
    visits[0].options.onSuccess();
    visits[0].options.onFinish();
    assert.equal(state.form.report_reviewer_name, 'João Silva');
    assert.equal(elements(render(), 'button')[0].props.disabled, false);
});

test('validation failure preserves names and allows correcting and clearing them', t => {
    const { state, visits, render } = mount(t);
    state.form.report_reviewer_name = 'a'.repeat(151);
    state.submit();
    state.form.errors.report_reviewer_name = 'O nome deve ter até 150 caracteres.';
    visits[0].options.onFinish();
    assert.equal(state.form.report_reviewer_name.length, 151);
    assert.ok(elements(render(), 'span').some(span => span.children === state.form.errors.report_reviewer_name));
    state.form.report_reviewer_name = '';
    state.form.report_releaser_name = '';
    state.submit();
    assert.equal(visits.length, 2);
    assert.deepEqual(visits[1].data, { report_reviewer_name: '', report_releaser_name: '' });
});
