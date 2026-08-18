<script setup>
defineProps({
    entries: { type: Array, default: () => [] },
    continuation: { type: Boolean, default: false },
    measuring: { type: Boolean, default: false },
});
</script>

<template>
    <section class="report-summary-page" :class="{ 'report-summary-page-measuring': measuring }">
        <h1 class="report-summary-title">{{ continuation ? 'Sumário — continuação' : 'Sumário' }}</h1>
        <ol class="report-summary-list">
            <li
                v-for="entry in entries"
                :key="entry.key"
                class="report-summary-entry"
                :class="`report-summary-entry-${entry.kind}`"
            >
                <span class="report-summary-label">{{ entry.title }}</span>
                <span class="report-summary-leader" aria-hidden="true"></span>
                <span class="report-summary-page-number">{{ entry.page }}</span>
            </li>
        </ol>
    </section>
</template>

<style scoped>
.report-summary-page { width: 100%; height: 252mm; overflow: hidden; color: #111827; font-family: Georgia, 'Times New Roman', serif; }
.report-summary-page-measuring { height: auto; overflow: visible; }
.report-summary-title { margin: 0 0 12mm; font-size: 16pt; font-weight: 700; line-height: 1.1; }
.report-summary-list { display: grid; gap: 2.3mm; margin: 0; padding: 0; list-style: none; }
.report-summary-entry { display: flex; align-items: flex-end; font-size: 9.5pt; font-weight: 700; line-height: 1.2; }
.report-summary-entry-subsection { padding-left: 8mm; font-weight: 400; }
.report-summary-entry-annex { margin-top: 1mm; }
.report-summary-label { min-width: 0; }
.report-summary-leader { min-width: 8mm; flex: 1; margin: 0 1.5mm 1.2pt; border-bottom: 1px dotted #111827; }
.report-summary-page-number { min-width: 7mm; flex: none; text-align: right; }
</style>
