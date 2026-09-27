<script setup>
import ClassificationEquipmentSummaryTable from '@/components/domain/inspections/ClassificationEquipmentSummaryTable.vue';
import ClassificationSummaryTable from '@/components/domain/inspections/ClassificationSummaryTable.vue';
import ClassificationSpecialAssessmentTable from '@/components/domain/inspections/ClassificationSpecialAssessmentTable.vue';

defineProps({
    summary: { type: Object, required: true },
    includeHeader: { type: Boolean, default: false },
    categories: { type: Array, default: () => [] },
    specialRows: { type: Array, default: () => [] },
    showSpecial: { type: Boolean, default: false },
    classificationContinuation: { type: Boolean, default: false },
    specialContinuation: { type: Boolean, default: false },
});
</script>

<template>
    <div class="classification-report-tables">
        <template v-if="includeHeader">
            <h2 class="classification-report-heading">1. RESUMO DA CLASSIFICAÇÃO DO EQUIPAMENTO – GUT</h2>
            <ClassificationEquipmentSummaryTable :header="summary.header" variant="report" />
        </template>

        <template v-if="categories.length">
            <h3 class="classification-report-table-title">Tabela 2 – Resumo das classificações das avarias e seus quantitativos.<span v-if="classificationContinuation"> — CONTINUAÇÃO</span></h3>
            <ClassificationSummaryTable :summary="summary" :categories="categories" variant="report" />
        </template>

        <template v-if="showSpecial">
            <h3 class="classification-report-table-title">Tabela 3 – END’S, TRABALHOS DE ENGENHARIA E CI’S.<span v-if="specialContinuation"> — CONTINUAÇÃO</span></h3>
            <ClassificationSpecialAssessmentTable :rows="specialRows" variant="report" />
        </template>
    </div>
</template>

<style scoped>
.classification-report-tables { font-family: Georgia, 'Times New Roman', serif; }
.classification-report-heading { margin: 0 0 5mm; font-size: 10pt; font-weight: 700; text-align: left; }
.classification-report-table-title { margin: 4mm 0 2mm; font-size: 9pt; font-weight: 400; text-align: center; }
</style>
