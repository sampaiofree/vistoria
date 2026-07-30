<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import UiIcon from '@/components/ui/UiIcon.vue';

const props = defineProps({
    title: {
        type: String,
        required: true,
    },
    count: {
        type: [Number, String],
        default: 0,
    },
    description: {
        type: String,
        required: true,
    },
    icon: {
        type: String,
        required: true,
    },
    variant: {
        type: String,
        default: 'info',
    },
    href: {
        type: String,
        default: '',
    },
    loading: {
        type: Boolean,
        default: false,
    },
});

const variants = {
    danger: {
        accent: 'text-rose-600',
        chip: 'border-rose-200 bg-rose-50 text-rose-700',
        icon: 'bg-rose-500/10 text-rose-600',
    },
    warning: {
        accent: 'text-amber-600',
        chip: 'border-amber-200 bg-amber-50 text-amber-700',
        icon: 'bg-amber-500/10 text-amber-600',
    },
    info: {
        accent: 'text-sky-600',
        chip: 'border-sky-200 bg-sky-50 text-sky-700',
        icon: 'bg-sky-500/10 text-sky-600',
    },
    approval: {
        accent: 'text-violet-600',
        chip: 'border-violet-200 bg-violet-50 text-violet-700',
        icon: 'bg-violet-500/10 text-violet-600',
    },
};

const state = computed(() => variants[props.variant] ?? variants.info);
const component = computed(() => (props.href ? Link : 'div'));
const linkAttrs = computed(() => (props.href ? { href: props.href } : {}));
</script>

<template>
    <component
        :is="component"
        v-bind="linkAttrs"
        :aria-busy="loading ? 'true' : 'false'"
        class="group flex h-full flex-col rounded-2xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-950/5 transition hover:-translate-y-0.5 hover:border-slate-300 hover:shadow-md hover:shadow-slate-950/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-teal-500/70"
    >
        <div v-if="loading" class="animate-pulse" aria-hidden="true">
            <div class="flex items-start justify-between gap-3">
                <div class="h-11 w-11 rounded-xl bg-slate-200" />
                <div class="h-5 w-16 rounded-full bg-slate-100" />
            </div>
            <div class="mt-5 h-8 w-20 rounded-lg bg-slate-200" />
            <div class="mt-3 h-4 w-3/4 rounded bg-slate-200" />
            <div class="mt-2 h-4 w-5/6 rounded bg-slate-100" />
            <div class="mt-5 h-4 w-28 rounded bg-slate-200" />
        </div>

        <template v-else>
            <div class="flex items-start justify-between gap-3">
                <span class="inline-flex h-11 w-11 items-center justify-center rounded-xl" :class="state.icon">
                    <UiIcon :name="icon" class="h-5 w-5" />
                </span>
                <span class="inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-semibold" :class="state.chip">
                    Prioridade
                </span>
            </div>

            <div class="mt-5">
                <div class="text-3xl font-semibold tracking-tight text-slate-900">
                    {{ count }}
                </div>
                <div class="mt-2 text-sm font-semibold text-slate-900">
                    {{ title }}
                </div>
                <p class="mt-1 text-sm leading-6 text-slate-500">
                    {{ description }}
                </p>
            </div>

            <div class="mt-5 inline-flex items-center gap-2 text-sm font-semibold" :class="state.accent">
                Ver detalhes
                <UiIcon name="arrow-right" class="h-4 w-4 transition group-hover:translate-x-0.5" />
            </div>
        </template>
    </component>
</template>
