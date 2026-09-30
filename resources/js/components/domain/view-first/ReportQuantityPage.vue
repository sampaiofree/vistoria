<script setup>
defineProps({
    page: { type: Object, required: true },
    measuring: { type: Boolean, default: false },
});

function colorStyle(color) {
    if (!/^#[0-9A-F]{6}$/i.test(String(color || ''))) return {};

    const red = Number.parseInt(color.slice(1, 3), 16);
    const green = Number.parseInt(color.slice(3, 5), 16);
    const blue = Number.parseInt(color.slice(5, 7), 16);
    const brightness = ((red * 299) + (green * 587) + (blue * 114)) / 1000;

    return { backgroundColor: color, color: brightness >= 150 ? '#111827' : '#ffffff' };
}
</script>

<template>
    <div class="report-quantity-page" :class="{ 'report-quantity-measuring': measuring }">
        <h2 class="report-quantity-title">
            {{ page.annexTitle || (page.type === 'civil-quantity' ? 'QUANTITATIVO GERAL – CIVIL' : 'QUANTITATIVO GERAL – REC') }}<span v-if="page.continuation"> — CONTINUAÇÃO</span>
        </h2>
        <table class="report-quantity-table">
            <thead>
                <tr>
                    <th>CÓD.</th><th>DATA DE CADASTRO</th><th>PROJETO</th><th>FOTO</th>
                    <th>ITEM / SUBITEM</th><th>ELEMENTO</th><th>QTD.</th><th>{{ page.type === 'civil-quantity' ? 'M³ TOTAL' : 'PESO TOTAL' }}</th>
                    <th>G</th><th>U</th><th>T</th><th>PONT. TOTAL</th><th>CLASS.</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="item in page.items" :key="item.key">
                    <td>{{ item.code }}</td><td>{{ item.registered_on }}</td><td>{{ item.project }}</td><td>{{ item.photos }}</td>
                    <td>{{ item.item }}</td><td>{{ item.element }}</td><td>{{ item.quantity }}</td><td>{{ page.type === 'civil-quantity' ? item.total_volume_label : item.total_weight_label }}</td>
                    <td class="report-quantity-gravity"><div><span>{{ item.gravity.label }}</span><strong :style="colorStyle(item.gravity.color)">{{ item.gravity.score ?? '—' }}</strong></div></td>
                    <td class="report-quantity-urgency" :style="colorStyle(item.urgency.color)"><strong>{{ item.urgency.score ?? '—' }}</strong></td>
                    <td class="report-quantity-trend"><div><span>{{ item.trend.label }}</span><strong :style="colorStyle(item.trend.color)">{{ item.trend.score ?? '—' }}</strong></div></td>
                    <td class="report-quantity-score">{{ item.gut_score }}</td>
                    <td class="report-quantity-class" :style="colorStyle(item.classification.color)">{{ item.classification.code }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</template>

<style scoped>
.report-quantity-page { width: 100%; height: 155mm; overflow: hidden; font-family: Arial, sans-serif; }
.report-quantity-measuring { height: auto; overflow: visible; }
.report-quantity-title { margin: 0 0 4mm; font-family: Georgia, 'Times New Roman', serif; font-size: 12pt; font-weight: 800; }
.report-quantity-table { width: 100%; border-collapse: collapse; table-layout: auto; font-size: 5.5pt; line-height: 1.2; }
.report-quantity-table th, .report-quantity-table td { border: 1px solid #062b68; padding: 1mm .8mm; text-align: center; vertical-align: middle; overflow-wrap: anywhere; }
.report-quantity-table th { background: #062b68; color: #fff; font-size: 5.5pt; font-weight: 800; white-space: normal; overflow-wrap: normal; }
/* Auto layout lets compact columns grow to fit their headers and values. */
.report-quantity-table :is(th, td):is(:nth-child(2), :nth-child(4), :nth-child(7), :nth-child(8), :nth-child(10), :nth-child(12), :nth-child(13)) { width: 1%; }
.report-quantity-table td:is(:nth-child(2), :nth-child(7), :nth-child(8), :nth-child(10), :nth-child(12), :nth-child(13)) { white-space: nowrap; }
.report-quantity-gravity, .report-quantity-urgency, .report-quantity-trend { padding: 0 !important; }
.report-quantity-gravity > div, .report-quantity-trend > div { display: grid; min-height: 7mm; grid-template-columns: minmax(0, 1fr) 6mm; align-items: stretch; }
.report-quantity-gravity span, .report-quantity-trend span { display: flex; min-width: 0; align-items: center; justify-content: center; padding: 1mm .6mm; overflow-wrap: anywhere; font-size: 5pt; white-space: normal; }
.report-quantity-gravity strong, .report-quantity-trend strong, .report-quantity-urgency > strong { display: flex; min-height: 5mm; align-items: center; justify-content: center; padding: 1mm; color: #111827; font-size: 7pt; }
.report-quantity-urgency > strong { color: inherit; }
.report-quantity-score { background: #dbeafe; color: #0759a0; font-size: 7pt; font-weight: 800; }
.report-quantity-class { font-size: 7pt; font-weight: 800; }
</style>
