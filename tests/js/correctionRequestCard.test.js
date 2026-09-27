import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { compileScript, compileTemplate, parse } from '@vue/compiler-sfc';
import * as Vue from 'vue';

// Exercise the component's actual request lifecycle with a controlled Inertia transport.
const { descriptor } = parse(readFileSync(new URL('../../resources/js/components/domain/inspections/CorrectionRequestCard.vue', import.meta.url), 'utf8'));
const script = compileScript(descriptor, { id: 'correction-card-test', genDefaultAs: 'component' });
const componentSource = script.content
    .replace(/import \{([^}]+)\} from 'vue';/, 'const {$1} = Vue;')
    .replace(/import \{ useForm \} from '@inertiajs\/vue3';/, 'const { useForm } = inertia;');
const template = compileTemplate({
    source: descriptor.template.content,
    filename: 'CorrectionRequestCard.vue',
    id: 'correction-card-test',
    compilerOptions: { bindingMetadata: script.bindings },
});
const renderSource = template.code
    .replace(/import \{([^}]+)\} from "vue"/, (_, imports) => `const {${imports.replace(/ as /g, ': ')}} = Vue;`)
    .replace('export function render', 'function render');
// Inspect real template event handlers and attributes as VNodes; DOM-only directives need no host here.
const renderTemplate = new Function('Vue', `${renderSource}\nreturn render;`)({
    ...Vue,
    withDirectives: vnode => vnode,
    resolveComponent: name => name,
});

function elements(vnode, type) {
    const children = Array.isArray(vnode.children) ? vnode.children : [];
    return [...(vnode.type === type ? [vnode] : []), ...children.flatMap(child => elements(child, type))];
}

function mountCard(t, request = {}) {
    const visits = [];
    const useForm = defaults => {
        const form = Vue.reactive({
            ...defaults,
            errors: {},
            reset() { Object.assign(form, defaults); },
            clearErrors() { form.errors = {}; },
        });
        for (const method of ['post', 'patch', 'delete']) {
            form[method] = (url, options) => {
                const data = Object.fromEntries(Object.keys(defaults).map(key => [key, form[key]]));
                visits.push({ method, url, data, options });
            };
        }
        return form;
    };
    const component = new Function('Vue', 'inertia', `${componentSource}\nreturn component;`)(Vue, { useForm });
    const props = Vue.reactive({
        request: {
            public_id: 'correction-1',
            status: 'addressed',
            close_url: '/corrections/1/close',
            replace_url: '/corrections/1/replace',
            ...request,
        },
    });
    const scope = Vue.effectScope();
    const card = scope.run(() => component.setup(props, { expose() {} }));
    t.after(() => scope.stop());
    const render = () => renderTemplate({}, [], props, Vue.proxyRefs(card), {}, {});
    return { card, props, visits, render };
}

test('closing with an unsent adjustment draft sends one PATCH and clears forms on success', t => {
    const { card, visits, render } = mountCard(t);
    elements(render(), 'button').find(button => button.children === 'Solicitar novo ajuste').props.onClick();
    elements(render(), 'textarea')[0].props['onUpdate:modelValue']('Ajustar a recomendação técnica.');
    assert.equal(visits.length, 0);

    const closeButton = elements(render(), 'button').find(button => button.children === 'Encerrar');
    assert.equal(closeButton.props.type, 'button');
    closeButton.props.onClick();
    closeButton.props.onClick();
    elements(render(), 'form')[0].props.onSubmit({ preventDefault() {} });
    assert.equal(visits.length, 1);
    assert.equal(visits[0].method, 'patch');
    assert.equal(visits[0].url, '/corrections/1/close');
    assert.deepEqual(visits[0].data, {});
    assert.equal(card.processing.value, true);
    assert.equal(card.activeAction.value, 'close');
    assert.ok(elements(render(), 'button').every(button => button.props.disabled));
    assert.ok(elements(render(), 'button').some(button => button.children === 'Encerrando…'));

    visits[0].options.onSuccess();
    assert.equal(card.replacementOpen.value, false);
    assert.equal(card.messageForm.request_message, '');
    assert.equal(elements(render(), 'form').length, 0);
    assert.equal(card.processing.value, true);
    visits[0].options.onFinish();
    assert.equal(card.processing.value, false);
});

test('a failed or cancelled visit unlocks actions without discarding the unsent draft', t => {
    const { card, visits } = mountCard(t);
    card.replacementOpen.value = true;
    card.messageForm.request_message = 'Ajustar a recomendação técnica.';
    card.close();
    visits[0].options.onFinish();

    assert.equal(card.processing.value, false);
    assert.equal(card.replacementOpen.value, true);
    assert.equal(card.messageForm.request_message, 'Ajustar a recomendação técnica.');
    card.close();
    assert.equal(visits.length, 2);
});

test('submitting a new adjustment also prevents a competing closure', t => {
    const { card, visits } = mountCard(t);
    card.messageForm.request_message = 'Ajustar a recomendação técnica.';
    card.submitMessage('/corrections/1/replace');
    card.close();

    assert.equal(visits.length, 1);
    assert.equal(visits[0].method, 'post');
    assert.deepEqual(visits[0].data, { request_message: 'Ajustar a recomendação técnica.' });
    assert.equal(card.processing.value, true);
});

test('refreshed capabilities clear obsolete forms and prevent submissions to missing URLs', async t => {
    const { card, props, visits, render } = mountCard(t);
    card.replacementOpen.value = true;
    card.messageForm.request_message = 'Ajustar a recomendação técnica.';
    card.messageForm.errors = { request: 'Erro anterior.' };
    props.request = { ...props.request, status: 'closed', close_url: null, replace_url: null };
    await Vue.nextTick();

    assert.equal(card.replacementOpen.value, false);
    assert.equal(card.messageForm.request_message, '');
    assert.deepEqual(card.messageForm.errors, {});
    assert.equal(elements(render(), 'form').length, 0);
    assert.equal(elements(render(), 'button').length, 0);
    card.close();
    card.submitMessage(props.request.replace_url);
    assert.equal(visits.length, 0);
});

test('a validation refresh with unchanged capabilities preserves the form and errors', async t => {
    const { card, props } = mountCard(t);
    card.replacementOpen.value = true;
    card.messageForm.request_message = 'Curta';
    card.messageForm.errors = { request_message: 'Informe uma mensagem mais longa.' };
    props.request = { ...props.request };
    await Vue.nextTick();

    assert.equal(card.replacementOpen.value, true);
    assert.equal(card.messageForm.request_message, 'Curta');
    assert.equal(card.messageForm.errors.request_message, 'Informe uma mensagem mais longa.');
});

test('losing an action URL closes its form even when the request status is unchanged', async t => {
    const { card, props } = mountCard(t);
    card.replacementOpen.value = true;
    props.request.replace_url = null;
    await Vue.nextTick();

    assert.equal(card.replacementOpen.value, false);
});

test('forwarding to the inspector asks only for a message and sends it once', t => {
    const { card, visits, render } = mountCard(t, {
        status: 'requested',
        close_url: null,
        replace_url: null,
        create_child_url: '/corrections/1/children',
    });
    elements(render(), 'button').find(button => button.children === 'Solicitar ajuste ao Inspetor').props.onClick();
    const form = elements(render(), 'form')[0];
    assert.equal(elements(form, 'select').length, 0);
    assert.equal(elements(form, 'textarea').length, 1);
    assert.equal(elements(form, 'button').length, 1);
    assert.equal(elements(form, 'button')[0].children, 'Encaminhar ao Inspetor');
    elements(form, 'textarea')[0].props['onUpdate:modelValue']('Atualize a recomendação técnica.');

    form.props.onSubmit({ preventDefault() {} });
    form.props.onSubmit({ preventDefault() {} });
    assert.equal(visits.length, 1);
    assert.equal(visits[0].method, 'post');
    assert.equal(visits[0].url, '/corrections/1/children');
    assert.deepEqual(visits[0].data, { request_message: 'Atualize a recomendação técnica.' });
    assert.ok(elements(render(), 'button').every(button => button.props.disabled));

    visits[0].options.onSuccess();
    visits[0].options.onFinish();
    assert.equal(elements(render(), 'form').length, 0);
    assert.equal(card.childForm.request_message, '');
    assert.equal(card.processing.value, false);
});
