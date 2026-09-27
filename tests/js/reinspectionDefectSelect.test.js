import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { compileScript, parse } from '@vue/compiler-sfc';
import * as Vue from 'vue';

const { descriptor } = parse(readFileSync(new URL('../../resources/js/components/domain/inspections/ReinspectionDefectSelect.vue', import.meta.url), 'utf8'));
const script = compileScript(descriptor, { id: 'reinspection-select-test', genDefaultAs: 'component' });
const source = script.content.replace(/import \{([^}]+)\} from 'vue';/, 'const {$1} = Vue;');

function mountSelect(t, overrides = {}) {
    const calls = [];
    const events = [];
    let cleanup = () => {};
    const component = new Function('Vue', 'fetch', 'window', `${source}\nreturn component;`)(
        { ...Vue, onBeforeUnmount: callback => { cleanup = callback; } },
        (url, options) => new Promise((resolve, reject) => { calls.push({ url, options, resolve, reject }); }),
        { location: { origin: 'http://localhost' } },
    );
    const props = Vue.reactive({
        modelValue: [], equipmentId: 1, inspectionId: null,
        optionsUrl: '/inspections/reinspection-options', initialOptions: null, error: null,
        ...overrides,
    });
    const scope = Vue.effectScope();
    const selector = scope.run(() => component.setup(props, {
        expose() {},
        emit(name, value) {
            events.push({ name, value });
            if (name === 'update:modelValue') props.modelValue = value;
        },
    }));
    t.after(() => { cleanup(); scope.stop(); });
    return { props, selector, calls, events };
}

function payload(ids = [11, 12]) {
    return {
        inspection_type: 'reinspection', previous_inspection_id: 5, selected_ids: ids,
        defects: ids.map((id, index) => ({ id, code: `AV-${id}`, category: index ? 'REC' : 'CV', category_label: index ? 'REC' : 'CIVIL', must_reinspect: false })),
    };
}

async function answer(call, data = payload()) {
    call.resolve({ ok: true, json: async () => data });
    await new Promise(resolve => setImmediate(resolve));
}

test('loads all defaults, allows partial selection and searches without losing selections', async t => {
    const { props, selector, calls, events } = mountSelect(t);
    assert.equal(selector.state.value, 'loading');
    assert.equal(calls[0].url.searchParams.get('equipment_id'), '1');
    await answer(calls[0]);
    assert.deepEqual(props.modelValue, [11, 12]);
    assert.equal(selector.state.value, 'ready');
    assert.ok(events.some(event => event.name === 'base-change' && event.value === 5));
    selector.toggle(selector.options.value.defects[0], false);
    selector.search.value = 'civil';
    assert.deepEqual(selector.visible.value.map(item => item.id), [11]);
    assert.deepEqual(props.modelValue, [12]);
    props.error = 'Erro em outro campo do planejamento';
    await Vue.nextTick();
    assert.deepEqual(props.modelValue, [12]);
    assert.equal(calls.length, 1);
});

test('keeps mandatory defects selected and restores saved planning selection', t => {
    const saved = payload();
    saved.defects[0].must_reinspect = true;
    saved.selected_ids = [11];
    const { props, selector, calls } = mountSelect(t, { initialOptions: saved, inspectionId: 7 });
    assert.equal(calls.length, 0);
    assert.deepEqual(props.modelValue, [11]);
    selector.toggle(saved.defects[0], false);
    assert.deepEqual(props.modelValue, [11]);
    props.equipmentId = 2;
    assert.equal(calls.length, 1);
    assert.equal(calls[0].url.searchParams.get('inspection_id'), '7');
    assert.deepEqual(props.modelValue, []);
});

test('clears all selections under a filter, allows choosing one and selects all including hidden defects', t => {
    const { props, selector } = mountSelect(t, { initialOptions: payload() });
    selector.search.value = 'civil';
    assert.deepEqual(selector.visible.value.map(item => item.id), [11]);

    selector.clearSelection();
    assert.deepEqual(props.modelValue, []);
    assert.equal(selector.search.value, 'civil');
    assert.deepEqual(selector.visible.value.map(item => item.id), [11]);

    selector.toggle(selector.visible.value[0], true);
    assert.deepEqual(props.modelValue, [11]);

    selector.selectAll();
    assert.deepEqual(props.modelValue, [11, 12]);
    assert.equal(selector.search.value, 'civil');

    selector.search.value = '';
    selector.clearSelection();
    assert.deepEqual(props.modelValue, []);
    assert.deepEqual(selector.visible.value.map(item => item.id), [11, 12]);
});

test('searches classification variations, codes and categories without changing selection', t => {
    const data = payload([11, 12, 13, 14]);
    data.defects[0].classification_code = 'TA-2';
    data.defects[1].classification_code = 'TA-1';
    data.defects[2].classification_code = null;
    const { props, selector } = mountSelect(t, { initialOptions: data });
    selector.toggle(data.defects[0], false);

    for (const [query, expected] of [
        ['TA-2', [11]], ['ta-2', [11]], ['TA 2', [11]], ['TA2', [11]],
        ['TA-1', [12]], ['TA-3', []], ['AV-13', [13]], ['av-14', [14]],
        ['civil', [11]], ['CV', [11]], ['rec', [12, 13, 14]], ['', [11, 12, 13, 14]],
    ]) {
        selector.search.value = query;
        assert.deepEqual(selector.visible.value.map(item => item.id), expected, query);
        assert.deepEqual(props.modelValue, [12, 13, 14]);
    }
});

test('ignores an old equipment response even if it arrives after the new one', async t => {
    const { props, selector, calls } = mountSelect(t);
    props.equipmentId = 2;
    assert.equal(calls[0].options.signal.aborted, true);
    await answer(calls[1], payload([21, 22]));
    await answer(calls[0], payload([11, 12]));
    assert.deepEqual(props.modelValue, [21, 22]);
    assert.deepEqual(selector.options.value.selected_ids, [21, 22]);
    assert.equal(selector.state.value, 'ready');
});

test('reports errors to block submission until retry succeeds', async t => {
    const { selector, calls, events } = mountSelect(t);
    calls[0].resolve({ ok: false });
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(selector.state.value, 'error');
    assert.ok(events.some(event => event.name === 'status' && event.value === 'error'));
    const retry = selector.load();
    assert.equal(selector.state.value, 'loading');
    await answer(calls[1]);
    await retry;
    assert.equal(selector.state.value, 'ready');
});

test('selections and pending requests are isolated between batch rows', async t => {
    const first = mountSelect(t);
    const second = mountSelect(t, { equipmentId: 2 });
    await answer(first.calls[0]);
    await answer(second.calls[0], payload([21, 22]));
    first.selector.toggle(first.selector.options.value.defects[0], false);
    assert.deepEqual(first.props.modelValue, [12]);
    assert.deepEqual(second.props.modelValue, [21, 22]);
    first.props.equipmentId = '';
    assert.deepEqual(first.props.modelValue, []);
    assert.equal(first.selector.state.value, 'ready');
    assert.deepEqual(second.props.modelValue, [21, 22]);
});
