<script setup>
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/components/ui/AppLayout.vue';
import Pagination from '@/components/ui/Pagination.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import { formatDocument, formatPhone } from '@/lib/formatters';

const props = defineProps({
    clients: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        required: true,
    },
    can: {
        type: Object,
        required: true,
    },
    create_url: {
        type: String,
        required: true,
    },
});
</script>

<template>
    <AppLayout
        title="Cliente"
        subtitle="Cadastro único da base operacional vinculada a esta organização."
    >
        <template #actions>
            <Link
                v-if="can.create"
                :href="create_url"
                class="rounded-xl bg-teal-700 px-4 py-2.5 text-sm font-semibold text-white"
            >
                Cadastrar cliente
            </Link>
        </template>

        <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4">
                <div class="text-sm font-semibold text-slate-900">
                    Cliente
                </div>
                <div class="text-sm text-slate-500">
                    {{ clients.total }} registro(s)
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <th class="px-5 py-3">Logo</th>
                            <th class="px-5 py-3">Cliente</th>
                            <th class="px-5 py-3">Documento</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3 text-right">Acoes</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 bg-white">
                        <tr v-for="client in clients.data" :key="client.public_id">
                            <td class="px-5 py-4">
                                <img
                                    v-if="client.logo_url"
                                    :src="client.logo_url"
                                    :alt="`Logotipo de ${client.name}`"
                                    class="h-10 w-16 rounded border border-slate-200 bg-slate-50 object-contain p-1"
                                >
                                <span v-else class="text-sm text-slate-400">-</span>
                            </td>
                            <td class="px-5 py-4">
                                <div class="font-semibold text-slate-900">{{ client.name }}</div>
                                <div v-if="client.legal_name" class="text-sm text-slate-500">
                                    {{ client.legal_name }}
                                </div>
                                <div v-if="client.email" class="text-sm text-slate-500">
                                    {{ client.email }}
                                </div>
                            </td>
                            <td class="px-5 py-4 text-sm text-slate-600">
                                {{ formatDocument(client.document) }}
                                <div v-if="client.phone" class="text-slate-500">
                                    {{ formatPhone(client.phone) }}
                                </div>
                            </td>
                            <td class="px-5 py-4">
                                <StatusBadge :status="client.status" />
                            </td>
                            <td class="px-5 py-4">
                                <div class="flex justify-end gap-2">
                                    <Link
                                        :href="client.show_url"
                                        class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:border-slate-400 hover:text-slate-900"
                                    >
                                        Ver
                                    </Link>
                                    <Link
                                        v-if="client.can_update"
                                        :href="client.edit_url"
                                        class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:border-slate-400 hover:text-slate-900"
                                    >
                                        Editar
                                    </Link>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="clients.data.length === 0">
                            <td colspan="5" class="px-5 py-10 text-center text-sm text-slate-500">
                                Nenhum cliente encontrado.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="border-t border-slate-200 px-5 py-4">
                <Pagination :links="clients.links" />
            </div>
        </section>
    </AppLayout>
</template>
