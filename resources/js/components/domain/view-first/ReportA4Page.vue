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
});
</script>

<template>
    <section class="report-a4-page" :class="{ 'report-a4-cover': cover }">
        <div v-if="!cover" class="report-a4-header">
            <div class="report-brand report-brand-client">
                <img v-if="report.client_logo_url" :src="report.client_logo_url" :alt="report.client || 'Cliente'">
                <span v-else>{{ report.client || 'CLIENTE' }}</span>
            </div>
            <div class="report-brand report-brand-provider">SEND <small>INSPEÇÃO &amp; ENGENHARIA</small></div>
            <div class="report-header-meta">
                <strong>{{ report.number }}</strong>
                <span>{{ report.revision }} · PÁGINA {{ page }}</span>
            </div>
        </div>

        <div class="report-a4-content">
            <slot />
        </div>

        <footer class="report-a4-footer">
            <span>{{ report.provider || 'Vistoria Serviços de Inspeção Ltda.' }}</span>
            <span>{{ report.number }} · {{ report.revision }}</span>
            <span>PÁGINA {{ page }} / {{ total }}</span>
        </footer>
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
}

.report-a4-cover {
    padding-top: 12mm;
}

.report-a4-header {
    display: grid;
    grid-template-columns: 1fr 1fr 1.2fr;
    align-items: stretch;
    min-height: 13mm;
    border-top: 1.2px solid #111827;
    border-bottom: 1px solid #111827;
    font-size: 7pt;
}

.report-brand,
.report-header-meta {
    display: flex;
    align-items: center;
    min-width: 0;
    padding: 2mm 3mm;
    border-right: 1px solid #111827;
}

.report-brand:last-child,
.report-header-meta:last-child {
    border-right: 0;
}

.report-brand img {
    max-width: 32mm;
    max-height: 8mm;
    object-fit: contain;
}

.report-brand-client {
    color: #075985;
    font-size: 8pt;
    font-weight: 800;
    text-transform: uppercase;
}

.report-brand-provider {
    color: #07519a;
    font-size: 11pt;
    font-weight: 900;
    letter-spacing: -.06em;
}

.report-brand-provider small {
    margin-left: 2mm;
    color: #111827;
    font-size: 4.5pt;
    letter-spacing: .03em;
}

.report-header-meta {
    flex-direction: column;
    align-items: flex-start;
    justify-content: center;
    gap: 1mm;
    text-transform: uppercase;
}

.report-header-meta span {
    color: #4b5563;
    font-size: 6pt;
}

.report-a4-content {
    flex: 1;
    min-height: 0;
    padding-top: 6mm;
}

.report-a4-footer {
    display: flex;
    justify-content: space-between;
    gap: 4mm;
    margin-top: 5mm;
    padding-top: 3mm;
    border-top: 1px solid #111827;
    color: #374151;
    font-size: 6.5pt;
    text-transform: uppercase;
}

@media screen and (max-width: 880px) {
    .report-a4-page {
        transform-origin: top left;
        margin-left: 0;
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

    .report-a4-page:last-child {
        break-after: auto;
        page-break-after: auto;
    }
}
</style>
