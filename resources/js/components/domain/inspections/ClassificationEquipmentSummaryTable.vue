<script setup>
import { computed } from 'vue';

const props = defineProps({
    header: { type: Object, default: () => ({}) },
    editable: { type: Boolean, default: false },
    values: { type: Object, default: null },
    errors: { type: Object, default: () => ({}) },
    variant: { type: String, default: 'workspace' },
});

const emit = defineEmits(['update:value']);

function valueFor(field) {
    return props.values?.[field] ?? props.header[field] ?? '';
}

function update(field, event) {
    emit('update:value', { field, value: event.target.value });
}

const criticalityStyle = computed(() => {
    const color = props.header.criticality_color;
    if (!color) return {};

    return {
        backgroundColor: color,
        color: ['#FF0000', '#0070C0'].includes(color.toUpperCase()) ? '#ffffff' : '#111827',
    };
});
</script>

<template>
    <table class="classification-equipment-summary-table" :class="`classification-equipment-summary-table-${variant}`">
        <colgroup>
            <col class="classification-equipment-area"><col class="classification-equipment-subarea"><col class="classification-equipment-installation">
            <col class="classification-equipment-abc"><col class="classification-equipment-date"><col class="classification-equipment-criticality">
        </colgroup>
        <thead>
            <tr><th>ÁREA</th><th>SUBÁREA</th><th>LOCAL DE INSTALAÇÃO</th><th>CÓD. ABC</th><th>DATA DA INSP.</th><th>CRITICIDADE</th></tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ header.area || '—' }}</td><td>{{ header.subarea || '—' }}</td><td>{{ header.installation_location || '—' }}</td><td>{{ header.abc_code || '—' }}</td>
                <td class="classification-equipment-editable">
                    <input v-if="editable" :value="valueFor('inspected_on')" type="date" @input="update('inspected_on', $event)">
                    <span v-else>{{ header.inspection_date || '—' }}</span>
                    <p v-if="errors.inspected_on" class="classification-equipment-error">{{ errors.inspected_on }}</p>
                </td>
                <td class="classification-equipment-criticality-cell" :style="criticalityStyle">{{ header.criticality || '' }}</td>
            </tr>
            <tr class="classification-equipment-labels"><th>EQUIPAMENTO</th><th>TAG</th><th>DESENHO GERAL</th><th>ORDEM</th><th colspan="2">PROC. INSPEÇÃO</th></tr>
            <tr>
                <td>{{ header.equipment || '—' }}</td><td>{{ header.tag || '—' }}</td>
                <td>{{ header.general_drawing || '—' }}</td>
                <td>{{ header.work_order || '—' }}</td>
                <td colspan="2">{{ header.procedure_number || '—' }}</td>
            </tr>
        </tbody>
    </table>
</template>

<style scoped>
.classification-equipment-summary-table { width: 100%; border-collapse: collapse; table-layout: fixed; font-family: Georgia, 'Times New Roman', serif; text-align: center; }
.classification-equipment-summary-table th, .classification-equipment-summary-table td { border: 1px solid #111827; padding: 1.3mm 1.5mm; line-height: 1.1; vertical-align: middle; overflow-wrap: anywhere; }
.classification-equipment-summary-table th { background: #e2e8f0; font-weight: 700; }.classification-equipment-summary-table tr > :first-child { border-left: 0; }.classification-equipment-summary-table tr > :last-child { border-right: 0; }
.classification-equipment-labels th { border-top-width: 1.5px; }.classification-equipment-area { width: 15%; }.classification-equipment-subarea { width: 19%; }.classification-equipment-installation { width: 24%; }.classification-equipment-abc { width: 15%; }.classification-equipment-date { width: 14%; }.classification-equipment-criticality { width: 13%; }
.classification-equipment-editable input { width: 100%; border: 1px solid #94a3b8; border-radius: .375rem; padding: .4rem .5rem; font: inherit; text-align: center; }.classification-equipment-error { margin: .25rem 0 0; color: #e11d48; font-family: inherit; font-size: .75rem; text-align: left; }
.classification-equipment-summary-table-workspace { min-width: 58rem; font-family: inherit; font-size: .875rem; color: #1e293b; }.classification-equipment-summary-table-workspace th { font-family: inherit; font-size: .75rem; }.classification-equipment-summary-table-workspace .classification-equipment-editable { padding: .375rem; }
.classification-equipment-summary-table-report { font-size: 8pt; }.classification-equipment-summary-table-report th { font-size: 7.5pt; }.classification-equipment-summary-table-report th, .classification-equipment-summary-table-report td { line-height: 1.25; vertical-align: middle; }.classification-equipment-summary-table-report .classification-equipment-editable { padding: 1.3mm 1.5mm; }.classification-equipment-summary-table-report input { display: none; }
</style>
