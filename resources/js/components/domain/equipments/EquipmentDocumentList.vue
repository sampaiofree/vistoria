<script setup>
import { computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import StatusToggleForm from '@/components/ui/StatusToggleForm.vue';

const props = defineProps({
    documents: {
        type: Array,
        default: () => [],
    },
    canManage: {
        type: Boolean,
        default: false,
    },
});

const currentForm = useForm({});

const groupedDocuments = computed(() => {
    const groups = new Map();

    for (const document of props.documents) {
        if (!groups.has(document.document_group)) {
            groups.set(document.document_group, {
                document_group: document.document_group,
                title: document.title,
                document_type_label: document.document_type_label,
                documents: [],
            });
        }

        groups.get(document.document_group).documents.push(document);
    }

    return Array.from(groups.values());
});

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

function setCurrent(url) {
    const confirmed = window.confirm('Definir esta revisão como atual?');

    if (!confirmed) {
        return;
    }

    currentForm.patch(url, {
        preserveScroll: true,
    });
}
</script>

<template>
    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h3 class="text-lg font-semibold text-slate-900">Documentos</h3>
                <p class="text-sm text-slate-500">
                    Revisões atuais e anteriores agrupadas por documento técnico.
                </p>
            </div>
            <div class="text-sm text-slate-500">
                {{ documents.length }} registro(s)
            </div>
        </div>

        <div v-if="groupedDocuments.length === 0" class="mt-5 rounded-xl border border-dashed border-slate-300 bg-slate-50 p-6 text-sm text-slate-500">
            Nenhum documento cadastrado para este equipamento.
        </div>

        <div v-else class="mt-5 space-y-4">
            <article v-for="group in groupedDocuments" :key="group.document_group" class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                <div class="flex flex-col gap-2 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h4 class="text-base font-semibold text-slate-900">{{ group.title }}</h4>
                            <span class="rounded-full border border-slate-200 bg-white px-2.5 py-1 text-xs font-semibold uppercase tracking-wide text-slate-600">
                                {{ group.document_type_label }}
                            </span>
                        </div>
                        <p class="mt-1 text-xs text-slate-500">
                            Grupo {{ group.document_group }}
                        </p>
                    </div>

                    <div class="text-sm text-slate-500">
                        {{ group.documents.length }} revisão(ões)
                    </div>
                </div>

                <div class="mt-4 space-y-3">
                    <div
                        v-for="document in group.documents"
                        :key="document.public_id"
                        class="rounded-lg border border-slate-200 bg-white p-4"
                    >
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                            <div class="space-y-2">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="text-sm font-semibold text-slate-900">
                                        {{ document.original_name }}
                                    </span>
                                    <StatusBadge :status="document.status" />
                                    <span
                                        v-if="document.is_current"
                                        class="inline-flex items-center rounded-full border border-teal-200 bg-teal-50 px-2.5 py-1 text-xs font-semibold uppercase tracking-wide text-teal-700"
                                    >
                                        Atual
                                    </span>
                                </div>

                                <div class="flex flex-wrap gap-x-4 gap-y-1 text-sm text-slate-600">
                                    <span>Revisão {{ document.revision ?? '—' }}</span>
                                    <span v-if="document.document_number">Doc. {{ document.document_number }}</span>
                                    <span v-if="document.issued_at">Emissão {{ document.issued_at }}</span>
                                    <span>{{ formatBytes(document.size) }}</span>
                                </div>

                                <p v-if="document.description" class="whitespace-pre-line text-sm text-slate-600">
                                    {{ document.description }}
                                </p>

                                <p v-if="document.uploaded_by" class="text-xs text-slate-500">
                                    Enviado por {{ document.uploaded_by.name }}
                                    <span v-if="document.created_at"> · {{ document.created_at }}</span>
                                </p>
                            </div>

                            <div class="flex flex-wrap items-center gap-2">
                                <Link
                                    :href="document.show_url"
                                    class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:border-slate-400 hover:text-slate-900"
                                >
                                    Detalhes
                                </Link>

                                <Link
                                    :href="document.download_url"
                                    class="rounded-lg border border-teal-200 bg-teal-50 px-3 py-2 text-sm font-medium text-teal-700 transition hover:border-teal-300 hover:bg-teal-100"
                                >
                                    Baixar
                                </Link>

                                <button
                                    v-if="canManage && !document.is_current"
                                    type="button"
                                    :disabled="currentForm.processing"
                                    class="rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-2 text-sm font-medium text-indigo-700 transition hover:border-indigo-300 hover:bg-indigo-100 disabled:cursor-not-allowed disabled:opacity-60"
                                    @click="setCurrent(document.set_current_url)"
                                >
                                    Definir como atual
                                </button>

                                <StatusToggleForm
                                    v-if="canManage"
                                    :action="document.status_url"
                                    :current-status="document.status"
                                    entity-label="documento"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </article>
        </div>
    </section>
</template>
