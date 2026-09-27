import { computed, reactive, readonly } from 'vue';

export function createAppInstall(browser) {
    const displayMode = browser?.matchMedia?.('(display-mode: standalone)');
    const state = reactive({
        android: /Android/i.test(browser?.navigator?.userAgentData?.platform ?? browser?.navigator?.userAgent ?? ''),
        standalone: Boolean(displayMode?.matches || browser?.navigator?.standalone),
        installed: false,
        accepted: false,
        pending: false,
        showHelp: false,
    });
    const visible = computed(() => state.android && !state.standalone && !state.installed && !state.accepted);
    let deferredPrompt = null;
    let started = false;

    function capturePrompt(event) {
        if (!state.android || state.standalone || state.installed) return;

        event.preventDefault();
        deferredPrompt = event;
        state.accepted = false;
        state.showHelp = false;
    }

    function markInstalled() {
        deferredPrompt = null;
        state.installed = true;
        state.showHelp = false;
    }

    function updateDisplayMode(event) {
        state.standalone = event.matches;
        if (event.matches) state.showHelp = false;
    }

    function start() {
        if (started || !browser) return;
        started = true;
        browser.addEventListener('beforeinstallprompt', capturePrompt);
        browser.addEventListener('appinstalled', markInstalled);
        displayMode?.addEventListener('change', updateDisplayMode);
    }

    function stop() {
        if (!started) return;
        browser.removeEventListener('beforeinstallprompt', capturePrompt);
        browser.removeEventListener('appinstalled', markInstalled);
        displayMode?.removeEventListener('change', updateDisplayMode);
        deferredPrompt = null;
        started = false;
    }

    async function install() {
        if (!visible.value || state.pending) return;
        if (!deferredPrompt) {
            state.showHelp = true;
            return;
        }

        const prompt = deferredPrompt;
        deferredPrompt = null; // Each browser event can be used only once.
        state.pending = true;
        state.showHelp = false;

        try {
            await prompt.prompt();
            const choice = await prompt.userChoice;
            state.accepted = choice.outcome === 'accepted';
        } catch {
            state.showHelp = true;
        } finally {
            state.pending = false;
        }
    }

    return { state: readonly(state), visible, start, stop, install };
}

// Shared across Inertia pages: navigation must not discard the browser's event.
export const appInstall = createAppInstall(typeof window === 'undefined' ? null : window);
