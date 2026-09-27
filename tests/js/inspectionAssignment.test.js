import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { compileScript, compileTemplate, parse } from '@vue/compiler-sfc';
import * as Vue from 'vue';
import { eligibleAssignmentUsers } from '../../resources/js/lib/inspectionAssignment.js';

function mount(t, path, inputProps, transport = {}) {
    const { descriptor } = parse(readFileSync(new URL(`../../resources/js/${path}`, import.meta.url), 'utf8'));
    const script = compileScript(descriptor, { id: 'assignment-test', genDefaultAs: 'component' });
    const source = script.content
        .replace(/import \{([^}]+)\} from 'vue';/, 'const {$1} = Vue;')
        .replace(/import \{([^}]+)\} from '@inertiajs\/vue3';/, 'const {$1} = inertia;')
        .replace(/import \{ eligibleAssignmentUsers \} from '[^']+';/, '')
        .replace(/import (\w+) from '[^']+\.vue';/g, 'const $1 = "$1";');
    const visits = [];
    const inertia = {
        router: { get: (url, data, options) => visits.push({ url, data, options }) },
        useForm: defaults => Vue.reactive({
            ...defaults,
            errors: {},
            post: (url, options) => visits.push({ url, data: defaults, options }),
        }),
        ...transport,
    };
    const component = new Function('Vue', 'inertia', 'eligibleAssignmentUsers', `${source}\nreturn component;`)(Vue, inertia, eligibleAssignmentUsers);
    const props = Vue.reactive(inputProps);
    const scope = Vue.effectScope();
    const state = scope.run(() => component.setup(props, { expose() {} }));
    t.after(() => scope.stop());
    const template = compileTemplate({ source: descriptor.template.content, filename: path, id: 'assignment-test', compilerOptions: { bindingMetadata: script.bindings } });
    assert.deepEqual(template.errors, []);
    const renderSource = template.code
        .replace(/import \{([^}]+)\} from "vue"/, (_, imports) => `const {${imports.replace(/ as /g, ': ')}} = Vue;`)
        .replace('export function render', 'function render');
    const renderTemplate = new Function('Vue', `${renderSource}\nreturn render;`)({ ...Vue, withDirectives: vnode => vnode });
    return { props, state, visits, render: () => renderTemplate({}, [], props, Vue.proxyRefs(state), {}, {}) };
}

test('self-assignment sends no identity or role, blocks rapid clicks, and unlocks after a failed visit', t => {
    const { state, visits, render } = mount(t, 'components/domain/inspections/SelfAssignmentButton.vue', {
        capability: { action: '/inspections/1/self-assign', label: 'Assumir como Revisor' },
    });
    const button = render();
    assert.equal(button.children.trim(), 'Assumir como Revisor');
    assert.equal(button.props.type, 'button');
    button.props.onClick();
    button.props.onClick();
    assert.equal(visits.length, 1);
    assert.equal(visits[0].url, '/inspections/1/self-assign');
    assert.deepEqual(visits[0].data, {});
    assert.equal(render().props.disabled, true);
    assert.equal(render().children.trim(), 'Assumindo…');
    visits[0].options.onFinish();
    assert.equal(render().props.disabled, false);
    state.submit();
    assert.equal(visits.length, 2);
});

test('missing capability hides and prevents self-assignment', t => {
    const { state, visits, render } = mount(t, 'components/domain/inspections/SelfAssignmentButton.vue', { capability: null });
    state.submit();
    assert.equal(visits.length, 0);
    assert.equal(render().type, Vue.Comment);
});

test('switching queue keeps search and stage, removes responsible filters and resets pagination', t => {
    const { state, props, visits } = mount(t, 'pages/Inspections/Index.vue', {
        filters: { scope: 'mine', search: 'Bomba', status: 'in_review', responsible: '3', responsibility: 'approver', page: 2 },
        capabilities: { available_queue: true }, inspections: { data: [], links: [] }, options: { statuses: [] }, create_url: '/inspections/create',
    });
    state.changeScope('available');
    assert.deepEqual(visits[0].data, { search: 'Bomba', status: 'in_review', scope: 'available' });
    props.filters.scope = 'available';
    state.applyFilters();
    assert.deepEqual(visits[1].data, visits[0].data);
    state.clearFilters();
    assert.deepEqual(visits[2].data, { scope: 'available' });
});

test('manual assignment offers only the matching operational role and clears an incompatible selection', async t => {
    const users = [
        { id: 1, name: 'Revisor', account_type: 'member', operational_role: 'reviewer' },
        { id: 2, name: 'Liberador', account_type: 'member', operational_role: 'releaser' },
        { id: 3, name: 'Admin', account_type: 'company_admin', operational_role: 'reviewer' },
        { id: 4, name: 'Inspetor', account_type: 'member', operational_role: 'inspector' },
    ];
    const { state } = mount(t, 'components/domain/inspections/AssignmentForm.vue', { users, roles: [], action: '/responsibles' });
    state.form.responsibility = 'approver';
    state.form.user_id = 1;
    await Vue.nextTick();
    assert.deepEqual(state.eligibleUsers.value.map(user => user.id), [1]);
    assert.equal(state.form.user_id, 1);
    state.form.responsibility = 'releaser';
    await Vue.nextTick();
    assert.deepEqual(state.eligibleUsers.value.map(user => user.id), [2]);
    assert.equal(state.form.user_id, '');
    assert.deepEqual(eligibleAssignmentUsers(users, 'preparer'), users);
});
