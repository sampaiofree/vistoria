<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    links: {
        type: Array,
        required: true,
    },
});
</script>

<template>
    <nav
        v-if="links?.length"
        class="flex flex-wrap items-center gap-2"
        aria-label="Pagination"
    >
        <template v-for="link in links" :key="`${link.label}-${link.url ?? 'null'}`">
            <span
                v-if="!link.url"
                class="rounded-md border border-slate-200 px-3 py-1.5 text-sm text-slate-400"
                v-html="link.label"
            />
            <span
                v-else-if="link.active"
                class="rounded-md border border-slate-800 bg-slate-800 px-3 py-1.5 text-sm font-semibold text-white"
                v-html="link.label"
            />
            <Link
                v-else
                :href="link.url"
                class="rounded-md border border-slate-200 bg-white px-3 py-1.5 text-sm text-slate-700 transition hover:border-slate-400 hover:text-slate-900"
                v-html="link.label"
            />
        </template>
    </nav>
</template>
