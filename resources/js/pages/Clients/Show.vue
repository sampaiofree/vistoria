<script setup>
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/components/ui/AppLayout.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import StatusToggleForm from '@/components/ui/StatusToggleForm.vue';
import ClientUnitList from '@/components/domain/clients/ClientUnitList.vue';
import { formatDocument, formatPhone } from '@/lib/formatters';

defineProps({
    client: {
        type: Object,
        required: true,
    },
    units: {
        type: Object,
        required: true,
    },
    can: {
        type: Object,
        required: true,
    },
});
</script>

<template>
    <AppLayout
        title="Cliente"
        subtitle="Detalhes cadastrais e hierarquia operacional vinculada ao cliente."
    >
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <div class="flex flex-wrap items-center gap-3">
                        <h2 class="text-2xl font-semibold text-slate-900">{{ client.name }}</h2>
                        <StatusBadge :status="client.status" />
                    </div>
                    <p v-if="client.legal_name" class="mt-2 text-sm text-slate-500">
                        {{ client.legal_name }}
                    </p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <Link
                        :href="client.show_url"
                        class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:border-slate-400 hover:text-slate-900"
                    >
                        Recarregar
                    </Link>
                    <Link
                        v-if="can.update"
                        :href="client.edit_url"
                        class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:border-slate-400 hover:text-slate-900"
                    >
                        Editar
                    </Link>
                    <StatusToggleForm
                        v-if="can.update"
                        :action="client.status_url"
                        :current-status="client.status"
                        entity-label="cliente"
                    />
                    <Link
                        v-if="can.create_unit"
                        :href="client.create_unit_url"
                        class="rounded-lg bg-teal-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-teal-700"
                    >
                        Nova unidade
                    </Link>
                </div>
            </div>

            <dl class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Documento</dt>
                    <dd class="mt-1 text-sm font-medium text-slate-900">{{ formatDocument(client.document) }}</dd>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">E-mail</dt>
                    <dd class="mt-1 text-sm font-medium text-slate-900">{{ client.email ?? '-' }}</dd>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Telefone</dt>
                    <dd class="mt-1 text-sm font-medium text-slate-900">{{ formatPhone(client.phone) }}</dd>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Status</dt>
                    <dd class="mt-1">
                        <StatusBadge :status="client.status" />
                    </dd>
                </div>
            </dl>

            <div v-if="client.notes" class="mt-6 rounded-xl border border-slate-200 bg-slate-50 p-4">
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Observacoes</div>
                <p class="mt-2 whitespace-pre-line text-sm text-slate-700">{{ client.notes }}</p>
            </div>
        </section>

        <div class="mt-6">
            <ClientUnitList :units="units" />
        </div>
    </AppLayout>
</template>
