<script setup>
const props = defineProps({
    summary: { type: Object, required: true },
    editable: { type: Boolean, default: false },
    m2Links: { type: Array, default: () => [] },
    variant: { type: String, default: 'workspace' },
});

const emit = defineEmits(['update:m2']);

function validHexColor(color) {
    return /^#[0-9A-F]{6}$/i.test(String(color || ''));
}

function colorStyle(color) {
    if (!validHexColor(color)) return {};

    const red = Number.parseInt(color.slice(1, 3), 16);
    const green = Number.parseInt(color.slice(3, 5), 16);
    const blue = Number.parseInt(color.slice(5, 7), 16);
    const brightness = ((red * 299) + (green * 587) + (blue * 114)) / 1000;

    return { backgroundColor: color, color: brightness >= 150 ? '#111827' : '#ffffff' };
}

function m2Value(category, classificationCode) {
    return props.m2Links.find((link) => link.category === category && link.classification_code === classificationCode)?.sap_number ?? '';
}

function updateM2(category, classificationCode, event) {
    emit('update:m2', { category, classificationCode, sapNumber: event.target.value });
}
</script>

<template>
    <table class="classification-summary-table" :class="`classification-summary-table-${variant}`">
        <colgroup>
            <col class="classification-summary-prioritization">
            <col class="classification-summary-classification">
            <col class="classification-summary-count">
            <col class="classification-summary-date">
            <col class="classification-summary-quantity">
            <col class="classification-summary-m2">
        </colgroup>
        <thead><tr><th>RESUMO DA PRIORIZAÇÃO</th><th>CLASSIFICAÇÃO</th><th>QTDE. AVARIAS</th><th>DATA</th><th>QUANTITATIVO</th><th>NOTA M2 (SAP)</th></tr></thead>
        <tbody v-for="category in summary.categories" :key="category.code">
            <tr v-for="(row, index) in category.report_rows" :key="row.classification_code">
                <td v-if="index === 0" :rowspan="category.report_rows.length" class="classification-summary-prioritization-cell">{{ category.report_name }}</td>
                <td class="classification-summary-classification-cell" :style="colorStyle(row.color)">{{ row.classification_code }}</td>
                <td>{{ row.defect_count || '—' }}</td>
                <td></td>
                <td>{{ category.code === 'TEL' ? '—' : (row.quantity?.display || '—') }}</td>
                <td>
                    <input
                        v-if="editable && row.defect_count > 0"
                        :value="m2Value(category.code, row.classification_code)"
                        class="classification-summary-m2-input"
                        maxlength="100"
                        :aria-label="`Nota M2 para ${row.classification_code}`"
                        @input="updateM2(category.code, row.classification_code, $event)"
                    >
                    <template v-else>{{ row.sap_m2_number || '—' }}</template>
                </td>
            </tr>
            <tr class="classification-summary-total">
                <td colspan="2">Classificação do dano mais crítico:</td>
                <td :style="colorStyle(category.most_critical_color)">{{ category.most_critical || '—' }}</td>
                <td colspan="2">Total {{ category.report_name }}<template v-if="category.report_total.unit"> [{{ category.report_total.unit }}]</template></td>
                <td>{{ category.report_total.value === null ? '' : category.report_total.display }}</td>
            </tr>
        </tbody>
    </table>
</template>

<style scoped>
.classification-summary-table { width: 100%; border-collapse: collapse; table-layout: fixed; font-family: Georgia, 'Times New Roman', serif; }
.classification-summary-table th, .classification-summary-table td { border: 1px solid #111827; padding: 1mm 1.25mm; line-height: 1.05; text-align: center; vertical-align: middle; overflow-wrap: anywhere; }
.classification-summary-table th { background: #e2e8f0; font-weight: 700; }
.classification-summary-table tr > :first-child { border-left: 0; }
.classification-summary-table tr > :last-child { border-right: 0; }
.classification-summary-prioritization { width: 24%; }.classification-summary-classification { width: 13%; }.classification-summary-count { width: 11%; }.classification-summary-date { width: 14%; }.classification-summary-quantity { width: 15%; }.classification-summary-m2 { width: 23%; }
.classification-summary-prioritization-cell { font-size: 9pt; }.classification-summary-classification-cell { font-size: 8.5pt; font-weight: 700; }.classification-summary-total td { background: #f8fafc; font-size: 8pt; font-weight: 700; }
.classification-summary-table-report { font-size: 7.2pt; }.classification-summary-table-report th { font-size: 7pt; }
.classification-summary-table-workspace { min-width: 58rem; font-size: .875rem; }.classification-summary-table-workspace th, .classification-summary-table-workspace td { padding: .7rem .75rem; }.classification-summary-table-workspace th { font-family: inherit; font-size: .75rem; }.classification-summary-table-workspace .classification-summary-prioritization-cell { font-family: inherit; font-size: .875rem; }.classification-summary-table-workspace .classification-summary-classification-cell { font-family: inherit; font-size: .875rem; }.classification-summary-m2-input { width: 100%; min-width: 8rem; border: 1px solid #94a3b8; border-radius: .375rem; padding: .4rem .5rem; font-family: inherit; text-align: center; }
</style>
