<script setup>
const props = defineProps({
    rows: { type: Array, default: () => [] },
    editable: { type: Boolean, default: false },
    values: { type: Array, default: () => [] },
    variant: { type: String, default: 'workspace' },
});

const emit = defineEmits(['update:value']);

function valueFor(assessmentPublicId, field) {
    return props.values.find((value) => value.assessment_public_id === assessmentPublicId)?.[field] ?? '';
}

function update(assessmentPublicId, field, event) {
    emit('update:value', { assessmentPublicId, field, value: event.target.value });
}
</script>

<template>
    <table class="special-assessment-table" :class="`special-assessment-table-${variant}`">
        <colgroup>
            <col class="special-assessment-type">
            <col class="special-assessment-photos">
            <col class="special-assessment-service">
            <col class="special-assessment-priority">
            <col class="special-assessment-note">
        </colgroup>
        <thead>
            <tr><th colspan="5">END’S, TRABALHOS DE ENGENHARIA E CI’S</th></tr>
            <tr><th>TIPO</th><th>FOTOS</th><th>SERVIÇO</th><th>PRIORIDADE</th><th>NOTA</th></tr>
        </thead>
        <tbody>
            <tr v-for="row in rows" :key="row.assessment_public_id">
                <td>{{ row.code }}</td>
                <td>{{ row.photos }}</td>
                <td v-for="field in ['service', 'priority', 'note']" :key="field">
                    <input
                        v-if="editable"
                        :value="valueFor(row.assessment_public_id, field)"
                        class="special-assessment-input"
                        maxlength="100"
                        :aria-label="`${field} para ${row.code}`"
                        @input="update(row.assessment_public_id, field, $event)"
                    >
                    <template v-else>{{ row[field] || '—' }}</template>
                </td>
            </tr>
            <tr v-if="rows.length === 0">
                <td colspan="5" class="special-assessment-empty">Nenhuma avaria elegível nesta inspeção.</td>
            </tr>
        </tbody>
    </table>
</template>

<style scoped>
.special-assessment-table { width: 100%; min-width: 52rem; border-collapse: collapse; table-layout: fixed; font-family: Georgia, 'Times New Roman', serif; font-size: .875rem; }
.special-assessment-table th, .special-assessment-table td { border: 1px solid #111827; padding: .7rem .75rem; line-height: 1.1; text-align: center; vertical-align: middle; overflow-wrap: anywhere; }
.special-assessment-table th { background: #e2e8f0; font-size: .75rem; font-weight: 700; }
.special-assessment-table thead tr:first-child th { background: white; font-size: .9rem; }
.special-assessment-table tr > :first-child { border-left: 0; }.special-assessment-table tr > :last-child { border-right: 0; }
.special-assessment-type { width: 24%; }.special-assessment-photos { width: 16%; }.special-assessment-service { width: 20%; }.special-assessment-priority { width: 20%; }.special-assessment-note { width: 20%; }
.special-assessment-input { width: 100%; border: 1px solid #94a3b8; border-radius: .375rem; padding: .4rem .5rem; font-family: inherit; text-align: center; }
.special-assessment-empty { color: #64748b; font-family: inherit; }
.special-assessment-table-report { min-width: 0; font-size: 7.5pt; }.special-assessment-table-report th, .special-assessment-table-report td { padding: 1mm 1.25mm; }.special-assessment-table-report th { font-size: 7pt; }.special-assessment-table-report thead tr:first-child th { font-size: 8pt; }
</style>
