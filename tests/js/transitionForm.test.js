import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { compileScript, compileTemplate, parse } from '@vue/compiler-sfc';
import * as Vue from 'vue';

const { descriptor } = parse(readFileSync(new URL('../../resources/js/components/domain/inspections/TransitionForm.vue', import.meta.url), 'utf8'));
const script = compileScript(descriptor, { id: 'transition-form-test', genDefaultAs: 'component' });
const componentSource = script.content
    .replace(/import \{([^}]+)\} from 'vue';/, 'const {$1} = Vue;')
    .replace(/import \{ useForm \} from '@inertiajs\/vue3';/, 'const { useForm } = inertia;');
const template = compileTemplate({
    source: descriptor.template.content,
    filename: 'TransitionForm.vue',
    id: 'transition-form-test',
    compilerOptions: { bindingMetadata: script.bindings },
});
assert.deepEqual(template.errors, []);
const renderSource = template.code
    .replace(/import \{([^}]+)\} from "vue"/, (_, imports) => `const {${imports.replace(/ as /g, ': ')}} = Vue;`)
    .replace('export function render', 'function render');
const renderTemplate = new Function('Vue', `${renderSource}\nreturn render;`)({ ...Vue, withDirectives: vnode => vnode });

function elements(vnode, type) {
    const children = Array.isArray(vnode.children) ? vnode.children : [];
    return [...(vnode.type === type ? [vnode] : []), ...children.flatMap(child => elements(child, type))];
}

function mount(t, transition) {
    const visits = [];
    const useForm = defaults => {
        let transform = data => data;
        const form = Vue.reactive({
            ...defaults,
            errors: {},
            processing: false,
            reset() { Object.assign(form, defaults); },
            transform(callback) { transform = callback; return form; },
            post(url, options) {
                const data = Object.fromEntries(Object.keys(defaults).map(key => [key, form[key]]));
                visits.push({ url, data: transform(data), options });
            },
        });
        return form;
    };
    const component = new Function('Vue', 'inertia', `${componentSource}\nreturn component;`)(Vue, { useForm });
    const props = Vue.reactive({ transition });
    const scope = Vue.effectScope();
    const state = scope.run(() => component.setup(props, { expose() {} }));
    t.after(() => scope.stop());
    return { state, visits, render: () => renderTemplate({}, [], props, Vue.proxyRefs(state), {}, {}) };
}

test('reviewer chooses planner for a general correction and sends the selected target', t => {
    const { state, visits, render } = mount(t, {
        key: 'return_for_correction',
        label: 'Enviar para correção',
        action: '/inspections/1/return-for-correction',
        correction_message: true,
        correction_targets: [
            { value: 'inspector', label: 'Inspetor' },
            { value: 'planner', label: 'Planejador' },
        ],
        marked_planner_general_request: false,
    });
    assert.equal(state.form.correction_target, 'inspector');
    assert.equal(elements(render(), 'select').length, 1);

    elements(render(), 'select')[0].props['onUpdate:modelValue']('planner');
    assert.equal(elements(render(), 'textarea')[0].props.required, true);
    elements(render(), 'textarea')[0].props['onUpdate:modelValue']('Confira as notas do relatório.');
    elements(render(), 'form')[0].props.onSubmit({ preventDefault() {} });

    assert.deepEqual(visits[0].data, {
        justification: 'Confira as notas do relatório.',
        correction_target: 'planner',
    });
    visits[0].options.onSuccess();
    assert.equal(state.form.correction_target, 'inspector');
});

test('a marked planner replacement can be resent without another message', t => {
    const { state, visits, render } = mount(t, {
        key: 'return_for_correction',
        action: '/inspections/1/return-for-correction',
        correction_message: true,
        correction_targets: [
            { value: 'inspector', label: 'Inspetor' },
            { value: 'planner', label: 'Planejador' },
        ],
        marked_planner_general_request: true,
    });
    state.form.correction_target = 'planner';
    assert.equal(elements(render(), 'textarea')[0].props.required, false);
    state.submit();
    assert.deepEqual(visits[0].data, { justification: '', correction_target: 'planner' });
});

test('other transitions keep their existing payload and have no target selector', t => {
    const { state, visits, render } = mount(t, {
        key: 'return_for_correction',
        action: '/inspections/1/return-for-correction',
        correction_message: true,
    });
    assert.equal(elements(render(), 'select').length, 0);
    state.form.justification = 'Confira esta avaria.';
    state.submit();
    assert.deepEqual(visits[0].data, { justification: 'Confira esta avaria.' });
});
