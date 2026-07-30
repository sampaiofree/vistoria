<script setup>
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/components/ui/AppLayout.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import EquipmentStatusActions from '@/components/domain/equipments/EquipmentStatusActions.vue';
import EquipmentDocumentUpload from '@/components/domain/equipments/EquipmentDocumentUpload.vue';
import EquipmentDocumentList from '@/components/domain/equipments/EquipmentDocumentList.vue';

defineProps({
    equipment: {
        type: Object,
        required: true,
    },
    client: {
        type: Object,
        required: true,
    },
    unit: {
        type: Object,
        required: true,
    },
    area: {
        type: Object,
        required: true,
    },
    subarea: {
        type: Object,
        default: null,
    },
    documents: {
        type: Array,
        default: () => [],
    },
    document_types: {
        type: Array,
        required: true,
    },
    can: {
        type: Object,
        required: true,
    },
    index_url: {
        type: String,
        required: true,
    },
    create_url: {
        type: String,
        required: true,
    },
    edit_url: {
        type: String,
        required: true,
    },
    status_url: {
        type: String,
        required: true,
    },
    document_store_url: {
        type: String,
        required: true,
    },
});
</script>

<template>
    <AppLayout
        title="Equipamento"
        subtitle="Resumo cadastral, status operacional e documentos técnicos do ativo."
    >
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <div class="flex flex-wrap items-center gap-3">
                        <h2 class="text-2xl font-semibold text-slate-900">{{ equipment.name }}</h2>
                        <StatusBadge :status="equipment.status" />
                    </div>
                    <p class="mt-2 text-sm text-slate-500">
                        <Link :href="client.show_url" class="font-medium text-teal-700 hover:text-teal-800">{{ client.name }}</Link>
                        /
                        <Link :href="unit.show_url" class="font-medium text-teal-700 hover:text-teal-800">{{ unit.name }}</Link>
                        /
                        <Link :href="area.show_url" class="font-medium text-teal-700 hover:text-teal-800">{{ area.name }}</Link>
                        <span v-if="subarea"> / <Link :href="subarea.show_url" class="font-medium text-teal-700 hover:text-teal-800">{{ subarea.name }}</Link></span>
                    </p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <Link
                        :href="index_url"
                        class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:border-slate-400 hover:text-slate-900"
                    >
                        Voltar
                    </Link>
                    <Link
                        v-if="can.update"
                        :href="edit_url"
                        class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:border-slate-400 hover:text-slate-900"
                    >
                        Editar
                    </Link>
                    <Link
                        v-if="can.create"
                        :href="create_url"
                        class="rounded-lg bg-teal-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-teal-700"
                    >
                        Novo equipamento
                    </Link>
                </div>
            </div>

            <div class="mt-6 flex flex-wrap gap-3">
                <EquipmentStatusActions
                    v-if="can.change_status"
                    :action="status_url"
                    :current-status="equipment.status"
                    entity-label="equipamento"
                />
            </div>

            <dl class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">TAG</dt>
                    <dd class="mt-1 text-sm font-medium text-slate-900">{{ equipment.tag }}</dd>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Fabricante</dt>
                    <dd class="mt-1 text-sm font-medium text-slate-900">{{ equipment.manufacturer ?? '-' }}</dd>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Modelo</dt>
                    <dd class="mt-1 text-sm font-medium text-slate-900">{{ equipment.model ?? '-' }}</dd>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Status</dt>
                    <dd class="mt-1">
                        <StatusBadge :status="equipment.status" />
                    </dd>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Número de série</dt>
                    <dd class="mt-1 text-sm font-medium text-slate-900">{{ equipment.serial_number ?? '-' }}</dd>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Código patrimonial</dt>
                    <dd class="mt-1 text-sm font-medium text-slate-900">{{ equipment.asset_code ?? '-' }}</dd>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Código ABC</dt>
                    <dd class="mt-1 text-sm font-medium text-slate-900">{{ equipment.abc_code ?? '-' }}</dd>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Comissionamento</dt>
                    <dd class="mt-1 text-sm font-medium text-slate-900">{{ equipment.commissioned_at ?? '-' }}</dd>
                </div>
            </dl>

            <div v-if="equipment.installation_location" class="mt-6 rounded-xl border border-slate-200 bg-slate-50 p-4">
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Local de instalação</div>
                <p class="mt-2 text-sm text-slate-700">{{ equipment.installation_location }}</p>
            </div>

            <div v-if="equipment.description" class="mt-6 rounded-xl border border-slate-200 bg-slate-50 p-4">
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Descrição</div>
                <p class="mt-2 whitespace-pre-line text-sm text-slate-700">{{ equipment.description }}</p>
            </div>

            <div v-if="equipment.notes" class="mt-6 rounded-xl border border-slate-200 bg-slate-50 p-4">
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Observações</div>
                <p class="mt-2 whitespace-pre-line text-sm text-slate-700">{{ equipment.notes }}</p>
            </div>

            <div v-if="equipment.status === 'decommissioned'" class="mt-6 rounded-xl border border-amber-200 bg-amber-50 p-4">
                <div class="text-xs font-semibold uppercase tracking-wide text-amber-700">Descomissionamento</div>
                <p v-if="equipment.decommissioned_at" class="mt-2 text-sm text-amber-900">
                    Em {{ equipment.decommissioned_at }}
                </p>
                <p v-if="equipment.decommission_reason" class="mt-2 whitespace-pre-line text-sm text-amber-900">
                    {{ equipment.decommission_reason }}
                </p>
            </div>
        </section>

        <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-1">
                <h3 class="text-lg font-semibold text-slate-900">Documentos técnicos</h3>
                <p class="text-sm text-slate-500">
                    Arquivos privados versionados para este equipamento.
                </p>
            </div>

            <div v-if="can.manage_documents" class="mt-5">
                <EquipmentDocumentUpload
                    :action="document_store_url"
                    :document-types="document_types"
                    :documents="documents"
                />
            </div>

            <div class="mt-6">
                <EquipmentDocumentList
                    :documents="documents"
                    :can-manage="can.manage_documents"
                />
            </div>
        </section>
    </AppLayout>
</template>
