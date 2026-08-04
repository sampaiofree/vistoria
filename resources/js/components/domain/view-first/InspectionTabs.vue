<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    tabs: {
        type: Array,
        default: () => [],
    },
    active: {
        type: String,
        default: 'overview',
    },
});
</script>

<template>
    <nav aria-label="Seções da inspeção" class="-mx-4 overflow-x-auto px-4 sm:mx-0 sm:px-0">
        <div class="flex min-w-max gap-1 rounded-2xl border border-slate-200 bg-white p-1.5 shadow-sm">
            <Link
                v-for="tab in tabs"
                :key="tab.key"
                :href="tab.url || tab.href"
                :aria-current="active === tab.key ? 'page' : undefined"
                class="inline-flex min-h-10 items-center gap-2 rounded-xl px-3.5 py-2 text-sm font-semibold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-teal-500"
                :class="active === tab.key
                    ? 'bg-slate-950 text-white shadow-sm'
                    : 'text-slate-600 hover:bg-slate-100 hover:text-slate-950'"
            >
                {{ tab.label }}
                <span
                    v-if="tab.count !== null && tab.count !== undefined"
                    class="rounded-full px-2 py-0.5 text-[11px]"
                    :class="active === tab.key ? 'bg-white/15 text-white' : 'bg-slate-100 text-slate-500'"
                >
                    {{ tab.count }}
                </span>
            </Link>
        </div>
    </nav>
</template>
