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
    },
    warning: {
        accent: 'text-amber-600',
    },
    info: {
        accent: 'text-sky-600',
    },
    approval: {
        accent: 'text-violet-600',
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
        class="group flex h-full flex-col rounded-lg border border-slate-200 bg-white p-4 transition hover:border-slate-400 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400"
    >
        <div v-if="loading" class="animate-pulse" aria-hidden="true">
            <div class="flex items-start justify-between gap-3">
                <div class="h-5 w-5 rounded bg-slate-200" />
                <div class="h-7 w-16 rounded bg-slate-200" />
            </div>
            <div class="mt-3 h-4 w-3/4 rounded bg-slate-200" />
            <div class="mt-2 h-4 w-5/6 rounded bg-slate-100" />
            <div class="mt-4 h-4 w-28 rounded bg-slate-200" />
        </div>

        <template v-else>
            <div class="flex items-center justify-between gap-3">
                <span class="inline-flex items-center justify-center" :class="state.accent">
                    <UiIcon :name="icon" class="h-5 w-5" />
                </span>
                <div class="text-2xl font-semibold text-slate-900">
                    {{ count }}
                </div>
            </div>

            <div class="mt-3">
                <div class="text-sm font-semibold text-slate-900">
                    {{ title }}
                </div>
                <p class="mt-1 text-sm leading-6 text-slate-500">
                    {{ description }}
                </p>
            </div>

            <div class="mt-4 inline-flex items-center gap-2 text-sm font-medium text-slate-600">
                Ver detalhes
                <UiIcon name="arrow-right" class="h-4 w-4" />
            </div>
        </template>
    </component>
</template>
