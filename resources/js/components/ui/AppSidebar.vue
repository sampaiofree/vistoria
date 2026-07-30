<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import { Link } from '@inertiajs/vue3';
import UiIcon from './UiIcon.vue';

const props = defineProps({
    items: {
        type: Array,
        default: () => [],
    },
    user: {
        type: Object,
        default: null,
    },
    organization: {
        type: Object,
        default: null,
    },
    collapsed: {
        type: Boolean,
        default: false,
    },
    mobileOpen: {
        type: Boolean,
        default: false,
    },
    homeUrl: {
        type: String,
        default: '/',
    },
});

const emit = defineEmits(['close-mobile']);
const aside = ref(null);
const closeButton = ref(null);

const initials = computed(() => {
    const name = props.user?.name ?? '';

    return name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase() ?? '')
        .join('') || 'V';
});

watch(
    () => props.mobileOpen,
    async (open) => {
        if (!open) {
            return;
        }

        await nextTick();
        closeButton.value?.focus();
    },
);

function handleKeydown(event) {
    if (!props.mobileOpen) {
        return;
    }

    if (event.key === 'Escape') {
        event.preventDefault();
        emit('close-mobile');

        return;
    }

    if (event.key !== 'Tab') {
        return;
    }

    const focusable = [...aside.value.querySelectorAll(
        'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])',
    )].filter((element) => element.offsetParent !== null);

    if (focusable.length === 0) {
        event.preventDefault();

        return;
    }

    const first = focusable[0];
    const last = focusable[focusable.length - 1];

    if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
    }
}
</script>

<template>
    <div>
        <button
            v-if="mobileOpen"
            type="button"
            class="fixed inset-0 z-30 bg-slate-950/60 transition-opacity lg:hidden"
            aria-label="Fechar menu lateral"
            @click="$emit('close-mobile')"
        />

        <aside
            id="app-sidebar"
            ref="aside"
            :role="mobileOpen ? 'dialog' : undefined"
            :aria-modal="mobileOpen ? 'true' : undefined"
            aria-label="Navegação principal"
            class="fixed inset-y-0 left-0 z-40 flex w-64 flex-col border-r border-slate-800 bg-slate-950 text-slate-100 shadow-2xl shadow-slate-950/20 transition-[transform,width,visibility] duration-200 lg:visible lg:translate-x-0"
            :class="[
                mobileOpen ? 'visible translate-x-0' : 'invisible -translate-x-full lg:visible lg:translate-x-0',
                collapsed ? 'lg:w-[4.5rem]' : 'lg:w-64',
            ]"
            @keydown="handleKeydown"
        >
            <div class="flex h-16 items-center justify-between border-b border-slate-800 px-4">
                <Link :href="homeUrl" class="flex items-center gap-3 text-left">
                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-2xl bg-teal-500/15 text-teal-300">
                        <UiIcon name="dashboard" class="h-5 w-5" />
                    </span>
                    <span :class="collapsed ? 'lg:sr-only' : ''">
                        <span class="block text-sm font-semibold uppercase tracking-[0.22em] text-slate-400">
                            Vistoria
                        </span>
                        <span class="block text-base font-semibold text-white">
                            Central operacional
                        </span>
                    </span>
                </Link>

                <button
                    ref="closeButton"
                    type="button"
                    class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-slate-800 text-slate-300 transition hover:border-slate-700 hover:text-white lg:hidden"
                    aria-label="Fechar menu lateral"
                    @click="$emit('close-mobile')"
                >
                    <UiIcon name="close" class="h-4 w-4" />
                </button>
            </div>

            <nav class="flex-1 space-y-1 px-3 py-4">
                <Link
                    v-for="item in items"
                    :key="item.href"
                    :href="item.href"
                    :title="collapsed ? item.label : undefined"
                    :aria-current="item.active ? 'page' : undefined"
                    class="group flex items-center gap-3 rounded-2xl px-3 py-3 text-sm font-medium transition focus:outline-none focus-visible:ring-2 focus-visible:ring-teal-400/80"
                    :class="item.active
                        ? 'bg-teal-500/15 text-white ring-1 ring-inset ring-teal-400/20'
                        : 'text-slate-300 hover:bg-slate-900 hover:text-white'"
                >
                    <span
                        class="inline-flex h-10 w-10 items-center justify-center rounded-xl transition"
                        :class="item.active ? 'bg-teal-500/20 text-teal-300' : 'bg-slate-900 text-slate-300 group-hover:bg-slate-800 group-hover:text-white'"
                    >
                        <UiIcon :name="item.icon" class="h-5 w-5" />
                    </span>
                    <span :class="collapsed ? 'lg:sr-only' : ''">
                        {{ item.label }}
                    </span>
                </Link>
            </nav>

            <div class="border-t border-slate-800 p-4">
                <div
                    class="rounded-2xl border border-slate-800 bg-slate-900/80 p-4"
                    :class="collapsed ? 'lg:hidden' : ''"
                >
                    <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">
                        Sessão
                    </div>
                    <div class="mt-1 text-sm font-medium text-white">
                        {{ user?.name ?? 'Usuário' }}
                    </div>
                    <div v-if="organization" class="mt-1 text-xs text-slate-400">
                        {{ organization.name }}
                    </div>
                    <div v-else class="mt-1 text-xs text-slate-400">
                        Visão global
                    </div>
                </div>

                <div
                    v-if="collapsed"
                    class="hidden h-10 w-10 items-center justify-center rounded-xl border border-slate-800 bg-slate-900 text-xs font-semibold text-white lg:flex"
                    :title="user?.name ?? 'Usuário'"
                    :aria-label="`Sessão de ${user?.name ?? 'Usuário'}`"
                >
                    {{ initials }}
                </div>
            </div>
        </aside>
    </div>
</template>
