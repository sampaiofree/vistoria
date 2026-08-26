<script setup>
import { onBeforeUnmount, ref } from 'vue';
import { router } from '@inertiajs/vue3';

const visible = ref(false);
let timer = null;
let trackedVisit = null;

function isPageNavigation(visit) {
    return visit.method?.toLowerCase() === 'get'
        && !visit.preserveState
        && visit.showProgress;
}

function clearLoader() {
    if (timer) {
        window.clearTimeout(timer);
        timer = null;
    }

    visible.value = false;
    trackedVisit = null;
}

const removeStartListener = router.on('start', (event) => {
    const visit = event.detail.visit;

    if (!isPageNavigation(visit)) {
        return;
    }

    clearLoader();
    trackedVisit = visit;
    timer = window.setTimeout(() => {
        if (trackedVisit === visit) {
            visible.value = true;
        }
    }, 150);
});

const removeFinishListener = router.on('finish', (event) => {
    if (event.detail.visit === trackedVisit) {
        clearLoader();
    }
});

onBeforeUnmount(() => {
    removeStartListener();
    removeFinishListener();
    clearLoader();
});
</script>

<template>
    <div
        v-if="visible"
        class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-950/20 backdrop-blur-[1px]"
        role="status"
        aria-live="polite"
        aria-label="Carregando página"
    >
        <div class="flex items-center gap-3 rounded-md border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 shadow-lg">
            <span class="h-5 w-5 animate-spin rounded-full border-2 border-slate-300 border-t-slate-700 motion-reduce:animate-none" aria-hidden="true"></span>
            <span>Carregando página…</span>
        </div>
    </div>
</template>
