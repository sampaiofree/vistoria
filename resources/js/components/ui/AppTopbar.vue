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
    collapsible: {
        type: Boolean,
        default: true,
    },
});

const emit = defineEmits(['toggle-sidebar', 'toggle-collapse']);

const page = usePage();
const userMenuOpen = ref(false);
const userRegion = ref(null);
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
    userMenuOpen.value = false;
}

function handleKeydown(event) {
    if (event.key === 'Escape') {
        const returnTarget = userMenuOpen.value ? userButton.value : null;

        closeMenus();
        returnTarget?.focus();
    }
}

function handlePointerDown(event) {
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
    <header class="sticky top-0 z-20 border-b border-slate-200 bg-white">
        <div class="flex h-16 items-center gap-3 px-4 sm:px-6 lg:px-8">
            <button
                type="button"
                class="inline-flex h-11 w-11 items-center justify-center rounded-md border border-slate-300 text-slate-600 transition hover:bg-slate-50 hover:text-slate-900 lg:hidden"
                aria-label="Abrir menu lateral"
                aria-controls="app-sidebar"
                :aria-expanded="mobileOpen ? 'true' : 'false'"
                @click="$emit('toggle-sidebar', $event)"
            >
                <UiIcon name="menu" class="h-5 w-5" />
            </button>

            <button
                v-if="collapsible"
                type="button"
                class="hidden h-11 w-11 items-center justify-center rounded-md border border-slate-300 text-slate-600 transition hover:bg-slate-50 hover:text-slate-900 lg:inline-flex"
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

            <div ref="userRegion" class="relative">
                <button
                    ref="userButton"
                    type="button"
                    class="flex h-11 items-center gap-3 rounded-md border border-slate-300 bg-white px-3 py-2 text-left transition hover:bg-slate-50"
                    :aria-expanded="userMenuOpen ? 'true' : 'false'"
                    aria-label="Menu do usuário"
                    aria-controls="user-menu"
                    aria-haspopup="menu"
                    @click="userMenuOpen = !userMenuOpen"
                >
                    <span class="inline-flex h-8 w-8 items-center justify-center rounded bg-slate-800 text-xs font-semibold text-white">
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
                    <UiIcon name="chevron-down" class="hidden h-4 w-4 text-slate-400 sm:block" />
                </button>

                <div
                    v-if="userMenuOpen"
                    id="user-menu"
                    role="menu"
                    class="fixed left-4 right-4 top-16 mt-2 rounded-md border border-slate-200 bg-white p-2 shadow-md sm:absolute sm:left-auto sm:right-0 sm:top-auto sm:w-64"
                >
                    <div class="border-b border-slate-100 px-3 py-3">
                        <div class="text-sm font-semibold text-slate-900">
                            {{ user?.name ?? 'Usuário' }}
                        </div>
                        <div class="text-xs text-slate-500">
                            {{ user?.email ?? '' }}
                        </div>
                    </div>
                    <Link href="/account/password" class="flex w-full items-center gap-3 rounded-md px-3 py-3 text-left text-sm font-medium text-slate-700 transition hover:bg-slate-100 hover:text-slate-950">
                        <UiIcon name="user" class="h-4 w-4" />
                        Alterar senha
                    </Link>
                    <Link
                        v-if="logoutUrl"
                        :href="logoutUrl"
                        method="post"
                        as="button"
                        role="menuitem"
                        class="mt-1 flex w-full items-center gap-3 rounded-md px-3 py-3 text-left text-sm font-medium text-slate-700 transition hover:bg-slate-100 hover:text-slate-950"
                    >
                        <UiIcon name="logout" class="h-4 w-4" />
                        Sair
                    </Link>
                </div>
            </div>
        </div>
    </header>
</template>
