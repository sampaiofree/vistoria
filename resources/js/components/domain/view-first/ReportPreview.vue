<script setup>
import { computed } from 'vue';
import CivilClassificationBadge from '@/components/domain/view-first/CivilClassificationBadge.vue';
import ReportA4Page from '@/components/domain/view-first/ReportA4Page.vue';

const props = defineProps({
    content: { type: Object, default: () => ({}) },
});

const cover = computed(() => props.content.cover ?? {});
const locations = computed(() => props.content.locations ?? []);
const findings = computed(() => props.content.findings ?? []);
const evidence = computed(() => props.content.sections?.find((section) => section.key === 'evidence')?.items ?? []);
const quantities = computed(() => props.content.quantities ?? {});

function chunks(items, size) {
    const result = [];

    for (let index = 0; index < items.length; index += size) {
        result.push(items.slice(index, index + size));
    }

    return result.length ? result : [[]];
}

const pages = computed(() => [
    { type: 'cover' },
    { type: 'summary' },
    { type: 'aspects' },
    ...chunks(locations.value, 2).map((items) => ({ type: 'locations', items })),
    ...chunks(findings.value, 2).map((items) => ({ type: 'findings', items })),
    ...chunks(evidence.value, 4).map((items) => ({ type: 'evidence', items })),
    { type: 'quantities' },
    { type: 'closing' },
]);

function visualClass(photo, index) {
    const variants = ['concrete', 'structure', 'surface', 'repair'];
    const variant = photo?.visual_variant ?? variants[index % variants.length];

    return `report-photo-${variant}`;
}
</script>

<template>
    <div class="report-preview-pages">
        <ReportA4Page
            v-for="(page, index) in pages"
            :key="`${page.type}-${index}`"
            :page="index + 1"
            :total="pages.length"
            :report="cover"
            :cover="page.type === 'cover'"
        >
            <template v-if="page.type === 'cover'">
                <div class="report-cover-rule"></div>
                <div class="report-cover-title">
                    <p>UBU – USINA III</p>
                    <p>{{ cover.equipment_name || 'EQUIPAMENTO' }}</p>
                    <p>{{ cover.equipment_tag || '—' }}</p>
                    <p>INSPEÇÃO DE INTEGRIDADE ESTRUTURAL</p>
                    <p>RELATÓRIO DE INSPEÇÃO</p>
                </div>
                <div class="report-cover-meta">
                    <div><span>CLIENTE</span><strong>{{ cover.client || '—' }}</strong></div>
                    <div><span>DOCUMENTO</span><strong>{{ cover.number || '—' }}</strong></div>
                    <div><span>REVISÃO</span><strong>{{ cover.revision || '—' }}</strong></div>
                    <div><span>DATA</span><strong>{{ cover.issued_at || cover.inspected_on || '—' }}</strong></div>
                    <div><span>O.S.</span><strong>{{ cover.service_order || '—' }}</strong></div>
                </div>
                <div class="report-cover-stamp">
                    <div class="report-stamp-send">SEND <small>INSPEÇÃO &amp; ENGENHARIA</small></div>
                    <div class="report-stamp-client">
                        <img v-if="cover.client_logo_url" :src="cover.client_logo_url" :alt="cover.client || 'Cliente'">
                        <strong v-else>{{ cover.client || 'CLIENTE' }}</strong>
                    </div>
                    <div class="report-stamp-number">
                        <span>Nº SAMARCO</span>
                        <strong>{{ cover.number || '—' }}</strong>
                    </div>
                </div>
            </template>

            <template v-else-if="page.type === 'summary'">
                <h2 class="report-page-title">1 · RESUMO DA CLASSIFICAÇÃO DO EQUIPAMENTO — GUT</h2>
                <div class="report-summary-table">
                    <div class="report-summary-cell report-summary-heading">Equipamento</div>
                    <div class="report-summary-cell">{{ cover.equipment_tag }} · {{ cover.equipment_name }}</div>
                    <div class="report-summary-cell report-summary-heading">Classe atual</div>
                    <div class="report-summary-cell"><CivilClassificationBadge :code="content.executive_summary?.criticality?.code" :label="content.executive_summary?.criticality?.label" /></div>
                </div>
                <div class="report-summary-callout">
                    <strong>{{ content.executive_summary?.headline }}</strong>
                    <p>{{ content.executive_summary?.description }}</p>
                </div>
                <div class="report-metric-grid">
                    <div><span>Total</span><strong>{{ content.executive_summary?.metrics?.total ?? '—' }}</strong></div>
                    <div><span>Concluídas</span><strong>{{ content.executive_summary?.metrics?.completed ?? '—' }}</strong></div>
                    <div><span>Fotos</span><strong>{{ content.executive_summary?.metrics?.photo_total ?? '—' }}</strong></div>
                    <div><span>Quantidade</span><strong>{{ content.executive_summary?.metrics?.quantity_total_label ?? '—' }}</strong></div>
                </div>
            </template>

            <template v-else-if="page.type === 'aspects'">
                <h2 class="report-page-title">2 · DESCRIÇÃO DOS ASPECTOS GERAIS DO EQUIPAMENTO</h2>
                <div class="report-aspect-grid">
                    <div v-for="item in (content.general_aspects || [])" :key="item.label" class="report-data-box">
                        <span>{{ item.label }}</span>
                        <strong>{{ item.value }}</strong>
                    </div>
                </div>
                <div class="report-prose">
                    <h3>2.1 Descrição das características do equipamento</h3>
                    <p>{{ content.executive_summary?.description || 'Informações técnicas consolidadas a partir da inspeção atual.' }}</p>
                    <h3>2.2 Conclusão e recomendações</h3>
                    <p>{{ content.executive_summary?.headline || 'A condição observada deve ser acompanhada conforme programação técnica.' }}</p>
                </div>
            </template>

            <template v-else-if="page.type === 'locations'">
                <h2 class="report-page-title report-blue-title">ANEXO A — LOCALIZAÇÃO E DOCUMENTAÇÃO FOTOGRÁFICA</h2>
                <div v-for="location in page.items" :key="location.id" class="report-location-card">
                    <div class="report-blue-bar"><strong>{{ location.marker }} · {{ location.title }}</strong><CivilClassificationBadge :code="location.classification?.code" /></div>
                    <div class="report-location-body">
                        <p>{{ location.location }}</p>
                        <dl><div><dt>Elemento</dt><dd>{{ location.element }}</dd></div><div><dt>Impacto</dt><dd>{{ location.impact?.label || '—' }}</dd></div><div><dt>GUT</dt><dd>{{ location.gut?.score ?? '—' }}</dd></div><div><dt>Fotos</dt><dd>{{ location.photo_count }} · {{ location.photo_interval }}</dd></div></dl>
                    </div>
                </div>
            </template>

            <template v-else-if="page.type === 'findings'">
                <h2 class="report-page-title">3 · AVARIAS E AVALIAÇÕES CIVIL</h2>
                <article v-for="finding in page.items" :key="finding.id" class="report-finding-card">
                    <div class="report-finding-heading"><div><span>{{ finding.code }}</span><h3>{{ finding.title }}</h3></div><CivilClassificationBadge :code="finding.classification?.code" :label="finding.classification?.label" /></div>
                    <p>{{ finding.location }}</p>
                    <p>{{ finding.project }} · {{ finding.item }} · {{ finding.element }}</p>
                    <p><strong>Recomendação:</strong> {{ finding.recommendation || 'Acompanhar conforme programação técnica.' }}</p>
                </article>
            </template>

            <template v-else-if="page.type === 'evidence'">
                <h2 class="report-page-title report-blue-title">ANEXO B — REGISTRO FOTOGRÁFICO</h2>
                <div class="report-photo-grid">
                    <article v-for="(photo, photoIndex) in page.items" :key="photo.id" class="report-photo-card">
                        <div class="report-photo-image" :class="visualClass(photo, photoIndex)"><span>{{ String(photo.sequence ?? photoIndex + 1).padStart(2, '0') }}</span></div>
                        <div><strong>{{ photo.title }}</strong><p>{{ photo.caption }}</p></div>
                    </article>
                </div>
            </template>

            <template v-else-if="page.type === 'quantities'">
                <h2 class="report-page-title">4 · QUANTITATIVO CONSOLIDADO</h2>
                <div class="report-metric-grid">
                    <div><span>Total</span><strong>{{ quantities.total_label || '—' }}</strong></div>
                    <div><span>Exportável</span><strong>{{ quantities.exportable_total_label || '—' }}</strong></div>
                    <div><span>Unidade</span><strong>{{ quantities.unit || '—' }}</strong></div>
                </div>
                <table class="report-table"><thead><tr><th>Classe</th><th>Total</th><th>Qtd.</th><th>Unidade</th></tr></thead><tbody><tr v-for="item in (quantities.by_class || [])" :key="item.code"><td><CivilClassificationBadge :code="item.code" :label="item.label" /></td><td>{{ item.total_label }}</td><td>{{ item.count }}</td><td>{{ item.unit }}</td></tr><tr v-if="!(quantities.by_class || []).length"><td colspan="4">Sem consolidado disponível.</td></tr></tbody></table>
            </template>

            <template v-else>
                <div class="report-closing-grid">
                    <div><h2 class="report-page-title">5 · RESPONSABILIDADE TÉCNICA</h2><ul><li v-for="item in (content.sections?.find((section) => section.key === 'responsibles')?.items || [])" :key="item.id"><strong>{{ item.user?.name }}</strong><span>{{ item.responsibility_label }}</span></li></ul></div>
                    <div><h2 class="report-page-title">6 · DOCUMENTOS DE REFERÊNCIA</h2><ul><li v-for="item in (content.sections?.find((section) => section.key === 'documents')?.items || [])" :key="item.id"><strong>{{ item.document?.title }}</strong><span>Revisão {{ item.document?.revision || '—' }}</span></li></ul></div>
                </div>
            </template>
        </ReportA4Page>
    </div>
</template>

<style scoped>
.report-preview-pages { display: flex; flex-direction: column; gap: 0; overflow-x: auto; padding: 0 4mm 12mm; }
.report-page-title { margin: 0 0 6mm; padding-bottom: 2mm; border-bottom: 1px solid #111827; font-size: 11pt; font-weight: 800; letter-spacing: .02em; }
.report-blue-title { color: #fff; padding: 2mm 3mm; border: 0; background: #062b68; font-size: 9pt; text-align: center; }
.report-cover-rule { height: 1px; margin: 4mm 0 30mm; background: #111827; }
.report-cover-title { display: grid; gap: 9mm; padding: 0 36mm; text-align: left; font-family: Georgia, serif; font-size: 15pt; font-weight: 700; line-height: 1.15; }
.report-cover-title p { margin: 0; }
.report-cover-meta { display: grid; grid-template-columns: repeat(5, 1fr); margin-top: auto; border: 1px solid #111827; }
.report-cover-meta div { min-height: 15mm; padding: 2mm; border-right: 1px solid #111827; }
.report-cover-meta div:last-child { border-right: 0; }
.report-cover-meta span, .report-data-box span, .report-metric-grid span { display: block; color: #4b5563; font-size: 6.5pt; font-weight: 700; text-transform: uppercase; }
.report-cover-meta strong { display: block; margin-top: 1mm; font-size: 8pt; }
.report-cover-stamp { display: grid; grid-template-columns: 1fr 1.3fr 1fr; align-items: center; margin-top: 8mm; border-top: 1px solid #111827; border-bottom: 2px solid #111827; }
.report-cover-stamp > div { min-height: 18mm; padding: 3mm; border-right: 1px solid #111827; }
.report-cover-stamp > div:last-child { border-right: 0; }
.report-stamp-send { color: #07519a; font-size: 16pt; font-weight: 900; }
.report-stamp-send small { display: block; color: #111827; font-size: 5pt; letter-spacing: .05em; }
.report-stamp-client { display: flex; align-items: center; color: #07519a; font-size: 12pt; text-transform: uppercase; }
.report-stamp-client img { max-width: 35mm; max-height: 10mm; object-fit: contain; }
.report-stamp-number span { display: block; font-size: 6pt; }
.report-stamp-number strong { display: block; margin-top: 2mm; font-size: 8pt; }
.report-summary-table { display: grid; grid-template-columns: 1fr 2fr; border: 1px solid #111827; font-size: 8pt; }
.report-summary-cell { padding: 3mm; border-bottom: 1px solid #111827; }.report-summary-cell:nth-last-child(-n+2) { border-bottom: 0; }.report-summary-cell:nth-child(odd) { border-right: 1px solid #111827; font-weight: 800; text-transform: uppercase; }
.report-summary-heading { background: #f3f4f6; }
.report-summary-callout { margin-top: 6mm; padding: 5mm; border: 1px solid #9ca3af; font-size: 9pt; }.report-summary-callout p { margin: 2mm 0 0; color: #4b5563; line-height: 1.5; }
.report-metric-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 3mm; margin-top: 6mm; }.report-metric-grid > div, .report-data-box { padding: 4mm; border: 1px solid #d1d5db; }.report-metric-grid strong, .report-data-box strong { display: block; margin-top: 2mm; font-size: 10pt; }
.report-aspect-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 3mm; }.report-prose { margin-top: 8mm; font-family: Georgia, serif; font-size: 9pt; line-height: 1.55; }.report-prose h3 { margin: 5mm 0 2mm; font-size: 10pt; }.report-prose p { margin: 0; }
.report-location-card, .report-finding-card { margin-bottom: 6mm; border: 1px solid #9ca3af; break-inside: avoid; }.report-blue-bar { display: flex; justify-content: space-between; align-items: center; padding: 2mm 3mm; background: #062b68; color: #fff; font-size: 8pt; }.report-location-body { padding: 4mm; font-size: 8pt; }.report-location-body p, .report-finding-card p { margin: 0 0 2mm; }.report-location-body dl { display: grid; grid-template-columns: repeat(2, 1fr); gap: 3mm; margin: 4mm 0 0; }.report-location-body dt { color: #4b5563; font-size: 6.5pt; font-weight: 700; text-transform: uppercase; }.report-location-body dd { margin: 1mm 0 0; font-weight: 700; }
.report-finding-card { padding: 4mm; font-size: 8pt; line-height: 1.45; }.report-finding-heading { display: flex; justify-content: space-between; gap: 4mm; margin-bottom: 3mm; }.report-finding-heading span { color: #07519a; font-size: 7pt; font-weight: 800; }.report-finding-heading h3 { margin: 1mm 0 0; font-size: 10pt; }
.report-photo-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 5mm; }.report-photo-card { overflow: hidden; border: 1px solid #9ca3af; break-inside: avoid; }.report-photo-image { position: relative; height: 48mm; background: #94a3b8; background-image: linear-gradient(145deg, #cbd5e1, #475569); }.report-photo-image span { position: absolute; right: 3mm; bottom: 3mm; padding: 2mm; background: #062b68; color: #fff; font-size: 8pt; font-weight: 800; }.report-photo-structure { background-image: linear-gradient(115deg, transparent 35%, rgba(15,23,42,.45) 36% 41%, transparent 42%), linear-gradient(30deg, #94a3b8, #475569); }.report-photo-surface { background-image: repeating-linear-gradient(105deg, rgba(255,255,255,.12) 0 2px, transparent 2px 18px), linear-gradient(145deg, #64748b, #334155); }.report-photo-repair { background-image: linear-gradient(90deg, transparent 47%, rgba(13,148,136,.78) 48% 52%, transparent 53%), linear-gradient(145deg, #cbd5e1, #64748b); }.report-photo-card > div:last-child { padding: 3mm; font-size: 7.5pt; }.report-photo-card p { margin: 1mm 0 0; color: #4b5563; }
.report-table { width: 100%; border-collapse: collapse; margin-top: 6mm; font-size: 8pt; }.report-table th, .report-table td { padding: 3mm; border: 1px solid #9ca3af; text-align: left; }.report-table th { background: #f3f4f6; font-size: 7pt; text-transform: uppercase; }
.report-closing-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8mm; }.report-closing-grid ul { margin: 0; padding: 0; list-style: none; }.report-closing-grid li { display: grid; gap: 1mm; padding: 3mm 0; border-bottom: 1px solid #d1d5db; font-size: 8pt; }.report-closing-grid li span { color: #4b5563; }
@media print { .report-preview-pages { display: block; } }
</style>
