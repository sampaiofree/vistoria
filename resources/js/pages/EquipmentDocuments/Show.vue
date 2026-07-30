<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/components/ui/AppLayout.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import StatusToggleForm from '@/components/ui/StatusToggleForm.vue';

const props = defineProps({
    document: {
        type: Object,
        required: true,
    },
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
    can: {
        type: Object,
        required: true,
    },
    back_url: {
        type: String,
        required: true,
    },
    download_url: {
        type: String,
        required: true,
    },
    status_url: {
        type: String,
        required: true,
    },
});

const currentForm = useForm({});

function formatBytes(bytes) {
    if (!bytes && bytes !== 0) {
        return '-';
    }

    const thresholds = [
        { unit: 'GB', size: 1024 ** 3 },
        { unit: 'MB', size: 1024 ** 2 },
        { unit: 'KB', size: 1024 },
    ];

    for (const threshold of thresholds) {
        if (bytes >= threshold.size) {
            return `${(bytes / threshold.size).toFixed(1)} ${threshold.unit}`;
        }
    }

    return `${bytes} B`;
}

function setCurrent() {
    const confirmed = window.confirm('Definir esta revisão como atual?');

    if (!confirmed) {
        return;
    }

    currentForm.patch(props.document.set_current_url, {
        preserveScroll: true,
    });
}
</script>

<template>
    <AppLayout
        title="Documento técnico"
        subtitle="Detalhe da revisão armazenada em disco privado."
    >
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <div class="flex flex-wrap items-center gap-3">
                        <h2 class="text-2xl font-semibold text-slate-900">{{ document.title }}</h2>
                        <StatusBadge :status="document.status" />
                        <span
                            v-if="document.is_current"
                            class="inline-flex items-center rounded-full border border-teal-200 bg-teal-50 px-2.5 py-1 text-xs font-semibold uppercase tracking-wide text-teal-700"
                        >
                            Atual
                        </span>
                    </div>
                    <p class="mt-2 text-sm text-slate-500">
                        {{ equipment.tag }} · {{ document.document_type_label }}
                    </p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <Link
                        :href="back_url"
                        class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:border-slate-400 hover:text-slate-900"
                    >
                        Voltar
                    </Link>
                    <Link
                        v-if="can.download"
                        :href="download_url"
                        class="rounded-lg border border-teal-200 bg-teal-50 px-4 py-2 text-sm font-medium text-teal-700 transition hover:border-teal-300 hover:bg-teal-100"
                    >
                        Baixar
                    </Link>
                    <StatusToggleForm
                        v-if="can.update_status"
                        :action="status_url"
                        :current-status="document.status"
                        entity-label="documento"
                    />
                    <button
                        v-if="can.set_current && !document.is_current"
                        type="button"
                        :disabled="currentForm.processing"
                        class="rounded-lg border border-indigo-200 bg-indigo-50 px-4 py-2 text-sm font-medium text-indigo-700 transition hover:border-indigo-300 hover:bg-indigo-100 disabled:cursor-not-allowed disabled:opacity-60"
                        @click="setCurrent"
                    >
                        Definir como atual
                    </button>
                </div>
            </div>

            <p class="mt-4 text-sm text-slate-500">
                Arquivo original: {{ document.original_name }}
            </p>

            <dl class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Grupo</dt>
                    <dd class="mt-1 break-all text-sm font-medium text-slate-900">{{ document.document_group }}</dd>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Revisão</dt>
                    <dd class="mt-1 text-sm font-medium text-slate-900">{{ document.revision ?? '-' }}</dd>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Número</dt>
                    <dd class="mt-1 text-sm font-medium text-slate-900">{{ document.document_number ?? '-' }}</dd>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Arquivo</dt>
                    <dd class="mt-1 text-sm font-medium text-slate-900">{{ formatBytes(document.size) }}</dd>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Emitido em</dt>
                    <dd class="mt-1 text-sm font-medium text-slate-900">{{ document.issued_at ?? '-' }}</dd>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Criado em</dt>
                    <dd class="mt-1 text-sm font-medium text-slate-900">{{ document.created_at ?? '-' }}</dd>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">MIME</dt>
                    <dd class="mt-1 text-sm font-medium text-slate-900">{{ document.mime_type }}</dd>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Checksum</dt>
                    <dd class="mt-1 break-all text-sm font-medium text-slate-900">{{ document.checksum }}</dd>
                </div>
            </dl>

            <div v-if="document.description" class="mt-6 rounded-xl border border-slate-200 bg-slate-50 p-4">
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Descrição</div>
                <p class="mt-2 whitespace-pre-line text-sm text-slate-700">{{ document.description }}</p>
            </div>

            <div class="mt-6 rounded-xl border border-slate-200 bg-slate-50 p-4">
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Equipamento vinculado</div>
                <p class="mt-2 text-sm text-slate-700">
                    <Link :href="equipment.show_url" class="font-medium text-teal-700 hover:text-teal-800">
                        {{ equipment.tag }} · {{ equipment.name }}
                    </Link>
                </p>
                <p class="mt-2 text-sm text-slate-500">
                    <Link :href="client.show_url" class="font-medium text-teal-700 hover:text-teal-800">{{ client.name }}</Link>
                    /
                    <Link :href="unit.show_url" class="font-medium text-teal-700 hover:text-teal-800">{{ unit.name }}</Link>
                    /
                    <Link :href="area.show_url" class="font-medium text-teal-700 hover:text-teal-800">{{ area.name }}</Link>
                    <span v-if="subarea"> / <Link :href="subarea.show_url" class="font-medium text-teal-700 hover:text-teal-800">{{ subarea.name }}</Link></span>
                </p>
            </div>
        </section>
    </AppLayout>
</template>
