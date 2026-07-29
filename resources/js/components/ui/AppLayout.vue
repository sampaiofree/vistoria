<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

defineProps({
    title: {
        type: String,
        required: true,
    },
    subtitle: {
        type: String,
        default: '',
    },
});

const page = usePage();

const user = computed(() => page.props.auth?.user ?? null);
const organization = computed(() => user.value?.organization ?? null);

const navigation = computed(() => [
    { label: 'Dashboard', href: '/dashboard', active: page.url.startsWith('/dashboard') },
    { label: 'Clientes', href: '/clients', active: page.url.startsWith('/clients') },
    { label: 'Inspeções', href: '/inspections', active: page.url.startsWith('/inspections') },
]);

const flashSuccess = computed(() => page.props.flash?.success ?? '');
const flashError = computed(() => page.props.flash?.error ?? '');
</script>

<template>
    <div class="min-h-screen bg-slate-100 text-slate-900">
        <header class="border-b border-slate-200 bg-white">
            <div class="mx-auto flex max-w-7xl flex-col gap-4 px-4 py-4 sm:px-6 lg:px-8">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div class="flex flex-wrap items-center gap-3">
                        <div>
                            <div class="text-sm font-semibold uppercase tracking-[0.16em] text-slate-500">
                                Vistoria
                            </div>
                            <div class="text-lg font-semibold text-slate-900">
                                {{ title }}
                            </div>
                        </div>
                        <nav class="flex flex-wrap gap-2">
                            <Link
                                v-for="item in navigation"
                                :key="item.href"
                                :href="item.href"
                                class="rounded-full border px-3 py-1.5 text-sm font-medium transition"
                                :class="item.active
                                    ? 'border-teal-600 bg-teal-50 text-teal-700'
                                    : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300 hover:text-slate-900'"
                            >
                                {{ item.label }}
                            </Link>
                        </nav>
                    </div>

                    <div class="flex flex-wrap items-center gap-3 text-sm text-slate-600">
                        <div class="text-right">
                            <div class="font-medium text-slate-900">{{ user?.name }}</div>
                            <div v-if="organization" class="text-slate-500">
                                {{ organization.name }}
                            </div>
                        </div>

                        <Link
                            href="/logout"
                            method="post"
                            as="button"
                            class="rounded-full border border-slate-200 bg-white px-4 py-2 font-medium text-slate-700 transition hover:border-slate-300 hover:text-slate-900"
                        >
                            Sair
                        </Link>
                    </div>
                </div>

                <p v-if="subtitle" class="max-w-4xl text-sm text-slate-600">
                    {{ subtitle }}
                </p>
            </div>
        </header>

        <main class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
            <div v-if="flashSuccess || flashError" class="mb-6 space-y-3">
                <div
                    v-if="flashSuccess"
                    class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"
                >
                    {{ flashSuccess }}
                </div>
                <div
                    v-if="flashError"
                    class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800"
                >
                    {{ flashError }}
                </div>
            </div>

            <slot />
        </main>
    </div>
</template>
