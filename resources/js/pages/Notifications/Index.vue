<script setup>
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/components/ui/AppLayout.vue';

defineProps({
    notifications: { type: Object, required: true },
    read_all_url: { type: String, required: true },
});
</script>

<template>
    <AppLayout title="Notificações" subtitle="Acompanhe avisos operacionais que precisam da sua atenção.">
        <template #actions>
            <Link
                :href="read_all_url"
                method="patch"
                as="button"
                class="rounded-md border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"
            >
                Marcar todas como lidas
            </Link>
        </template>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div v-if="notifications.data.length" class="divide-y divide-slate-100">
                <Link
                    v-for="notification in notifications.data"
                    :key="notification.id"
                    :href="notification.read_url"
                    method="patch"
                    as="button"
                    class="flex w-full items-start gap-3 px-5 py-4 text-left transition hover:bg-slate-50"
                    :class="notification.read ? 'bg-white' : 'bg-teal-50/50'"
                >
                    <span class="mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full" :class="notification.read ? 'bg-slate-300' : 'bg-teal-600'"></span>
                    <span class="min-w-0 flex-1">
                        <span class="block font-semibold text-slate-950">{{ notification.title }}</span>
                        <span class="mt-1 block text-sm leading-6 text-slate-600">{{ notification.message }}</span>
                        <span class="mt-2 block text-xs text-slate-400">{{ notification.created_at }}</span>
                    </span>
                </Link>
            </div>
            <div v-else class="px-6 py-14 text-center text-sm text-slate-500">Nenhuma notificação registrada.</div>
        </section>

        <nav v-if="notifications.links.length > 3" class="mt-5 flex flex-wrap justify-center gap-2" aria-label="Paginação">
            <Link
                v-for="link in notifications.links"
                :key="link.label"
                :href="link.url || ''"
                class="rounded-md border px-3 py-2 text-sm"
                :class="link.active ? 'border-teal-700 bg-teal-700 text-white' : link.url ? 'border-slate-300 bg-white text-slate-700 hover:bg-slate-50' : 'cursor-not-allowed border-slate-200 text-slate-400'"
                :aria-disabled="!link.url"
                v-html="link.label"
            />
        </nav>
    </AppLayout>
</template>
