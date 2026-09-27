<script setup>
import { computed } from 'vue';

const props = defineProps({ worksheet: { type: Object, required: true } });
const tableWidth = computed(() => props.worksheet.columns.reduce((total, column) => total + column.width * 7 + 5, 0));
const themeStyle = computed(() => ({
    '--worksheet-header-background': props.worksheet.theme.header_background,
    '--worksheet-header-color': props.worksheet.theme.header_color,
    '--worksheet-border': props.worksheet.theme.border,
    color: props.worksheet.theme.text,
    width: `${tableWidth.value}px`,
}));

function cellStyle(row, column) {
    const cell = row.cells[column.key];
    return {
        backgroundColor: cell.background || column.background,
        color: cell.color || undefined,
        textAlign: ['number', 'quantity'].includes(column.type) ? 'center' : 'left',
    };
}
</script>

<template>
    <div class="quantitative-scroll" tabindex="0" role="region" aria-label="Planilha de quantitativo das avarias">
        <table class="quantitative-sheet" :style="themeStyle" aria-label="Quantitativo padrão de avarias">
            <colgroup>
                <col v-for="column in worksheet.columns" :key="column.key" :style="{ width: `${column.width * 7 + 5}px` }">
            </colgroup>
            <thead>
                <tr><th :colspan="worksheet.columns.length" class="quantitative-title">{{ worksheet.title }}</th></tr>
                <tr v-for="row in worksheet.header_rows" :key="row.key" class="quantitative-metadata">
                    <td colspan="3"></td>
                    <th scope="row">{{ row.label }}</th>
                    <td colspan="6" class="quantitative-metadata-value">{{ worksheet.header[row.key].display }}</td>
                    <th scope="row" class="quantitative-secondary-label">{{ row.secondary_label }}</th>
                    <td colspan="2" :class="{ 'quantitative-metadata-value': row.secondary_key }">{{ row.secondary_key ? worksheet.header[row.secondary_key].display : '' }}</td>
                    <td></td>
                </tr>
                <tr aria-hidden="true"><td :colspan="worksheet.columns.length" class="quantitative-spacer"></td></tr>
                <tr class="quantitative-column-headings">
                    <th v-for="column in worksheet.columns" :key="column.key" scope="col">{{ column.label }}</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="row in worksheet.rows" :key="row.assessment_public_id">
                    <td v-for="column in worksheet.columns" :key="column.key" :style="cellStyle(row, column)">{{ row.cells[column.key].display }}</td>
                </tr>
            </tbody>
        </table>
        <p v-if="!worksheet.rows.length" class="quantitative-empty">Nenhuma avaria publicada e ativa nesta inspeção.</p>
    </div>
</template>

<style scoped>
.quantitative-scroll { max-height: 75vh; overflow: auto; background: #fff; }
.quantitative-scroll:focus-visible { outline: 2px solid #0f766e; outline-offset: -2px; }
.quantitative-sheet { table-layout: fixed; border-collapse: separate; border-spacing: 0; font-family: Arial, sans-serif; font-size: 13px; }
.quantitative-title { padding: 18px 12px; font-size: 28px; line-height: 1.3; text-align: center; font-weight: 700; }
.quantitative-metadata th, .quantitative-metadata td { height: 30px; padding: 5px 8px; overflow-wrap: anywhere; }
.quantitative-metadata th { text-align: right; }
.quantitative-metadata .quantitative-secondary-label { text-align: left; font-weight: 400; }
.quantitative-metadata-value { background: #ffffcc; border: 1px solid var(--worksheet-border); }
.quantitative-spacer { height: 16px; }
.quantitative-column-headings th { position: sticky; top: 0; z-index: 1; height: 58px; padding: 8px; background: var(--worksheet-header-background); color: var(--worksheet-header-color); text-align: left; font-weight: 700; border-top: 1px solid var(--worksheet-border); }
.quantitative-column-headings th, tbody td { border-right: 1px solid var(--worksheet-border); border-bottom: 1px solid var(--worksheet-border); overflow-wrap: anywhere; }
.quantitative-column-headings th:first-child, tbody td:first-child { border-left: 1px solid var(--worksheet-border); }
tbody td { height: 30px; padding: 6px 8px; vertical-align: middle; white-space: pre-wrap; }
.quantitative-empty { position: sticky; left: 0; width: 100%; margin: 0; padding: 32px 20px; color: #64748b; text-align: center; }
</style>
