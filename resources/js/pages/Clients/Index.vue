<script setup>
import { Link, useForm } from '@inertiajs/vue3';
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

const form = useForm({
    search: props.filters.search ?? '',
});

function submit() {
    form.get('/clients', {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}
</script>

<template>
    <AppLayout
        title="Clientes"
        subtitle="Cadastro da base operacional vinculada a esta organizacao."
    >
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <form class="flex flex-1 flex-col gap-3 sm:flex-row" @submit.prevent="submit">
                    <label class="flex-1">
                        <span class="sr-only">Pesquisar clientes</span>
                        <input
                            v-model="form.search"
                            type="search"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none transition focus:border-teal-600 focus:ring-2 focus:ring-teal-100"
                            placeholder="Buscar por nome, razao social ou documento"
                        >
                    </label>
                    <button
                        type="submit"
                        class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-slate-50 px-4 py-2 text-sm font-medium text-slate-700 transition hover:border-slate-400 hover:text-slate-900"
                    >
                        Buscar
                    </button>
                </form>

                <Link
                    v-if="can.create"
                    :href="create_url"
                    class="inline-flex items-center justify-center rounded-lg bg-teal-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-teal-700"
                >
                    Novo cliente
                </Link>
            </div>
        </section>

        <section class="mt-6 rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4">
                <div class="text-sm font-semibold text-slate-900">
                    Clientes
                </div>
                <div class="text-sm text-slate-500">
                    {{ clients.total }} registro(s)
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <th class="px-5 py-3">Cliente</th>
                            <th class="px-5 py-3">Documento</th>
                            <th class="px-5 py-3">Unidades</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3 text-right">Acoes</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 bg-white">
                        <tr v-for="client in clients.data" :key="client.public_id">
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
                            <td class="px-5 py-4 text-sm text-slate-600">
                                {{ client.units_count }}
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
