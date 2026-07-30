<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AppSidebar from '@/components/ui/AppSidebar.vue';
import AppTopbar from '@/components/ui/AppTopbar.vue';
import PageHeader from '@/components/ui/PageHeader.vue';

const props = defineProps({
    title: {
        type: String,
        required: true,
    },
    subtitle: {
        type: String,
        default: '',
    },
    wide: {
        type: Boolean,
        default: false,
    },
});

const page = usePage();

const user = computed(() => page.props.auth?.user ?? null);
const organization = computed(() => user.value?.organization ?? null);
const navigation = computed(() => page.props.navigation ?? []);
const dashboardUrl = computed(() => navigation.value.find((item) => item.icon === 'dashboard')?.href ?? '/');
const logoutUrl = computed(() => page.props.auth?.logout_url ?? '');

const flashSuccess = computed(() => page.props.flash?.success ?? '');
const flashError = computed(() => page.props.flash?.error ?? '');

const collapsed = ref(false);
const mobileOpen = ref(false);
const mobileMenuTrigger = ref(null);

const storageKey = 'vistoria.sidebar.collapsed';

function syncSidebarState() {
    if (typeof window === 'undefined') {
        return;
    }

    const stored = window.localStorage.getItem(storageKey);

    if (stored !== null) {
        collapsed.value = stored === '1';
        return;
    }

    collapsed.value = window.innerWidth < 1280;
}

function toggleSidebar(event) {
    if (mobileOpen.value) {
        closeMobileSidebar();

        return;
    }

    mobileMenuTrigger.value = event?.currentTarget ?? null;
    mobileOpen.value = true;
}

function closeMobileSidebar(restoreFocus = true) {
    mobileOpen.value = false;

    if (restoreFocus) {
        nextTick(() => mobileMenuTrigger.value?.focus());
    }
}

function toggleCollapse() {
    collapsed.value = !collapsed.value;
}

onMounted(() => {
    syncSidebarState();

    window.addEventListener('keydown', handleKeydown);
});

onBeforeUnmount(() => {
    window.removeEventListener('keydown', handleKeydown);
    document.body.style.overflow = '';
});

watch(
    collapsed,
    (value) => {
        if (typeof window === 'undefined') {
            return;
        }

        window.localStorage.setItem(storageKey, value ? '1' : '0');
    },
    { flush: 'post' },
);

watch(
    () => page.url,
    () => {
        mobileOpen.value = false;
    },
);

watch(mobileOpen, (open) => {
    document.body.style.overflow = open ? 'hidden' : '';
});

function handleKeydown(event) {
    if (event.key === 'Escape') {
        closeMobileSidebar();
    }
}

const shellStyle = computed(() => ({
    '--sidebar-width': collapsed.value ? '4.5rem' : '16rem',
}));

const contentWidthClass = computed(() => (props.wide ? 'w-full' : 'mx-auto w-full max-w-7xl'));
</script>

<template>
    <div class="min-h-screen bg-slate-100 text-slate-900" :style="shellStyle">
        <a
            href="#main-content"
            class="fixed left-4 top-4 z-50 -translate-y-24 rounded-xl bg-white px-4 py-3 text-sm font-semibold text-slate-950 shadow-lg transition focus:translate-y-0 focus:outline-none focus-visible:ring-2 focus-visible:ring-teal-500"
        >
            Pular para o conteúdo
        </a>

        <AppSidebar
            :items="navigation"
            :user="user"
            :organization="organization"
            :collapsed="collapsed"
            :mobile-open="mobileOpen"
            :home-url="dashboardUrl"
            @close-mobile="closeMobileSidebar"
        />

        <div
            class="min-h-screen lg:pl-[var(--sidebar-width)]"
            :inert="mobileOpen ? '' : undefined"
            :aria-hidden="mobileOpen ? 'true' : undefined"
        >
            <AppTopbar
                :user="user"
                :organization="organization"
                :collapsed="collapsed"
                :mobile-open="mobileOpen"
                :logout-url="logoutUrl"
                @toggle-sidebar="toggleSidebar"
                @toggle-collapse="toggleCollapse"
            />

            <main id="main-content" tabindex="-1" class="px-4 py-6 outline-none sm:px-6 lg:px-8">
                <div :class="contentWidthClass">
                    <PageHeader :title="title" :description="subtitle">
                        <template #actions>
                            <slot name="actions" />
                        </template>
                    </PageHeader>

                    <div v-if="flashSuccess || flashError" class="mt-6 space-y-3">
                        <div
                            v-if="flashSuccess"
                            role="status"
                            class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"
                        >
                            {{ flashSuccess }}
                        </div>
                        <div
                            v-if="flashError"
                            role="alert"
                            class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800"
                        >
                            {{ flashError }}
                        </div>
                    </div>

                    <div class="mt-6">
                        <slot />
                    </div>
                </div>
            </main>
        </div>
    </div>
</template>
