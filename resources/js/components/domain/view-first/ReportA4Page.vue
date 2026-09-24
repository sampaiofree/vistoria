<script setup>
defineProps({
    page: {
        type: Number,
        required: true,
    },
    total: {
        type: Number,
        required: true,
    },
    report: {
        type: Object,
        required: true,
    },
    cover: {
        type: Boolean,
        default: false,
    },
    orientation: {
        type: String,
        default: 'portrait',
    },
});
</script>

<template>
    <section
        class="report-a4-page"
        :class="{ 'report-a4-cover': cover, 'report-a4-landscape': orientation === 'landscape' }"
        :data-report-orientation="orientation"
    >
        <div v-if="!cover" class="report-a4-header">
            <div class="report-header-cell report-header-logo">
                <img v-if="report.client_logo_url" :src="report.client_logo_url" :alt="report.client || 'Cliente'">
            </div>
            <div class="report-header-cell report-header-logo">
                <img v-if="report.provider_logo_url" :src="report.provider_logo_url" :alt="report.provider || 'Empresa responsável'">
            </div>
            <div class="report-header-cell report-header-designer">
                {{ report.report_designer || 'PROJETISTA II' }}
            </div>
            <div class="report-header-cell report-header-field report-header-samarco">
                <span class="report-header-label">nº SAMARCO</span>
                <strong>{{ report.external_report_number || '—' }}</strong>
            </div>
            <div class="report-header-cell report-header-field report-header-compact">
                <span class="report-header-label">rev.</span>
                <strong>{{ report.current_revision ?? '—' }}</strong>
            </div>
            <div class="report-header-cell report-header-field report-header-compact">
                <span class="report-header-label">página nº</span>
                <strong>{{ page }}</strong>
            </div>
        </div>

        <div class="report-a4-content" :class="{ 'report-a4-content-cover': cover }">
            <slot />
        </div>

    </section>
</template>

<style scoped>
.report-a4-page {
    position: relative;
    display: flex;
    flex-direction: column;
    width: 210mm;
    min-height: 297mm;
    margin: 0 auto 12mm;
    padding: 13mm 14mm 12mm;
    overflow: hidden;
    background: #fff;
    color: #111827;
    box-shadow: 0 18px 40px rgba(15, 23, 42, .12);
    print-color-adjust: exact;
    -webkit-print-color-adjust: exact;
}

.report-a4-cover {
    padding-top: 12mm;
}

.report-a4-landscape {
    width: 297mm;
    min-height: 210mm;
    padding: 10mm 14mm 9mm;
}

.report-a4-landscape .report-a4-content {
    padding-top: 4mm;
}

.report-a4-header {
    display: grid;
    grid-template-columns: minmax(0, 1.3fr) minmax(0, 1fr) 32mm 42mm 15mm 22mm;
    align-items: stretch;
    min-height: 16mm;
    border-top: 1.2px solid #111827;
    border-bottom: 1.5px solid #111827;
    font-size: 7pt;
}

.report-header-cell {
    display: flex;
    align-items: center;
    justify-content: center;
    min-width: 0;
    padding: 1.5mm 2mm;
    border-right: 1px solid #111827;
}

.report-header-cell:last-child {
    border-right: 0;
}

.report-header-logo img {
    display: block;
    width: 100%;
    height: 11mm;
    object-fit: contain;
}

.report-header-designer {
    color: #111827;
    font-size: 10pt;
    font-weight: 700;
    line-height: 1.15;
    text-align: center;
}

.report-header-field {
    flex-direction: column;
    align-items: flex-start;
    justify-content: center;
    color: #111827;
    line-height: 1.1;
}

.report-header-label {
    font-size: 7.5pt;
    font-weight: 700;
}

.report-header-field strong {
    margin-top: 1.5mm;
    overflow: hidden;
    max-width: 100%;
    font-size: 8pt;
    font-weight: 500;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.report-header-compact {
    align-items: center;
    padding-right: 1mm;
    padding-left: 1mm;
    text-align: center;
}

.report-header-compact .report-header-label {
    font-size: 8pt;
}

.report-header-compact strong {
    font-size: 9pt;
}

.report-a4-content {
    flex: 1;
    min-height: 0;
    padding-top: 6mm;
}

.report-a4-content-cover {
    display: flex;
    flex-direction: column;
    padding-top: 0;
}

@media screen and (max-width: 880px) {
    .report-a4-page {
        transform-origin: top left;
        margin-left: 0;
    }

    .report-a4-page.report-a4-landscape {
        width: 297mm;
        height: 210mm;
        min-height: 210mm;
        padding: 10mm 14mm 9mm;
    }
}

@media print {
    .report-a4-page {
        width: 210mm;
        height: 297mm;
        min-height: 297mm;
        margin: 0;
        padding: 13mm 14mm 12mm;
        overflow: hidden;
        box-shadow: none;
        break-after: page;
        page-break-after: always;
    }

    .report-a4-page.report-a4-landscape {
        width: 297mm;
        height: 210mm;
        min-height: 210mm;
        padding: 10mm 14mm 9mm;
    }

    .report-a4-page:last-child {
        break-after: auto;
        page-break-after: auto;
    }
}
</style>
