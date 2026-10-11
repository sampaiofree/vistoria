<script setup>
defineProps({ rows: { type: Array, default: () => [] } });
</script>

<template>
    <table class="classification-recovery-table">
        <colgroup><col class="recovery-name"><col class="recovery-count"><col class="recovery-total-label"><col class="recovery-total-value"></colgroup>
        <thead>
            <tr><th colspan="4" class="recovery-title">Classificação de Recuperação</th></tr>
            <tr><th>Resumo de Priorização</th><th>Qtde avarias</th><th colspan="2">Quantitativo</th></tr>
        </thead>
        <tbody>
            <tr v-for="row in rows" :key="`${row.category}-${row.classification_code}`">
                <td class="recovery-label" :style="{ backgroundColor: row.color, color: row.text_color }">{{ row.label }}</td>
                <td>{{ row.defect_count }}</td>
                <td>{{ row.total_label }}</td>
                <td>{{ row.quantity }}</td>
            </tr>
        </tbody>
    </table>
</template>

<style scoped>
.classification-recovery-table { width: 100%; border-collapse: collapse; table-layout: fixed; font-family: Georgia, 'Times New Roman', serif; font-size: 8pt; }
.classification-recovery-table th, .classification-recovery-table td { border: 1px solid #111827; padding: 1mm 1.25mm; line-height: 1.15; text-align: center; vertical-align: middle; overflow-wrap: anywhere; }
.classification-recovery-table th { font-weight: 400; }
.classification-recovery-table .recovery-title { border-top-width: 2px; font-size: 10pt; }
.recovery-name { width: 34%; }.recovery-count { width: 16%; }.recovery-total-label { width: 25%; }.recovery-total-value { width: 25%; }
.classification-recovery-table .recovery-label { font-weight: 700; text-transform: uppercase; }
</style>
