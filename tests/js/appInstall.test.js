import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { compileScript, compileTemplate, parse } from '@vue/compiler-sfc';
import * as Vue from 'vue';
import { createAppInstall } from '../../resources/js/lib/appInstall.js';

function browser({ android = true, standalone = false } = {}) {
    const target = new EventTarget();
    const displayMode = new EventTarget();
    displayMode.matches = standalone;
    target.navigator = { userAgent: android ? 'Mozilla/5.0 (Linux; Android 14)' : 'Mozilla/5.0 (Macintosh)' };
    target.matchMedia = () => displayMode;
    return { target, displayMode };
}

function setup(t, options) {
    const { target, displayMode } = browser(options);
    const app = createAppInstall(target);
    app.start();
    t.after(() => app.stop());
    return { app, target, displayMode };
}

function offer(target, choice = Promise.resolve({ outcome: 'dismissed' }), promptError = null) {
    const event = new Event('beforeinstallprompt', { cancelable: true });
    let calls = 0;
    event.prompt = async () => {
        calls += 1;
        if (promptError) throw promptError;
    };
    event.userChoice = choice;
    target.dispatchEvent(event);
    return { event, calls: () => calls };
}

function dashboard(t, app, overrides = {}) {
    const { descriptor } = parse(readFileSync(new URL('../../resources/js/pages/Dashboard/Index.vue', import.meta.url), 'utf8'));
    const script = compileScript(descriptor, { id: 'pwa-dashboard', genDefaultAs: 'component' });
    const source = script.content
        .replace(/import \{([^}]+)\} from 'vue';/, 'const {$1} = Vue;')
        .replace(/import \{([^}]+)\} from '@inertiajs\/vue3';/, 'const {$1} = inertia;')
        .replace(/import \{ appInstall \} from '[^']+';/, '')
        .replace(/import (\w+) from '[^']+\.vue';/g, 'const $1 = { name: "$1" };');
    const component = new Function('Vue', 'inertia', 'appInstall', `${source}\nreturn component;`)(Vue, { Deferred: { name: 'Deferred' }, Link: { name: 'Link' }, router: {} }, app);
    const props = Vue.reactive({
        mode: 'operational', organization: { name: 'Empresa' }, can: {}, links: {},
        priority_counts: {}, my_inspections: [], workflow_summary: [], recent_activities: [], featured_inspection: null,
        ...overrides,
    });
    const scope = Vue.effectScope();
    const state = scope.run(() => component.setup(props, { expose() {} }));
    t.after(() => scope.stop());
    const template = compileTemplate({
        source: descriptor.template.content, filename: 'Dashboard/Index.vue', id: 'pwa-dashboard',
        compilerOptions: { bindingMetadata: script.bindings },
    });
    assert.deepEqual(template.errors, []);
    const renderSource = template.code
        .replace(/import \{([^}]+)\} from "vue"/, (_, imports) => `const {${imports.replace(/ as /g, ': ')}} = Vue;`)
        .replace('export function render', 'function render');
    const renderTemplate = new Function('Vue', `${renderSource}\nreturn render;`)(Vue);
    const render = () => renderTemplate({}, [], props, Vue.proxyRefs(state), {}, {});
    return {
        button: () => find(render().children.actions(), node => node.type === 'button'),
        help: () => find(render().children.default(), node => node.props?.id === 'app-install-help'),
        unmount: () => scope.stop(),
    };
}

function find(nodes, predicate) {
    for (const node of Array.isArray(nodes) ? nodes : [nodes]) {
        if (!node || typeof node !== 'object') continue;
        if (predicate(node)) return node;
        if (Array.isArray(node.children)) {
            const found = find(node.children, predicate);
            if (found) return found;
        }
    }
    return null;
}

test('dashboard offers installation on Android and shows guidance when no native prompt exists', async t => {
    const { app } = setup(t);
    const page = dashboard(t, app);
    assert.equal(page.button().children.trim(), 'Instalar aplicativo');
    assert.equal(page.help(), null);
    await page.button().props.onClick();
    assert.match(page.help().children, /No Chrome, abra o menu ⋮/);
    assert.equal(page.help().props.role, 'status');
    assert.equal(page.button().props['aria-describedby'], 'app-install-help');
});

test('native prompt survives navigation and rapid clicks cannot consume it twice', async t => {
    const { app, target } = setup(t);
    let finish;
    const choice = new Promise(resolve => { finish = resolve; });
    const prompt = offer(target, choice);
    assert.equal(prompt.event.defaultPrevented, true);
    const previousPage = dashboard(t, app);
    previousPage.unmount();
    const page = dashboard(t, app);
    const pending = page.button().props.onClick();
    await page.button().props.onClick();
    assert.equal(prompt.calls(), 1);
    assert.equal(page.button().props.disabled, true);
    assert.equal(page.button().children.trim(), 'Aguardando confirmação…');
    finish({ outcome: 'dismissed' });
    await pending;
    assert.equal(page.button().props.disabled, false);
    assert.equal(page.help(), null);
    await page.button().props.onClick();
    assert.equal(prompt.calls(), 1);
    assert.ok(page.help());
});

test('accepted installation and appinstalled hide the dashboard action', async t => {
    const { app, target } = setup(t);
    const page = dashboard(t, app);
    offer(target, Promise.resolve({ outcome: 'accepted' }));
    await page.button().props.onClick();
    assert.equal(page.button(), null);
    target.dispatchEvent(new Event('appinstalled'));
    assert.equal(app.state.installed, true);
    assert.equal(page.button(), null);
    assert.equal(page.help(), null);
});

test('installing through the browser menu also hides the action and guidance', async t => {
    const { app, target } = setup(t);
    const page = dashboard(t, app);
    await page.button().props.onClick();
    target.dispatchEvent(new Event('appinstalled'));
    assert.equal(page.button(), null);
    assert.equal(page.help(), null);
});

test('a new browser offer after cancellation remains usable', async t => {
    const { app, target } = setup(t);
    let finish;
    offer(target, new Promise(resolve => { finish = resolve; }));
    const pending = app.install();
    const nextPrompt = offer(target, Promise.resolve({ outcome: 'accepted' }));
    finish({ outcome: 'dismissed' });
    await pending;
    await app.install();
    assert.equal(nextPrompt.calls(), 1);
    assert.equal(app.visible.value, false);
});

test('prompt failure unlocks the button and provides the manual installation path', async t => {
    const { app, target } = setup(t);
    offer(target, Promise.resolve({ outcome: 'dismissed' }), new Error('Unavailable'));
    const page = dashboard(t, app);
    await page.button().props.onClick();
    assert.equal(page.button().props.disabled, false);
    assert.ok(page.help());
});

test('standalone, non-Android, and accounts without a company have no dashboard action', t => {
    const standalone = setup(t, { standalone: true });
    assert.equal(dashboard(t, standalone.app).button(), null);
    const desktop = setup(t, { android: false });
    assert.equal(dashboard(t, desktop.app).button(), null);
    assert.equal(offer(desktop.target).event.defaultPrevented, false);
    const android = setup(t);
    assert.equal(dashboard(t, android.app, { organization: null }).button(), null);
    assert.equal(dashboard(t, android.app, { mode: 'global', organization: null }).button(), null);
});

test('display-mode changes hide the action and listener initialization is idempotent', async t => {
    const { app, target, displayMode } = setup(t);
    app.start();
    const prompt = offer(target);
    await app.install();
    assert.equal(prompt.calls(), 1);
    const event = new Event('change');
    event.matches = true;
    displayMode.dispatchEvent(event);
    assert.equal(app.visible.value, false);
    app.stop();
    assert.equal(offer(target).event.defaultPrevented, false);
});

test('initialization is safe without a browser and Android client hints are supported', t => {
    const unavailable = createAppInstall(null);
    unavailable.start();
    assert.equal(unavailable.visible.value, false);
    const { target } = browser({ android: false });
    target.navigator.userAgentData = { platform: 'Android' };
    const app = createAppInstall(target);
    t.after(() => app.stop());
    assert.equal(app.visible.value, true);
});
