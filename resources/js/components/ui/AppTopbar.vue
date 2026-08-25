<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
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
    notifications: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(['toggle-sidebar', 'toggle-collapse']);

const page = usePage();
const userMenuOpen = ref(false);
const notificationMenuOpen = ref(false);
const userRegion = ref(null);
const userButton = ref(null);
const notificationRegion = ref(null);
const notificationButton = ref(null);
let notificationPoll = null;

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
    notificationMenuOpen.value = false;
}

function handleKeydown(event) {
    if (event.key === 'Escape') {
        const returnTarget = notificationMenuOpen.value ? notificationButton.value : (userMenuOpen.value ? userButton.value : null);

        closeMenus();
        returnTarget?.focus();
    }
}

function handlePointerDown(event) {
    if (!userRegion.value?.contains(event.target)) {
        userMenuOpen.value = false;
    }
    if (!notificationRegion.value?.contains(event.target)) {
        notificationMenuOpen.value = false;
    }
}

onMounted(() => {
    window.addEventListener('keydown', handleKeydown);
    document.addEventListener('pointerdown', handlePointerDown);
    notificationPoll = window.setInterval(() => {
        if (document.visibilityState === 'visible' && props.notifications) {
            router.reload({ only: ['notifications'], preserveScroll: true, preserveState: true });
        }
    }, 30000);
});

onBeforeUnmount(() => {
    window.removeEventListener('keydown', handleKeydown);
    document.removeEventListener('pointerdown', handlePointerDown);
    if (notificationPoll) window.clearInterval(notificationPoll);
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

            <div v-if="notifications" ref="notificationRegion" class="relative">
                <button
                    ref="notificationButton"
                    type="button"
                    class="relative inline-flex h-11 w-11 items-center justify-center rounded-md border border-slate-300 bg-white text-slate-600 transition hover:bg-slate-50 hover:text-slate-900"
                    :aria-expanded="notificationMenuOpen ? 'true' : 'false'"
                    aria-label="Notificações"
                    aria-controls="notification-menu"
                    aria-haspopup="menu"
                    @click="notificationMenuOpen = !notificationMenuOpen; userMenuOpen = false"
                >
                    <UiIcon name="bell" class="h-5 w-5" />
                    <span v-if="notifications.unread_count" class="absolute -right-1 -top-1 inline-flex min-h-5 min-w-5 items-center justify-center rounded-full bg-rose-600 px-1 text-[10px] font-bold text-white">
                        {{ notifications.unread_count > 99 ? '99+' : notifications.unread_count }}
                    </span>
                </button>

                <div
                    v-if="notificationMenuOpen"
                    id="notification-menu"
                    class="fixed left-4 right-4 top-16 mt-2 overflow-hidden rounded-md border border-slate-200 bg-white shadow-lg sm:absolute sm:left-auto sm:right-0 sm:top-auto sm:w-96"
                >
                    <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
                        <span class="font-semibold text-slate-950">Notificações</span>
                        <Link v-if="notifications.unread_count" :href="notifications.read_all_url" method="patch" as="button" class="text-xs font-semibold text-teal-700 hover:text-teal-900">Marcar todas como lidas</Link>
                    </div>
                    <div v-if="notifications.recent.length" class="max-h-96 divide-y divide-slate-100 overflow-y-auto">
                        <Link
                            v-for="notification in notifications.recent"
                            :key="notification.id"
                            :href="notification.read_url"
                            method="patch"
                            as="button"
                            class="flex w-full items-start gap-3 px-4 py-3 text-left hover:bg-slate-50"
                            :class="notification.read ? 'bg-white' : 'bg-teal-50/50'"
                        >
                            <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full" :class="notification.read ? 'bg-slate-300' : 'bg-teal-600'"></span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-sm font-semibold text-slate-900">{{ notification.title }}</span>
                                <span class="mt-0.5 line-clamp-2 block text-xs leading-5 text-slate-600">{{ notification.message }}</span>
                                <span class="mt-1 block text-[11px] text-slate-400">{{ notification.created_at }}</span>
                            </span>
                        </Link>
                    </div>
                    <p v-else class="px-4 py-8 text-center text-sm text-slate-500">Nenhuma notificação.</p>
                    <Link :href="notifications.index_url" class="block border-t border-slate-100 px-4 py-3 text-center text-sm font-semibold text-teal-700 hover:bg-slate-50">Ver todas</Link>
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
                    @click="userMenuOpen = !userMenuOpen; notificationMenuOpen = false"
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
