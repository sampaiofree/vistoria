<script setup>
import { computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import ClassificationEquipmentSummaryTable from '@/components/domain/inspections/ClassificationEquipmentSummaryTable.vue';

const props = defineProps({
    header: { type: Object, default: () => ({}) },
    updateUrl: { type: String, default: null },
    editable: { type: Boolean, default: false },
});

const form = useForm({
    inspected_on: props.header.inspection_date_input ?? '',
});

const canEdit = computed(() => props.editable && Boolean(props.updateUrl));
watch(() => props.header, (header) => {
    form.defaults({
        inspected_on: header.inspection_date_input ?? '',
    });
    form.reset();
}, { deep: true });

function save() {
    if (!canEdit.value) return;

    form.put(props.updateUrl, { preserveScroll: true });
}

function updateValue({ field, value }) {
    form[field] = value;
}
</script>

<template>
    <section class="mx-auto max-w-7xl rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.16em] text-teal-700">Resumo técnico</p>
                <h2 class="mt-2 text-xl font-semibold text-slate-950">1 — Resumo da Classificação do Equipamento – GUT</h2>
                <p class="mt-1 text-sm text-slate-500">Tabela 1 — Resumo do equipamento.</p>
            </div>
            <button v-if="canEdit" type="button" class="rounded-xl bg-teal-700 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50" :disabled="form.processing" @click="save">
                Salvar data da inspeção
            </button>
        </div>

        <div class="mt-6 overflow-x-auto">
            <ClassificationEquipmentSummaryTable :header="header" :editable="canEdit" :values="form" :errors="form.errors" @update:value="updateValue" />
        </div>
    </section>
</template>
