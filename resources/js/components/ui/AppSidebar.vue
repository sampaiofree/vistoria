<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppSidebarItem from './AppSidebarItem.vue';
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
    context: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(['close-mobile', 'expand-desktop']);
const aside = ref(null);
const closeButton = ref(null);
const sidebarStyle = computed(() => ({
    backgroundColor: props.organization?.primary_color ?? '#0F172A',
}));

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
            class="fixed inset-0 z-30 bg-slate-950/50 transition-opacity lg:hidden"
            aria-label="Fechar menu lateral"
            @click="$emit('close-mobile')"
        />

        <aside
            id="app-sidebar"
            ref="aside"
            :role="mobileOpen ? 'dialog' : undefined"
            :aria-modal="mobileOpen ? 'true' : undefined"
            aria-label="Navegação principal"
            class="fixed inset-y-0 left-0 z-40 flex w-64 flex-col border-r border-slate-800 bg-slate-900 text-slate-100 transition-[transform,width,visibility] duration-200 lg:visible lg:translate-x-0"
            :style="sidebarStyle"
            :class="[
                mobileOpen ? 'visible translate-x-0' : 'invisible -translate-x-full lg:visible lg:translate-x-0',
                context ? 'w-72 lg:w-72' : (collapsed ? 'lg:w-[4.5rem]' : 'lg:w-64'),
            ]"
            @keydown="handleKeydown"
        >
            <div class="flex h-16 items-center justify-between border-b border-slate-800 px-4">
                <Link :href="context?.inspection?.overview_url || homeUrl" class="flex min-w-0 items-center gap-3 text-left">
                    <template v-if="context">
                        <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center text-slate-300">
                            <UiIcon name="inspections" class="h-5 w-5" />
                        </span>
                    </template>
                    <template v-else>
                        <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center overflow-hidden text-slate-300">
                            <img v-if="organization?.icon_url" :src="organization.icon_url" :alt="`Ícone de ${organization.name}`" class="h-full w-full object-contain">
                            <UiIcon v-else name="dashboard" class="h-5 w-5" />
                        </span>
                    </template>
                    <span class="min-w-0" :class="collapsed ? 'lg:sr-only' : ''">
                        <template v-if="context">
                        <span class="block text-xs font-medium text-slate-400">
                            Inspeção
                        </span>
                        <span class="mt-0.5 block truncate text-sm font-semibold text-white">
                            {{ context.inspection?.equipment_tag || 'Sem equipamento' }}
                        </span>
                        <span class="mt-0.5 block truncate text-[11px] text-slate-500">
                            {{ context.inspection.number || 'Sem número' }} · {{ context.inspection.status_label }}
                        </span>
                        </template>
                        <template v-else>
                            <span class="block text-xs font-medium text-slate-400">Empresa</span>
                            <span class="mt-0.5 block truncate text-sm font-semibold text-white">
                                {{ organization?.name || 'Vistoria' }}
                            </span>
                        </template>
                    </span>
                </Link>

                <button
                    ref="closeButton"
                    type="button"
                    class="inline-flex h-9 w-9 items-center justify-center rounded-md border border-slate-700 text-slate-300 transition hover:bg-slate-800 hover:text-white lg:hidden"
                    aria-label="Fechar menu lateral"
                    @click="$emit('close-mobile')"
                >
                    <UiIcon name="close" class="h-4 w-4" />
                </button>
            </div>

            <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4">
                <Link
                    v-if="context"
                    :href="context.back_url"
                    class="mb-3 flex items-center gap-2 rounded-md px-2.5 py-2 text-xs font-medium text-slate-400 transition hover:bg-slate-800 hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400"
                    @click="$emit('close-mobile')"
                >
                    <UiIcon name="chevron-left" class="h-3.5 w-3.5" />
                    {{ context.back_label }}
                </Link>

                <AppSidebarItem
                    v-for="item in items"
                    :key="item.key || item.href"
                    :item="item"
                    :collapsed="collapsed"
                    @navigate="$emit('close-mobile')"
                    @expand-desktop="$emit('expand-desktop')"
                />
            </nav>

            <div class="border-t border-slate-800 p-4">
                <div
                    class="px-1 py-1"
                    :class="collapsed ? 'lg:hidden' : ''"
                >
                    <div class="text-xs font-medium text-slate-500">
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
                    class="hidden h-10 w-10 items-center justify-center rounded-md border border-slate-700 bg-slate-800 text-xs font-semibold text-white lg:flex"
                    :title="user?.name ?? 'Usuário'"
                    :aria-label="`Sessão de ${user?.name ?? 'Usuário'}`"
                >
                    {{ initials }}
                </div>
            </div>
        </aside>
    </div>
</template>
