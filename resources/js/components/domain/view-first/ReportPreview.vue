<script setup>
import { computed, ref, watch } from 'vue';
import ReportA4Page from '@/components/domain/view-first/ReportA4Page.vue';
import InspectionLocationReportMap from '@/components/domain/inspection-locations/InspectionLocationReportMap.vue';
import ReportMapObservationText from '@/components/domain/view-first/ReportMapObservationText.vue';
import ReportGeneralAspectsPaginator from '@/components/domain/view-first/ReportGeneralAspectsPaginator.vue';
import ReportSummaryPage from '@/components/domain/view-first/ReportSummaryPage.vue';
import ReportSummaryPaginator from '@/components/domain/view-first/ReportSummaryPaginator.vue';
import GeneralAspectsDocument from '@/components/domain/inspections/GeneralAspectsDocument.vue';
import {
    buildReportSummaryEntries,
    numberGeneralAspectsDocument,
    reportSummarySignature,
} from '@/lib/reportSummary.js';

const props = defineProps({
    content: { type: Object, default: () => ({}) },
});

const cover = computed(() => props.content.cover ?? {});
const locations = computed(() => props.content.locations ?? []);
const photographicBlocks = computed(() => props.content.photographic_documentation?.blocks ?? []);
const evolutionRows = computed(() => props.content.evolution_rows ?? []);
const reportFindings = computed(() => props.content.findings ?? []);
const textualFindings = computed(() => reportFindings.value.filter((finding) =>
    ['not_located', 'not_inspected'].includes(finding.condition),
));
const generalAspects = computed(() => props.content.general_aspects?.document ?? null);
const reportOverview = computed(() => props.content.overview ?? { blocks: [] });
const locationSequence = computed(() => props.content.location_sequence ?? []);
const numberedGeneralAspects = computed(() => numberGeneralAspectsDocument(generalAspects.value));
const generalAspectsPages = ref([]);
const generalAspectsReady = ref(!generalAspects.value);
const summaryPages = ref([[]]);
const summaryPageCount = ref(1);
const summaryReady = ref(false);
const observationLayouts = ref({});
const previewRoot = ref(null);

const emit = defineEmits(['layout-ready']);

defineExpose({
    getPageElements: () => previewRoot.value
        ? [...previewRoot.value.querySelectorAll('.report-a4-page')]
        : [],
});

watch(generalAspects, (document) => {
    if (document) return;
    generalAspectsPages.value = [];
    generalAspectsReady.value = true;
});

function updateGeneralAspectsPages(value) {
    generalAspectsPages.value = value;
}

function mapObservation(map) {
    return String(map?.observations ?? map?.description ?? '');
}

function defaultObservationLayout(map) {
    const source = mapObservation(map);

    return {
        source,
        chunks: [{ text: source, fontSize: 9, fitted: false }],
    };
}

watch(locations, (sheets) => {
    const layouts = {};

    sheets.forEach((sheet) => {
        (sheet.maps || []).forEach((map) => {
            const existing = observationLayouts.value[map.public_id];
            const source = mapObservation(map);

            layouts[map.public_id] = existing?.source === source
                ? existing
                : defaultObservationLayout(map);
        });
    });

    observationLayouts.value = layouts;
}, { immediate: true });

const layoutReady = computed(() => generalAspectsReady.value && summaryReady.value && locations.value.every((sheet) =>
    (sheet.maps || []).every((map) => observationChunks(map).every((chunk) => chunk.fitted)),
));

watch(layoutReady, (ready) => emit('layout-ready', ready), { immediate: true });

function observationChunks(map) {
    return observationLayouts.value[map.public_id]?.chunks
        ?? defaultObservationLayout(map).chunks;
}

function updateObservationLayout({ mapId, chunkIndex, text, overflow, fontSize }) {
    const layout = observationLayouts.value[mapId];

    if (!layout || !layout.chunks[chunkIndex]) return;

    const chunks = [...layout.chunks];
    const replacement = [{ text, fontSize, fitted: true }];

    if (typeof overflow === 'string' && overflow !== '') {
        replacement.push({ text: overflow, fontSize: 9, fitted: false });
    }

    chunks.splice(chunkIndex, 1, ...replacement);
    observationLayouts.value = {
        ...observationLayouts.value,
        [mapId]: { ...layout, chunks },
    };
}

function chunks(items, size) {
    const result = [];

    for (let index = 0; index < items.length; index += size) {
        result.push(items.slice(index, index + size));
    }

    return result;
}

function categoryKey(value) {
    return String(value || '').trim().toLocaleUpperCase('pt-BR');
}

function mapPages(sheet) {
    return (sheet.maps || []).flatMap((map) => {
        const chunksForMap = observationChunks(map);
        const category = sheet.category ?? {};
        const annexTitle = sheet.annex_title ?? null;

        return chunksForMap.map((observation, observationIndex) => ({
            type: observationIndex === 0 ? 'location-map' : 'map-observations',
            key: observationIndex === 0
                ? `location-map-${map.public_id}`
                : `map-observations-${map.public_id}-${observationIndex}`,
            category,
            map,
            observation,
            observationIndex,
            annexTitle: observationIndex === 0 ? annexTitle : null,
        }));
    });
}

const photographicBlocksByCategory = computed(() => {
    const groups = new Map();

    photographicBlocks.value.forEach((block) => {
        const category = categoryKey(block.category || block.category_label);

        if (category && !groups.has(category)) {
            groups.set(category, []);
        }

        groups.get(category)?.push(block);
    });

    return groups;
});

function photographicPages(blocks, key, category) {
    return chunks(blocks || [], 2).map((items, index) => ({
        type: 'photographic',
        key: `photographic-${key}-${index}`,
        category,
        items,
    }));
}

function sequencedLocationPages() {
    return locationSequence.value.flatMap((entry, index) => {
        const category = entry.category ?? {};
        const key = entry.id || entry.map?.public_id || index;

        return [
            ...mapPages({ category, maps: entry.map ? [entry.map] : [], annex_title: entry.annex_title }),
            ...photographicPages(
                entry.photographic_blocks,
                key,
                category.code || category.name || 'SEM CATEGORIA',
            ),
        ];
    });
}

function legacyLocationPages() {
    const categories = new Map();

    locations.value.forEach((sheet) => {
        const key = categoryKey(sheet.category?.code || sheet.category?.name);
        if (!key) return;

        if (!categories.has(key)) {
            categories.set(key, {
                label: sheet.category?.code || sheet.category?.name || key,
                sheets: [],
            });
        }

        categories.get(key).sheets.push(sheet);
    });

    return Array.from(categories.entries()).flatMap(([key, category]) => [
        ...category.sheets.flatMap(mapPages),
        ...photographicPages(photographicBlocksByCategory.value.get(key) || [], key, category.label),
    ]);
}

const reportContentPages = computed(() => {
    return [
        ...generalAspectsPages.value.map((document, index) => ({
            type: 'general-aspects',
            key: `general-aspects-${index}`,
            document,
            continuation: index > 0,
        })),
        ...chunks(evolutionRows.value, 14).map((items, index) => ({
            type: 'defect-evolution',
            key: `defect-evolution-${index}`,
            items,
            continuation: index > 0,
        })),
        { type: 'overview', key: 'report-overview' },
        ...(locationSequence.value.length ? sequencedLocationPages() : legacyLocationPages()),
        ...chunks(textualFindings.value, 2).map((items, index) => ({
            type: 'textual-findings',
            key: `textual-findings-${index}`,
            items,
            continuation: index > 0,
        })),
    ];
});

const summaryEntries = computed(() => buildReportSummaryEntries(
    reportContentPages.value,
    summaryPageCount.value,
    reportOverview.value.title,
));

const summarySignature = computed(() => reportSummarySignature(summaryEntries.value));

watch(summarySignature, () => {
    summaryReady.value = false;
}, { flush: 'sync' });

function updateSummaryPages({ pages: measuredPages, signature }) {
    if (signature !== summarySignature.value) return;

    const normalizedPages = measuredPages.length ? measuredPages : [[]];
    summaryPages.value = normalizedPages;

    if (normalizedPages.length !== summaryPageCount.value) {
        summaryPageCount.value = normalizedPages.length;
        summaryReady.value = false;
    }
}

function updateSummaryReady({ ready, signature }) {
    summaryReady.value = Boolean(
        ready
        && signature === summarySignature.value
        && summaryPages.value.length === summaryPageCount.value,
    );
}

const pages = computed(() => [
    { type: 'cover' },
    ...summaryPages.value.map((entries, index) => ({
        type: 'summary',
        key: `report-summary-${index}`,
        entries,
        continuation: index > 0,
    })),
    ...reportContentPages.value,
]);

function validHexColor(color) {
    return /^#[0-9A-F]{6}$/i.test(String(color || ''));
}

function contrastingTextColor(color) {
    if (!validHexColor(color)) return '#111827';

    const red = Number.parseInt(color.slice(1, 3), 16);
    const green = Number.parseInt(color.slice(3, 5), 16);
    const blue = Number.parseInt(color.slice(5, 7), 16);
    const brightness = ((red * 299) + (green * 587) + (blue * 114)) / 1000;

    return brightness >= 150 ? '#111827' : '#ffffff';
}

function damageColorStyle(color) {
    if (!validHexColor(color)) return {};

    return {
        backgroundColor: color,
        color: contrastingTextColor(color),
    };
}

function legendColorStyle(color) {
    return validHexColor(color) ? { backgroundColor: color } : {};
}

function mapQuantityUnits(map) {
    return [...new Set((map?.damage_rows || [])
        .map((row) => row.quantity?.unit)
        .filter(Boolean))];
}

function mapQuantityHeader(map) {
    const units = mapQuantityUnits(map);

    return units.length === 1 ? units[0] : 'QUANT.';
}

function damageQuantity(row, map) {
    if (row.quantity?.value === null || row.quantity?.value === undefined) return '—';

    return mapQuantityUnits(map).length > 1
        ? `${row.quantity.value} ${row.quantity.unit || ''}`.trim()
        : row.quantity.value;
}

function visualClass(photo) {
    const variant = photo?.visual_variant ?? 'photo';

    return `report-photo-${variant}`;
}
</script>

<template>
    <div ref="previewRoot" class="report-preview-pages">
        <ReportGeneralAspectsPaginator
            v-if="generalAspects"
            :document="numberedGeneralAspects"
            @pages="updateGeneralAspectsPages"
            @ready="generalAspectsReady = $event"
        />
        <ReportSummaryPaginator
            :entries="summaryEntries"
            :signature="summarySignature"
            @pages="updateSummaryPages"
            @ready="updateSummaryReady"
        />
        <ReportA4Page
            v-for="(page, index) in pages"
            :key="page.key || `${page.type}-${index}`"
            :page="index + 1"
            :total="pages.length"
            :report="cover"
            :cover="page.type === 'cover'"
        >
            <template v-if="page.type === 'cover'">
                <div class="report-cover-layout">
                    <div class="report-cover-rule"></div>
                    <div class="report-cover-title-stage">
                        <div
                            v-if="(cover.title_lines || []).length"
                            class="report-cover-title"
                            :class="`report-cover-title-${cover.revision_density || 'normal'}`"
                        >
                            <p v-for="(line, lineIndex) in cover.title_lines" :key="`title-${lineIndex}`">{{ line }}</p>
                        </div>
                    </div>
                    <div class="report-cover-bottom">
                        <div
                            class="report-cover-history"
                            :class="`report-cover-history-${cover.revision_density || 'normal'}`"
                        >
                            <div class="report-cover-history-label">R<br>E<br>V<br>I<br>S<br>Õ<br>E<br>S</div>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Nº</th>
                                        <th>Descrição</th>
                                        <th>T.E.</th>
                                        <th>Data</th>
                                        <th>Prep.</th>
                                        <th>Verif.</th>
                                        <th>Aprov.</th>
                                        <th>Liber.</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="row in (cover.revision_history || [])" :key="row.key">
                                        <td>{{ row.revision_number ?? '—' }}</td>
                                        <td>{{ row.description || '—' }}</td>
                                        <td>{{ row.emission_type || '—' }}</td>
                                        <td>{{ row.date || '—' }}</td>
                                        <td>{{ row.compact_responsibles?.preparer || '—' }}</td>
                                        <td>{{ row.compact_responsibles?.reviewer || '—' }}</td>
                                        <td>{{ row.compact_responsibles?.approver || '—' }}</td>
                                        <td>{{ row.compact_responsibles?.releaser || '—' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="report-cover-emission-legend">
                            <strong>T.E. — TIPOS DE EMISSÃO</strong>
                            <div>
                                <span v-for="emission in (cover.emission_types || [])" :key="emission.value">
                                    {{ emission.value }} — {{ emission.label }}
                                </span>
                            </div>
                        </div>
                        <div class="report-cover-approval">
                            <div v-for="item in (cover.approval_flow || [])" :key="item.key">
                                <span>{{ item.label }}</span>
                                <strong>{{ item.name || '—' }}</strong>
                            </div>
                            <div>
                                <span>Data</span>
                                <strong>{{ cover.approval_date || '—' }}</strong>
                            </div>
                            <div>
                                <span>O.S.</span>
                                <strong>{{ cover.service_order || '—' }}</strong>
                            </div>
                        </div>
                        <div class="report-cover-institutional">
                            <div class="report-cover-provider-logo report-cover-institutional-logo">
                                <img v-if="cover.provider_logo_url" :src="cover.provider_logo_url" :alt="cover.provider || 'Empresa responsável'">
                                <strong v-else>{{ cover.provider || '—' }}</strong>
                            </div>
                            <div class="report-cover-designer-i report-cover-institutional-field">
                                <span>Nº PROJETISTA I:</span>
                                <strong>{{ cover.designer_i_report_number || '—' }}</strong>
                            </div>
                            <div class="report-cover-revision report-cover-institutional-field report-cover-institutional-compact">
                                <span>Rev.:</span>
                                <strong>{{ cover.current_revision ?? '—' }}</strong>
                            </div>
                            <div class="report-cover-page report-cover-institutional-field report-cover-institutional-compact">
                                <span>PÁGINA:</span>
                                <strong>1</strong>
                            </div>
                            <div class="report-cover-provider-role">
                                {{ cover.report_designer || 'PROJETISTA II' }}
                            </div>
                            <div class="report-cover-designer-ii report-cover-institutional-field">
                                <span>Nº PROJETISTA II:</span>
                            </div>
                            <div class="report-cover-client-logo report-cover-institutional-logo">
                                <img v-if="cover.client_logo_url" :src="cover.client_logo_url" :alt="cover.client || 'Cliente'">
                            </div>
                            <div class="report-cover-client-name">
                                {{ cover.client || '—' }}
                            </div>
                            <div class="report-cover-samarco report-cover-institutional-field">
                                <span>Nº SAMARCO:</span>
                                <strong>{{ cover.external_report_number || '—' }}</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </template>

            <template v-else-if="page.type === 'summary'">
                <ReportSummaryPage :entries="page.entries" :continuation="page.continuation" />
            </template>

            <template v-else-if="page.type === 'general-aspects'">
                <div class="report-general-aspects-page">
                    <h2 class="report-general-aspects-title">
                        2. DESCRIÇÃO DOS ASPECTOS GERAIS DO EQUIPAMENTO<span v-if="page.continuation"> — CONTINUAÇÃO</span>
                    </h2>
                    <div class="report-general-aspects-body">
                        <GeneralAspectsDocument :document="page.document" />
                    </div>
                </div>
            </template>

            <template v-else-if="page.type === 'overview'">
                <div class="report-overview-page">
                    <h2 class="report-general-aspects-title report-overview-heading">
                        {{ reportOverview.title || 'ANEXO A – LOCALIZAÇÃO E DOCUMENTAÇÃO FOTOGRÁFICA - TAC' }}
                    </h2>
                    <div class="report-page-title report-blue-title report-location-title report-overview-section-title">
                        {{ reportOverview.section_title || 'DOCUMENTAÇÃO FOTOGRÁFICA - TAC' }}
                    </div>

                    <article v-for="block in (reportOverview.blocks || [])" :key="block.position" class="report-photo-block report-overview-block">
                        <div class="report-photo-pair">
                            <article v-for="photoSlot in block.photos" :key="photoSlot.slot" class="report-photo-card">
                                <div class="report-photo-equipment">{{ reportOverview.equipment_label || 'FOTO EQUIPAMENTO' }}</div>
                                <div class="report-photo-title">
                                    <span>{{ photoSlot.number }}</span>
                                    <strong>Vista geral</strong>
                                </div>
                                <div class="report-photo-image">
                                    <img
                                        v-if="photoSlot.photo?.optimized_url"
                                        :src="photoSlot.photo.optimized_url"
                                        :alt="`Fotografia ${photoSlot.number} — Vista geral`"
                                    >
                                    <span v-else class="report-photo-unavailable">
                                        {{ photoSlot.photo?.status_label || 'Fotografia não informada' }}
                                    </span>
                                </div>
                            </article>
                        </div>
                        <div class="report-photo-text-section">
                            <div class="report-photo-blue-bar">Comentário:</div>
                            <p>{{ block.comment || '—' }}</p>
                        </div>
                        <div class="report-photo-text-section">
                            <div class="report-photo-blue-bar">Recomendações:</div>
                            <p>{{ block.recommendation || '—' }}</p>
                        </div>
                    </article>
                </div>
            </template>

            <template v-else-if="page.type === 'defect-evolution'">
                <div class="report-evolution-page">
                    <h2 class="report-general-aspects-title">
                        3. QUADRO DE EVOLUÇÃO DAS AVARIAS<span v-if="page.continuation"> — CONTINUAÇÃO</span>
                    </h2>
                    <table class="report-evolution-table">
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Avaria</th>
                                <th>Situação atual</th>
                                <th>Classe anterior</th>
                                <th>Classe atual</th>
                                <th>Quantidade</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in page.items" :key="item.id">
                                <td><strong>{{ item.code }}</strong></td>
                                <td>{{ item.title }}</td>
                                <td>{{ item.condition_label }}</td>
                                <td>{{ item.previous_classification?.code || '—' }}</td>
                                <td>{{ item.current_classification?.code || '—' }}</td>
                                <td>{{ item.quantity || '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </template>

            <template v-else-if="page.type === 'location-map'">
                <div class="report-map-page-layout">
                    <h2 v-if="page.annexTitle" class="report-general-aspects-title report-map-annex-title">
                        {{ page.annexTitle }}
                    </h2>
                    <h2 class="report-page-title report-blue-title report-location-title">
                        LOCALIZAÇÃO FOTOGRÁFICA - {{ page.category?.name || page.category?.code || 'SEM CATEGORIA' }}
                    </h2>
                    <article class="report-map-card">
                        <div class="report-map-title-box">{{ page.map.report_title || page.map.title }}</div>
                        <InspectionLocationReportMap class="report-map-visual" :map="page.map" />

                        <div class="report-map-footer">
                            <div class="report-map-classification-layout">
                                <table class="report-map-damage-table">
                                    <colgroup>
                                        <col class="report-map-damage-photos">
                                        <col class="report-map-damage-quantity">
                                        <col class="report-map-damage-gut">
                                        <col class="report-map-damage-gut">
                                        <col class="report-map-damage-gut">
                                        <col class="report-map-damage-classification">
                                    </colgroup>
                                    <thead>
                                        <tr><th colspan="6" class="report-map-table-title">CLASSIFICAÇÃO GUT</th></tr>
                                        <tr><th>FOTOS</th><th>{{ mapQuantityHeader(page.map) }}</th><th>GRAV.</th><th>URG.</th><th>TEND.</th><th>GRAV. DANO</th></tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="(row, rowIndex) in (page.map.damage_rows || [])"
                                            :key="row.assessment?.public_id || row.defect?.public_id || rowIndex"
                                        >
                                            <td>{{ row.photo_interval || '—' }}</td>
                                            <td>{{ damageQuantity(row, page.map) }}</td>
                                            <td :style="damageColorStyle(row.gut?.gravity?.color)">{{ row.gut?.gravity?.score ?? '—' }}</td>
                                            <td :style="damageColorStyle(row.gut?.urgency?.color)">{{ row.gut?.urgency?.score ?? '—' }}</td>
                                            <td :style="damageColorStyle(row.gut?.trend?.color)">{{ row.gut?.trend?.score ?? '—' }}</td>
                                            <td :style="damageColorStyle(row.classification?.color)">{{ row.classification?.code || '—' }}</td>
                                        </tr>
                                        <tr v-if="!(page.map.damage_rows || []).length"><td>—</td><td>—</td><td>—</td><td>—</td><td>—</td><td>—</td></tr>
                                    </tbody>
                                </table>

                                <div class="report-map-legend">
                                    <strong>LEGENDA:</strong>
                                    <div
                                        v-for="item in (page.map.classification_legend || [])"
                                        :key="item.public_id || item.code"
                                        class="report-map-legend-row"
                                    >
                                        <span class="report-map-color-swatch" :style="legendColorStyle(item.color)"></span>
                                        <span>{{ item.code }}</span>
                                    </div>
                                    <div v-if="!(page.map.classification_legend || []).length" class="report-map-legend-empty">—</div>
                                </div>
                            </div>

                            <div class="report-map-observations">
                                <div class="report-map-observations-title">Observações:</div>
                                <ReportMapObservationText
                                    :map-id="page.map.public_id"
                                    :chunk-index="page.observationIndex"
                                    :text="page.observation.text"
                                    :font-size="page.observation.fontSize"
                                    :fitted="page.observation.fitted"
                                    :max-height-mm="32"
                                    @layout="updateObservationLayout"
                                />
                            </div>
                        </div>
                    </article>
                </div>
            </template>

            <template v-else-if="page.type === 'map-observations'">
                <div class="report-map-continuation-layout">
                    <h2 class="report-page-title report-blue-title report-location-title">
                        LOCALIZAÇÃO FOTOGRÁFICA - {{ page.category?.name || page.category?.code || 'SEM CATEGORIA' }}
                    </h2>
                    <div class="report-map-title-box">{{ page.map.report_title || page.map.title }}</div>
                    <div class="report-map-observations report-map-observations-continuation">
                        <div class="report-map-observations-title">OBSERVAÇÕES — CONTINUAÇÃO</div>
                        <ReportMapObservationText
                            :map-id="page.map.public_id"
                            :chunk-index="page.observationIndex"
                            :text="page.observation.text"
                            :font-size="page.observation.fontSize"
                            :fitted="page.observation.fitted"
                            :max-height-mm="210"
                            @layout="updateObservationLayout"
                        />
                    </div>
                </div>
            </template>

            <template v-else-if="page.type === 'photographic'">
                <h2 class="report-page-title report-blue-title report-location-title">
                    DOCUMENTAÇÃO FOTOGRÁFICA - {{ page.category }}
                </h2>
                <article v-for="block in page.items" :key="block.id" class="report-photo-block">
                    <div class="report-photo-pair" :class="{ 'report-photo-pair-single': block.photos.length === 1 }">
                        <article v-for="photo in block.photos" :key="photo.id" class="report-photo-card">
                            <div class="report-photo-equipment">{{ block.equipment_label || 'FOTO EQUIPAMENTO' }}</div>
                            <div class="report-photo-title">
                                <span>{{ photo.sequence ?? '—' }}</span>
                                <strong>{{ block.defect_title }}</strong>
                            </div>
                            <div class="report-photo-image" :class="visualClass(photo)">
                                <img v-if="photo.url" :src="photo.url" :alt="block.defect_title">
                                <span v-else class="report-photo-unavailable">{{ photo.status === 'ready' ? 'Imagem indisponível' : (photo.status_label || 'Processamento pendente') }}</span>
                            </div>
                        </article>
                    </div>
                    <div class="report-photo-classification">
                        {{ block.defect_code || '—' }} | {{ block.condition_label || '—' }} |
                        Anterior: {{ block.previous_classification?.code || '—' }} |
                        Atual: {{ block.current_classification?.code || block.classification_code || '—' }}
                    </div>
                    <div class="report-photo-text-section">
                        <div class="report-photo-blue-bar">Comentário:</div>
                        <p>{{ block.comment || '—' }}</p>
                    </div>
                    <div class="report-photo-text-section">
                        <div class="report-photo-blue-bar">Recomendações:</div>
                        <p>{{ block.recommendation || '—' }}</p>
                    </div>
                </article>
            </template>

            <template v-else-if="page.type === 'textual-findings'">
                <h2 class="report-page-title report-blue-title report-location-title">
                    REGISTROS SEM EVIDÊNCIA FOTOGRÁFICA<span v-if="page.continuation"> — CONTINUAÇÃO</span>
                </h2>
                <article v-for="finding in page.items" :key="finding.id" class="report-textual-finding">
                    <div class="report-textual-finding-title">
                        <strong>{{ finding.code }} — {{ finding.title }}</strong>
                        <span>{{ finding.condition_label }}</span>
                    </div>
                    <div class="report-textual-finding-classes">
                        Classe anterior: <strong>{{ finding.previous_classification?.code || '—' }}</strong>
                        · Classe atual: <strong>{{ finding.current_classification?.code || '—' }}</strong>
                    </div>
                    <div class="report-photo-text-section">
                        <div class="report-photo-blue-bar">Justificativa:</div>
                        <p>{{ finding.reason || '—' }}</p>
                    </div>
                    <div class="report-photo-text-section">
                        <div class="report-photo-blue-bar">Comentário:</div>
                        <p>{{ finding.comment || '—' }}</p>
                    </div>
                    <div class="report-photo-text-section">
                        <div class="report-photo-blue-bar">Recomendações:</div>
                        <p>{{ finding.recommendation || '—' }}</p>
                    </div>
                </article>
            </template>

        </ReportA4Page>
    </div>
</template>

<style scoped>
.report-preview-pages { display: flex; flex-direction: column; gap: 0; overflow-x: auto; padding: 0 4mm 12mm; }
.report-page-title { margin: 0 0 6mm; padding-bottom: 2mm; border-bottom: 1px solid #111827; font-size: 11pt; font-weight: 800; letter-spacing: .02em; }
.report-blue-title { color: #fff; padding: 2mm 3mm; border: 0; background: #062b68; font-size: 9pt; text-align: center; }
.report-location-title { font-family: Georgia, 'Times New Roman', serif; font-size: 11pt; line-height: 1.1; text-transform: uppercase; }
.report-general-aspects-page { display: flex; width: 100%; height: 252mm; min-height: 0; flex-direction: column; overflow: hidden; }
.report-general-aspects-title { flex: none; margin: 0 0 8mm; padding: 0; border: 0; background: transparent; color: #111827; font-family: Georgia, 'Times New Roman', serif; font-size: 10pt; font-weight: 800; line-height: 1.1; text-align: left; }
.report-general-aspects-body { min-height: 0; flex: 1; overflow: hidden; font-family: Georgia, 'Times New Roman', serif; font-size: 10pt; }
.report-evolution-page { height: 252mm; overflow: hidden; }
.report-evolution-table { width: 100%; border-collapse: collapse; table-layout: fixed; font-family: Georgia, 'Times New Roman', serif; font-size: 8pt; }
.report-evolution-table th, .report-evolution-table td { border: 1px solid #94a3b8; padding: 2.5mm 2mm; vertical-align: top; }
.report-evolution-table th { background: #062b68; color: #fff; font-size: 7pt; text-transform: uppercase; }
.report-evolution-table th:nth-child(1) { width: 17%; }
.report-evolution-table th:nth-child(2) { width: 27%; }
.report-evolution-table th:nth-child(3) { width: 17%; }
.report-evolution-table th:nth-child(4), .report-evolution-table th:nth-child(5) { width: 13%; }
.report-evolution-table th:nth-child(6) { width: 13%; }
.report-textual-finding { margin-bottom: 6mm; border: 1px solid #94a3b8; font-family: Georgia, 'Times New Roman', serif; }
.report-textual-finding-title { display: flex; justify-content: space-between; gap: 4mm; padding: 3mm; background: #e2e8f0; font-size: 9pt; }
.report-textual-finding-classes { padding: 2.5mm 3mm; border-top: 1px solid #94a3b8; font-size: 8pt; }
.report-overview-page { display: flex; width: 100%; height: 252mm; min-height: 0; flex-direction: column; overflow: hidden; }
.report-overview-heading { margin-bottom: 4mm; }
.report-map-annex-title { margin-bottom: 4mm; }
.report-overview-section-title { flex: none; margin-bottom: 2mm; }
.report-photo-block.report-overview-block { flex: none; margin-bottom: 2mm; }
.report-photo-block.report-overview-block:last-child { margin-bottom: 0; }
.report-overview-block .report-photo-image { height: 43mm; }
.report-overview-block .report-photo-text-section p { min-height: 0; max-height: 20mm; padding: 2mm 4mm; overflow: hidden; font-size: 7pt; line-height: 1.2; text-align: left; }
.report-overview-block .report-photo-blue-bar { padding-top: 1mm; padding-bottom: 1mm; }
.report-cover-layout { position: relative; display: flex; min-height: 0; flex: 1; flex-direction: column; }
.report-cover-rule { position: relative; z-index: 1; height: 1px; flex: none; margin: 4mm 0 0; background: #111827; }
.report-cover-title-stage { display: flex; min-height: 0; flex: 1; align-items: center; }
.report-cover-title { display: grid; width: 100%; gap: 4mm; padding: 0 12mm; text-align: left; font-family: Georgia, serif; font-size: 15pt; font-weight: 700; line-height: 1.15; text-transform: uppercase; }
.report-cover-title p { margin: 0; }
.report-cover-title-compact { gap: 2.5mm; }
.report-cover-title-dense { gap: 1.5mm; padding: 0 8mm; font-size: 12pt; }
.report-cover-bottom { position: relative; z-index: 1; flex: none; background: #fff; }
.report-cover-history { display: grid; grid-template-columns: 13mm minmax(0, 1fr); border-top: 1px solid #111827; border-bottom: 1px solid #111827; font-family: Arial, sans-serif; }
.report-cover-history-label { display: flex; align-items: center; justify-content: center; border-right: 1px solid #111827; font-size: 10pt; line-height: 1.05; text-align: center; }
.report-cover-history table { width: 100%; border-collapse: collapse; table-layout: fixed; font-size: 7.5pt; }
.report-cover-history th, .report-cover-history td { padding: 2mm 1.5mm; border-right: 1px solid #111827; border-bottom: 1px solid #111827; text-align: center; vertical-align: middle; }
.report-cover-history th { font-size: 6.5pt; text-transform: uppercase; }
.report-cover-history th:nth-child(1), .report-cover-history td:nth-child(1) { width: 9%; }
.report-cover-history th:nth-child(2), .report-cover-history td:nth-child(2) { width: 25%; text-align: left; }
.report-cover-history th:nth-child(3), .report-cover-history td:nth-child(3) { width: 8%; }
.report-cover-history th:nth-child(4), .report-cover-history td:nth-child(4) { width: 15%; }
.report-cover-history th:nth-child(n+5), .report-cover-history td:nth-child(n+5) { width: 10.75%; }
.report-cover-history td:nth-child(n+5) { overflow-wrap: anywhere; white-space: normal; }
.report-cover-history th:last-child, .report-cover-history td:last-child { border-right: 0; }
.report-cover-history tr:last-child td { border-bottom: 0; }
.report-cover-history-compact table { font-size: 6.7pt; }
.report-cover-history-compact th, .report-cover-history-compact td { padding-top: 1.2mm; padding-bottom: 1.2mm; }
.report-cover-history-dense table { font-size: 5.8pt; }
.report-cover-history-dense th, .report-cover-history-dense td { padding-top: .7mm; padding-bottom: .7mm; }
.report-cover-emission-legend { margin-top: 2mm; border-bottom: 1px solid #111827; font-family: Arial, sans-serif; }
.report-cover-emission-legend > strong { display: block; padding: 1.5mm; border-bottom: 1px solid #111827; font-size: 8pt; text-align: center; }
.report-cover-emission-legend > div { display: grid; grid-template-columns: repeat(5, 1fr); gap: 1mm 3mm; padding: 2mm; font-size: 6.5pt; line-height: 1.2; }
.report-cover-emission-legend span { display: block; }
.report-cover-approval { display: grid; grid-template-columns: 1fr 1.18fr 1.04fr 1.2fr .62fr .95fr; margin-top: 0; border-bottom: 2px solid #111827; font-family: Arial, sans-serif; }
.report-cover-approval > div { display: flex; min-width: 0; min-height: 14mm; flex-direction: column; align-items: center; justify-content: center; padding: 1.5mm 2mm; border-right: 1px solid #111827; text-align: center; }
.report-cover-approval > div:last-child { border-right: 0; }
.report-cover-approval span { display: block; font-size: 7.5pt; font-weight: 500; line-height: 1.05; }
.report-cover-approval strong { display: block; margin-top: .8mm; overflow-wrap: anywhere; font-size: 7.5pt; font-weight: 500; line-height: 1.15; }
.report-cover-approval span { display: block; color: #4b5563; font-size: 6.5pt; font-weight: 700; text-transform: uppercase; }
.report-cover-institutional { display: grid; grid-template-areas: "provider-logo designer-i revision page" "provider-role designer-ii revision page" "client-logo client-name samarco samarco"; grid-template-columns: 4fr 4.5fr 1.2fr 1.5fr; grid-template-rows: 14mm 14mm 18mm; margin-top: 10mm; border-top: 1px solid #111827; border-bottom: 2px solid #111827; color: #111827; font-family: Arial, sans-serif; }
.report-cover-provider-logo { grid-area: provider-logo; border-right: 1px solid #111827; border-bottom: 1px solid #111827; }
.report-cover-designer-i { grid-area: designer-i; border-right: 1px solid #111827; border-bottom: 1px solid #111827; }
.report-cover-revision { grid-area: revision; border-right: 1px solid #111827; border-bottom: 1px solid #111827; }
.report-cover-page { grid-area: page; border-bottom: 1px solid #111827; }
.report-cover-provider-role { grid-area: provider-role; display: flex; min-width: 0; align-items: center; justify-content: center; padding: 1.5mm 3mm; border-right: 1px solid #111827; border-bottom: 1px solid #111827; font-size: 11pt; line-height: 1.1; text-align: center; }
.report-cover-designer-ii { grid-area: designer-ii; border-right: 1px solid #111827; border-bottom: 1px solid #111827; }
.report-cover-client-logo { grid-area: client-logo; border-right: 1px solid #111827; }
.report-cover-client-name { grid-area: client-name; display: flex; min-width: 0; align-items: center; justify-content: center; padding: 1.5mm 3mm; border-right: 1px solid #111827; color: #00008b; font-size: 12pt; font-weight: 700; line-height: 1.1; text-align: center; }
.report-cover-samarco { grid-area: samarco; }
.report-cover-institutional-logo { display: flex; min-width: 0; align-items: center; justify-content: center; padding: 1.5mm 3mm; }
.report-cover-institutional-logo img { display: block; width: 100%; height: 100%; object-fit: contain; }
.report-cover-institutional-logo strong { overflow-wrap: anywhere; font-size: 8pt; text-align: center; }
.report-cover-institutional-field { display: flex; min-width: 0; flex-direction: column; justify-content: flex-start; padding: 1.2mm 1.5mm; line-height: 1.05; }
.report-cover-institutional-field span { display: block; font-size: 7.5pt; font-weight: 500; }
.report-cover-institutional-field strong { display: block; margin-top: 1.5mm; overflow-wrap: anywhere; font-size: 9pt; font-weight: 700; text-align: center; }
.report-cover-institutional-compact { align-items: center; justify-content: center; }
.report-cover-institutional-compact span { font-size: 8pt; }
.report-cover-institutional-compact strong { margin-top: 3mm; font-size: 10pt; }
.report-cover-samarco span { font-size: 8pt; }
.report-cover-samarco strong { margin-top: 2.5mm; font-size: 9pt; }
.report-map-page-layout,
.report-map-continuation-layout { display: flex; width: 100%; height: 252mm; min-height: 0; flex-direction: column; overflow: hidden; }
.report-map-card { display: flex; min-height: 0; flex: 1; flex-direction: column; break-inside: avoid; }
.report-map-title-box { flex: none; margin-bottom: 3mm; padding: 2.5mm 4mm; border: 1px solid #111827; color: #111827; font-size: 9pt; font-weight: 800; line-height: 1.2; text-align: center; text-transform: uppercase; }
.report-map-visual { display: flex; min-height: 40mm; flex: 1; align-items: center; justify-content: center; overflow: hidden; }
.report-map-visual :deep(svg) { width: 100%; height: 100%; max-height: 100%; }
.report-map-footer { flex: none; margin-top: 3mm; font-family: Georgia, 'Times New Roman', serif; }
.report-map-classification-layout { display: grid; grid-template-columns: minmax(0, 1fr) 36mm; align-items: start; gap: 1.5mm; }
.report-map-damage-table { width: 100%; border-collapse: collapse; table-layout: fixed; font-size: 7pt; }
.report-map-damage-table th,
.report-map-damage-table td { height: 5.5mm; padding: .7mm 1.5mm; border: 1px solid #111827; line-height: 1.05; text-align: center; vertical-align: middle; }
.report-map-damage-photos { width: 18%; }
.report-map-damage-quantity { width: 16%; }
.report-map-damage-gut { width: 15%; }
.report-map-damage-classification { width: 21%; }
.report-map-damage-table .report-map-table-title { height: 6mm; padding: 1mm 2mm; background: #062b68; color: #fff; font-size: 8pt; font-weight: 800; }
.report-map-legend { width: 100%; overflow: hidden; border: 1px solid #111827; font-family: Georgia, 'Times New Roman', serif; font-size: 7pt; }
.report-map-legend > strong { display: flex; min-height: 6mm; align-items: center; padding: 1mm 2mm; border-bottom: 1px solid #111827; font-size: 8pt; }
.report-map-legend-row { display: grid; min-height: 5.5mm; grid-template-columns: 14mm minmax(0, 1fr); align-items: center; border-bottom: 1px solid #111827; }
.report-map-legend-row:last-child { border-bottom: 0; }
.report-map-color-swatch { align-self: stretch; border-right: 1px solid #111827; }
.report-map-legend-row > span:last-child { padding: .7mm 2mm; font-weight: 700; }
.report-map-legend-empty { display: flex; min-height: 5.5mm; align-items: center; justify-content: center; }
.report-map-observations { margin-top: 2mm; }
.report-map-observations-title { padding: 1mm 2mm; background: #062b68; color: #fff; font-family: Georgia, 'Times New Roman', serif; font-size: 9pt; line-height: 1.05; text-align: center; }
.report-map-observations-continuation { display: flex; min-height: 0; flex: 1; flex-direction: column; margin-top: 0; }
.report-map-observations-continuation :deep(.report-map-observation-copy) { flex: none; text-align: left; }
.report-photo-block { margin-bottom: 5mm; break-inside: avoid; }.report-photo-pair { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1mm; }.report-photo-pair-single .report-photo-card { grid-column: 1 / -1; width: 50%; justify-self: center; }.report-photo-card { min-width: 0; overflow: hidden; break-inside: avoid; }.report-photo-equipment { min-height: 6mm; padding: 1.2mm 2mm; background: #d1d1d1; color: #111827; font-family: Georgia, serif; font-size: 8pt; line-height: 1.1; }.report-photo-title { display: flex; align-items: baseline; gap: 3mm; min-height: 7mm; padding: 1.2mm 2mm; background: #fff; font-family: Georgia, serif; font-size: 9pt; line-height: 1.1; }.report-photo-title span { min-width: 5mm; font-size: 10pt; }.report-photo-title strong { font-weight: 700; }.report-photo-image { position: relative; height: 48mm; background: #e5e7eb; background-image: linear-gradient(145deg, #cbd5e1, #475569); }.report-photo-image img { display: block; width: 100%; height: 100%; object-fit: contain; background: #f3f4f6; }.report-photo-unavailable { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; padding: 4mm; background: rgba(15, 23, 42, .72); color: #fff; font-size: 8pt; font-weight: 700; text-align: center; }.report-photo-structure { background-image: linear-gradient(115deg, transparent 35%, rgba(15,23,42,.45) 36% 41%, transparent 42%), linear-gradient(30deg, #94a3b8, #475569); }.report-photo-surface { background-image: repeating-linear-gradient(105deg, rgba(255,255,255,.12) 0 2px, transparent 2px 18px), linear-gradient(145deg, #64748b, #334155); }.report-photo-repair { background-image: linear-gradient(90deg, transparent 47%, rgba(13,148,136,.78) 48% 52%, transparent 53%), linear-gradient(145deg, #cbd5e1, #64748b); }.report-photo-classification { min-height: 6mm; margin-top: 1mm; padding: 1.5mm 2mm; background: #fff; border: 1px solid #d1d5db; font-family: Georgia, serif; font-size: 8.5pt; font-weight: 700; line-height: 1.1; text-align: center; }.report-photo-text-section { margin-top: 1mm; break-inside: avoid; }.report-photo-blue-bar { padding: 1.2mm 2mm; background: #062b68; color: #fff; font-family: Georgia, serif; font-size: 9pt; line-height: 1.1; text-align: center; }.report-photo-text-section p { min-height: 10mm; margin: 0; padding: 3mm 5mm; font-family: Georgia, serif; font-size: 8.5pt; line-height: 1.35; text-align: center; white-space: pre-line; }
@media print { .report-preview-pages { display: block; } }
</style>
