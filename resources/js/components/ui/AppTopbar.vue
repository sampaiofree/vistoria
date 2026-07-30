<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import UiIcon from './UiIcon.vue';

const props = defineProps({
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
    logoutUrl: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['toggle-sidebar', 'toggle-collapse']);

const page = usePage();
const notificationsOpen = ref(false);
const userMenuOpen = ref(false);
const notificationsRegion = ref(null);
const userRegion = ref(null);
const notificationsButton = ref(null);
const userButton = ref(null);

const initials = computed(() => {
    const name = props.user?.name ?? '';
    return name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase() ?? '')
        .join('') || 'V';
});

function closeMenus() {
    notificationsOpen.value = false;
    userMenuOpen.value = false;
}

function handleKeydown(event) {
    if (event.key === 'Escape') {
        const returnTarget = notificationsOpen.value
            ? notificationsButton.value
            : userMenuOpen.value
                ? userButton.value
                : null;

        closeMenus();
        returnTarget?.focus();
    }
}

function handlePointerDown(event) {
    if (!notificationsRegion.value?.contains(event.target)) {
        notificationsOpen.value = false;
    }

    if (!userRegion.value?.contains(event.target)) {
        userMenuOpen.value = false;
    }
}

onMounted(() => {
    window.addEventListener('keydown', handleKeydown);
    document.addEventListener('pointerdown', handlePointerDown);
});

onBeforeUnmount(() => {
    window.removeEventListener('keydown', handleKeydown);
    document.removeEventListener('pointerdown', handlePointerDown);
});

watch(
    () => page.url,
    () => {
        closeMenus();
    },
);
</script>

<template>
    <header class="sticky top-0 z-20 border-b border-slate-200 bg-white/95 backdrop-blur">
        <div class="flex h-16 items-center gap-3 px-4 sm:px-6 lg:px-8">
            <button
                type="button"
                class="inline-flex h-11 w-11 items-center justify-center rounded-xl border border-slate-200 text-slate-600 transition hover:border-slate-300 hover:text-slate-900 lg:hidden"
                aria-label="Abrir menu lateral"
                aria-controls="app-sidebar"
                :aria-expanded="mobileOpen ? 'true' : 'false'"
                @click="$emit('toggle-sidebar', $event)"
            >
                <UiIcon name="menu" class="h-5 w-5" />
            </button>

            <button
                type="button"
                class="hidden h-11 w-11 items-center justify-center rounded-xl border border-slate-200 text-slate-600 transition hover:border-slate-300 hover:text-slate-900 lg:inline-flex"
                :aria-label="collapsed ? 'Abrir menu lateral' : 'Recolher menu lateral'"
                aria-controls="app-sidebar"
                :aria-expanded="collapsed ? 'false' : 'true'"
                @click="$emit('toggle-collapse')"
            >
                <UiIcon :name="collapsed ? 'chevron-right' : 'chevron-left'" class="h-5 w-5" />
            </button>

            <div class="min-w-0 flex-1">
                <div class="truncate text-sm font-medium text-slate-900">
                    {{ organization?.name ?? 'Visão global' }}
                </div>
                <div class="truncate text-xs text-slate-500">
                    {{ organization ? 'Ambiente operacional' : 'Superadministrador' }}
                </div>
            </div>

            <label class="hidden min-w-0 flex-1 xl:flex">
                <span class="sr-only">Buscar TAG, inspeção, cliente ou equipamento</span>
                <div class="relative w-full">
                    <UiIcon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                    <input
                        type="search"
                        placeholder="Busca global em breve"
                        class="h-11 w-full cursor-not-allowed rounded-2xl border border-slate-200 bg-slate-50 px-10 text-sm text-slate-500 outline-none placeholder:text-slate-400"
                        aria-label="Busca global indisponível nesta etapa"
                        title="Busca global em breve"
                        disabled
                    >
                </div>
            </label>

            <button
                type="button"
                class="inline-flex h-11 w-11 cursor-not-allowed items-center justify-center rounded-xl border border-slate-200 text-slate-400 xl:hidden"
                aria-label="Busca global indisponível nesta etapa"
                title="Busca global em breve"
                disabled
            >
                <UiIcon name="search" class="h-5 w-5" />
            </button>

            <div ref="notificationsRegion" class="relative">
                <button
                    ref="notificationsButton"
                    type="button"
                    class="inline-flex h-11 w-11 items-center justify-center rounded-xl border border-slate-200 text-slate-600 transition hover:border-slate-300 hover:text-slate-900"
                    aria-label="Notificações: nenhuma pendente"
                    :aria-expanded="notificationsOpen ? 'true' : 'false'"
                    aria-controls="notifications-menu"
                    aria-haspopup="true"
                    @click="notificationsOpen = !notificationsOpen; userMenuOpen = false"
                >
                    <UiIcon name="bell" class="h-5 w-5" />
                </button>

                <div
                    v-if="notificationsOpen"
                    id="notifications-menu"
                    class="fixed left-4 right-4 top-16 mt-2 rounded-2xl border border-slate-200 bg-white p-4 shadow-lg shadow-slate-950/10 sm:absolute sm:left-auto sm:right-0 sm:top-auto sm:w-72"
                >
                    <div class="text-sm font-semibold text-slate-900">
                        Notificações
                    </div>
                    <p class="mt-2 text-sm text-slate-600">
                        Nenhuma notificação pendente no momento.
                    </p>
                </div>
            </div>

            <div ref="userRegion" class="relative">
                <button
                    ref="userButton"
                    type="button"
                    class="flex h-11 items-center gap-3 rounded-2xl border border-slate-200 bg-white px-3 py-2 text-left transition hover:border-slate-300"
                    :aria-expanded="userMenuOpen ? 'true' : 'false'"
                    aria-label="Menu do usuário"
                    aria-controls="user-menu"
                    aria-haspopup="menu"
                    @click="userMenuOpen = !userMenuOpen; notificationsOpen = false"
                >
                    <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-slate-900 text-xs font-semibold text-white">
                        {{ initials }}
                    </span>
                    <span class="hidden text-left sm:block">
                        <span class="block text-sm font-medium text-slate-900">
                            {{ user?.name ?? 'Usuário' }}
                        </span>
                        <span class="block text-xs text-slate-500">
                            {{ user?.email ?? '' }}
                        </span>
                    </span>
                    <UiIcon name="chevron-right" class="hidden h-4 w-4 text-slate-400 sm:block" />
                </button>

                <div
                    v-if="userMenuOpen"
                    id="user-menu"
                    role="menu"
                    class="fixed left-4 right-4 top-16 mt-2 rounded-2xl border border-slate-200 bg-white p-2 shadow-lg shadow-slate-950/10 sm:absolute sm:left-auto sm:right-0 sm:top-auto sm:w-64"
                >
                    <div class="border-b border-slate-100 px-3 py-3">
                        <div class="text-sm font-semibold text-slate-900">
                            {{ user?.name ?? 'Usuário' }}
                        </div>
                        <div class="text-xs text-slate-500">
                            {{ user?.email ?? '' }}
                        </div>
                    </div>
                    <button
                        type="button"
                        class="flex w-full items-center gap-3 rounded-xl px-3 py-3 text-left text-sm font-medium text-slate-400"
                        disabled
                    >
                        <UiIcon name="user" class="h-4 w-4" />
                        Meu perfil
                    </button>
                    <Link
                        v-if="logoutUrl"
                        :href="logoutUrl"
                        method="post"
                        as="button"
                        role="menuitem"
                        class="mt-1 flex w-full items-center gap-3 rounded-xl px-3 py-3 text-left text-sm font-medium text-slate-700 transition hover:bg-slate-50 hover:text-slate-950"
                    >
                        <UiIcon name="logout" class="h-4 w-4" />
                        Sair
                    </Link>
                </div>
            </div>
        </div>
    </header>
</template>
