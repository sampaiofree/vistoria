<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import ReportDefectTechnicalDetails from '@/components/domain/view-first/ReportDefectTechnicalDetails.vue';
import ReportClassificationBadge from '@/components/domain/view-first/ReportClassificationBadge.vue';
import ReportDefectPhotoViewer from '@/components/domain/view-first/ReportDefectPhotoViewer.vue';

const props = defineProps({
    photos: { type: Array, required: true },
    selectedPhotoId: { type: String, required: true },
    finding: { type: Object, required: true },
    block: { type: Object, required: true },
    inspectionNumber: { type: String, default: '' },
});

const emit = defineEmits(['close']);
const dialog = ref(null);
const closeButton = ref(null);
const backButton = ref(null);
const selectedIndex = ref(Math.max(0, props.photos.findIndex((photo) => photo.id === props.selectedPhotoId)));
const selectedPhoto = computed(() => props.photos[selectedIndex.value] ?? null);
const comparison = ref(null);
const comparisonPhotoIndex = ref(0);
const comparisonPhoto = computed(() => comparison.value?.photos?.[comparisonPhotoIndex.value] ?? null);
const photoViewer = ref(null);
const viewerPhotos = computed(() => photoViewer.value?.scope === 'previous' ? comparison.value?.photos ?? [] : props.photos);
const viewerIndex = computed(() => photoViewer.value?.scope === 'previous' ? comparisonPhotoIndex.value : selectedIndex.value);
const history = ref([]);
const historyLoading = ref(true);
const historyError = ref(false);
const abortController = new AbortController();
let previousFocus = null;
let previousOverflow = '';
let comparisonTrigger = null;

function close() {
    emit('close');
}

function selectPhoto(index) {
    if (index >= 0 && index < props.photos.length) selectedIndex.value = index;
}

function selectComparison(item, event) {
    comparisonTrigger = event.currentTarget;
    comparison.value = item;
    comparisonPhotoIndex.value = 0;
    nextTick(() => backButton.value?.focus());
}

function returnToSummary() {
    comparison.value = null;
    nextTick(() => comparisonTrigger?.focus());
}

function selectComparisonPhoto(index) {
    if (index >= 0 && index < (comparison.value?.photos?.length ?? 0)) comparisonPhotoIndex.value = index;
}

function openPhotoViewer(scope, event) {
    const photo = scope === 'previous' ? comparisonPhoto.value : selectedPhoto.value;
    if (!photo?.url) return;
    photoViewer.value = { scope, trigger: event.currentTarget };
}

function closePhotoViewer() {
    const trigger = photoViewer.value?.trigger;
    photoViewer.value = null;
    nextTick(() => trigger?.isConnected && trigger.focus());
}

function selectViewerPhoto(index) {
    if (photoViewer.value?.scope === 'previous') selectComparisonPhoto(index);
    else selectPhoto(index);
}

function onKeydown(event) {
    if (photoViewer.value) return;
    if (event.key === 'Escape') {
        event.preventDefault();
        close();
    } else if (event.key === 'ArrowLeft') {
        if (event.target?.closest?.('[data-comparison-photos]')) selectComparisonPhoto(comparisonPhotoIndex.value - 1);
        else selectPhoto(selectedIndex.value - 1);
    } else if (event.key === 'ArrowRight') {
        if (event.target?.closest?.('[data-comparison-photos]')) selectComparisonPhoto(comparisonPhotoIndex.value + 1);
        else selectPhoto(selectedIndex.value + 1);
    } else if (event.key === 'Tab' && dialog.value) {
        const focusable = [...dialog.value.querySelectorAll('button:not([disabled]), a[href]')];
        if (!focusable.length) return;
        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    }
}

onMounted(async () => {
    previousFocus = document.activeElement;
    previousOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    document.addEventListener('keydown', onKeydown);
    await nextTick();
    closeButton.value?.focus();

    try {
        const response = await fetch(props.finding.report_history_url, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
            signal: abortController.signal,
        });
        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        const data = await response.json();
        if (data.assessment_public_id !== props.finding.assessment?.public_id) {
            throw new Error('Avaliação diferente da exibida no relatório');
        }
        history.value = Array.isArray(data.history) ? data.history : [];
    } catch (error) {
        if (error.name !== 'AbortError') historyError.value = true;
    } finally {
        historyLoading.value = false;
    }
});

onBeforeUnmount(() => {
    abortController.abort();
    document.removeEventListener('keydown', onKeydown);
    document.body.style.overflow = previousOverflow;
    nextTick(() => previousFocus?.focus?.());
});
</script>

<template>
    <Teleport to="body">
        <div class="report-defect-photo-modal fixed inset-0 z-[90] flex items-center justify-center bg-slate-950/80 p-3 sm:p-6" @mousedown.self="close">
            <section ref="dialog" role="dialog" aria-modal="true" :aria-hidden="photoViewer ? 'true' : undefined" :inert="photoViewer ? '' : undefined" :aria-label="`Avaria ${finding.code}`" class="flex max-h-[95vh] w-full max-w-6xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
                <header class="flex shrink-0 items-start justify-between gap-4 border-b border-slate-200 px-4 py-3 sm:px-6 sm:py-4">
                    <div class="min-w-0">
                        <p class="text-xs font-semibold uppercase tracking-widest text-teal-700">Avaria no relatório {{ inspectionNumber }}</p>
                        <h2 class="mt-1 text-lg font-bold text-slate-950 sm:text-xl">{{ finding.code }} · {{ finding.title }}</h2>
                        <p v-if="block.historical_label" class="mt-1 text-sm font-medium text-amber-800">{{ block.historical_label }}</p>
                    </div>
                    <button ref="closeButton" type="button" class="shrink-0 rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-600" @click="close">Fechar</button>
                </header>

                <div v-if="comparison" class="min-h-0 flex-1 overflow-y-auto p-4 sm:p-6">
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                        <button ref="backButton" type="button" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-600" @click="returnToSummary">← Voltar ao resumo</button>
                        <p class="text-sm text-slate-500">Comparação das avaliações desta avaria</p>
                    </div>
                    <div class="grid gap-4 lg:grid-cols-2">
                        <section :key="comparison.public_id" class="min-w-0 overflow-hidden rounded-xl border border-slate-200" aria-label="Avaliação anterior">
                            <div class="border-b border-slate-200 p-4">
                                <p class="text-xs font-bold uppercase tracking-widest text-slate-500">Avaliação anterior</p>
                                <h3 class="mt-1 font-bold text-slate-900">{{ comparison.inspection_number || 'Inspeção' }}</h3>
                                <p class="mt-1 text-sm text-slate-600">{{ comparison.assessed_at || 'Data não informada' }} · {{ comparison.condition_label || '—' }} · Classe <ReportClassificationBadge :code="comparison.classification_code" :color="comparison.classification_color" /></p>
                            </div>
                            <div class="bg-slate-950 p-3 sm:p-4" data-comparison-photos>
                                <div class="flex h-64 items-center justify-center rounded-lg bg-slate-900 sm:h-80">
                                    <button v-if="comparisonPhoto?.url" type="button" class="h-full w-full cursor-zoom-in rounded-lg focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-400" :aria-label="`Ampliar foto anterior de ${finding.code}`" @click="openPhotoViewer('previous', $event)"><img :src="comparisonPhoto.url" :alt="comparisonPhoto.caption || `Foto anterior de ${finding.code}`" class="max-h-full w-full object-contain"></button>
                                    <p v-else class="p-6 text-center text-sm text-white">Fotografia indisponível nesta avaliação.</p>
                                </div>
                                <div v-if="comparison.photos?.length > 1" class="mt-3 flex gap-2 overflow-x-auto pb-1" aria-label="Fotografias da avaliação anterior">
                                    <button v-for="(photo, index) in comparison.photos" :key="photo.id" type="button" class="h-14 w-16 shrink-0 overflow-hidden rounded-lg border-2 bg-slate-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white" :class="index === comparisonPhotoIndex ? 'border-teal-400' : 'border-transparent'" :aria-label="`Ver foto anterior ${index + 1} de ${comparison.photos.length}`" :aria-pressed="index === comparisonPhotoIndex" @click="selectComparisonPhoto(index)"><img :src="photo.thumbnail_url || photo.url" :alt="photo.caption || `Foto ${index + 1}`" class="h-full w-full object-cover"></button>
                                </div>
                                <p v-if="comparisonPhoto?.caption" class="mt-3 text-sm text-slate-200">{{ comparisonPhoto.caption }}</p>
                            </div>
                            <div class="p-3 sm:p-4"><ReportDefectTechnicalDetails :technical="comparison.technical_details" :classification-code="comparison.classification_code" :classification-color="comparison.classification_color" /></div>
                        </section>
                        <section class="min-w-0 overflow-hidden rounded-xl border border-slate-200" aria-label="Avaliação deste relatório">
                            <div class="border-b border-slate-200 p-4">
                                <p class="text-xs font-bold uppercase tracking-widest text-teal-700">Neste relatório</p>
                                <h3 class="mt-1 font-bold text-slate-900">{{ inspectionNumber || 'Inspeção' }}</h3>
                                <p class="mt-1 text-sm text-slate-600">{{ finding.assessment?.assessed_at || 'Data não informada' }} · {{ finding.condition_label || '—' }} · Classe <ReportClassificationBadge :code="finding.classification?.code" :color="finding.classification?.color" /></p>
                            </div>
                            <div class="bg-slate-950 p-3 sm:p-4">
                                <div class="flex h-64 items-center justify-center rounded-lg bg-slate-900 sm:h-80">
                                    <button v-if="selectedPhoto?.url" type="button" class="h-full w-full cursor-zoom-in rounded-lg focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-400" :aria-label="`Ampliar foto deste relatório de ${finding.code}`" @click="openPhotoViewer('current', $event)"><img :src="selectedPhoto.url" :alt="selectedPhoto.caption || `Foto de ${finding.code}`" class="max-h-full w-full object-contain"></button>
                                    <p v-else class="p-6 text-center text-sm text-white">Fotografia indisponível nesta avaliação.</p>
                                </div>
                                <div v-if="photos.length > 1" class="mt-3 flex gap-2 overflow-x-auto pb-1" aria-label="Fotografias deste relatório">
                                    <button v-for="(photo, index) in photos" :key="photo.id" type="button" class="h-14 w-16 shrink-0 overflow-hidden rounded-lg border-2 bg-slate-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white" :class="index === selectedIndex ? 'border-teal-400' : 'border-transparent'" :aria-label="`Ver foto atual ${index + 1} de ${photos.length}`" :aria-pressed="index === selectedIndex" @click="selectPhoto(index)"><img :src="photo.thumbnail_url || photo.url" :alt="photo.caption || `Foto ${index + 1}`" class="h-full w-full object-cover"></button>
                                </div>
                                <p v-if="selectedPhoto?.caption" class="mt-3 text-sm text-slate-200">{{ selectedPhoto.caption }}</p>
                            </div>
                            <div class="p-3 sm:p-4"><ReportDefectTechnicalDetails :technical="finding.technical_details" :classification-code="finding.classification?.code" :classification-color="finding.classification?.color" /></div>
                        </section>
                    </div>
                </div>

                <div v-else class="grid min-h-0 flex-1 overflow-y-auto lg:grid-cols-[minmax(0,1.35fr)_minmax(310px,0.85fr)]">
                    <div class="bg-slate-950 p-3 sm:p-5">
                        <div class="flex min-h-56 items-center justify-center rounded-xl bg-slate-900 lg:h-[min(62vh,600px)]">
                            <button v-if="selectedPhoto?.url" type="button" class="h-full w-full cursor-zoom-in rounded-xl focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-400" :aria-label="`Ampliar foto de ${finding.code}`" @click="openPhotoViewer('current', $event)"><img :src="selectedPhoto.url" :alt="selectedPhoto.caption || finding.title" class="max-h-[62vh] w-full object-contain"></button>
                            <p v-else class="p-6 text-center text-sm text-white">Fotografia indisponível.</p>
                        </div>
                        <div v-if="photos.length > 1" class="mt-3 flex gap-2 overflow-x-auto pb-1" aria-label="Fotografias desta avaria">
                            <button v-for="(photo, index) in photos" :key="photo.id" type="button" class="h-16 w-20 shrink-0 overflow-hidden rounded-lg border-2 bg-slate-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white" :class="index === selectedIndex ? 'border-teal-400' : 'border-transparent'" :aria-label="`Ver foto ${index + 1} de ${photos.length}`" :aria-pressed="index === selectedIndex" @click="selectPhoto(index)">
                                <img :src="photo.thumbnail_url || photo.url" :alt="photo.caption || `Foto ${index + 1}`" class="h-full w-full object-cover">
                            </button>
                        </div>
                        <p v-if="selectedPhoto?.caption" class="mt-3 text-sm leading-5 text-slate-200">{{ selectedPhoto.caption }}</p>
                    </div>

                    <div class="space-y-5 p-4 sm:p-6">
                        <section>
                            <h3 class="text-xs font-bold uppercase tracking-widest text-slate-500">Nesta avaliação</h3>
                            <dl class="mt-3 grid grid-cols-2 gap-3 text-sm">
                                <div class="rounded-xl bg-slate-50 p-3"><dt class="text-xs text-slate-500">Situação</dt><dd class="mt-1 font-semibold text-slate-900">{{ finding.condition_label || '—' }}</dd></div>
                                <div class="rounded-xl bg-slate-50 p-3"><dt class="text-xs text-slate-500">Inspeção de referência</dt><dd class="mt-1 font-semibold text-slate-900">{{ finding.assessment?.inspection?.number || inspectionNumber || '—' }}</dd></div>
                                <div class="col-span-2 rounded-xl bg-slate-50 p-3"><dt class="text-xs text-slate-500">Localização</dt><dd class="mt-1 font-semibold text-slate-900">{{ finding.location || '—' }}</dd></div>
                            </dl>
                            <div class="mt-3"><ReportDefectTechnicalDetails :technical="finding.technical_details" :classification-code="finding.classification?.code" :classification-color="finding.classification?.color" /></div>
                            <p v-if="finding.comment" class="mt-4 whitespace-pre-line text-sm leading-6 text-slate-700"><strong class="text-slate-950">Comentário:</strong> {{ finding.comment }}</p>
                            <p v-if="finding.recommendation" class="mt-3 whitespace-pre-line text-sm leading-6 text-slate-700"><strong class="text-slate-950">Recomendação:</strong> {{ finding.recommendation }}</p>
                            <a v-if="finding.assessment_url" :href="finding.assessment_url" target="_blank" rel="noopener noreferrer" class="mt-4 inline-flex rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-teal-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-600">Ver avaliação completa ↗</a>
                        </section>

                        <section class="border-t border-slate-200 pt-5">
                            <h3 class="text-xs font-bold uppercase tracking-widest text-slate-500">Histórico até este relatório</h3>
                            <p v-if="historyLoading" class="mt-3 text-sm text-slate-500" role="status">Carregando histórico…</p>
                            <p v-else-if="historyError" class="mt-3 text-sm text-rose-700" role="alert">Não foi possível carregar o histórico.</p>
                            <p v-else-if="!history.length" class="mt-3 text-sm text-slate-500">Sem avaliações anteriores neste histórico.</p>
                            <ol v-else class="mt-3 space-y-3 border-l-2 border-slate-200 pl-4">
                                <li v-for="item in history" :key="item.public_id" class="relative rounded-xl border border-slate-200 p-3 text-sm before:absolute before:-left-[23px] before:top-4 before:h-2.5 before:w-2.5 before:rounded-full before:bg-teal-600">
                                    <p class="font-semibold text-slate-900">{{ item.inspection_number || 'Inspeção' }} <span class="font-normal text-slate-500">· {{ item.assessed_at || 'Data não informada' }}</span></p>
                                    <p class="mt-1 text-slate-600">{{ item.condition_label || '—' }} · Classe <ReportClassificationBadge :code="item.classification_code" :color="item.classification_color" /></p>
                                    <button type="button" class="mt-2 rounded-lg border border-teal-700 px-3 py-1.5 text-xs font-semibold text-teal-800 hover:bg-teal-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-600" @click="selectComparison(item, $event)">Comparar avaliações</button>
                                </li>
                            </ol>
                        </section>
                    </div>
                </div>
            </section>
            <ReportDefectPhotoViewer
                v-if="photoViewer"
                :photos="viewerPhotos"
                :initial-index="viewerIndex"
                :title="`Avaria ${finding.code} · ${photoViewer.scope === 'previous' ? 'avaliação anterior' : 'neste relatório'}`"
                @select-photo="selectViewerPhoto"
                @close="closePhotoViewer"
            />
        </div>
    </Teleport>
</template>

<style>
@media print {
    .report-defect-photo-modal { display: none !important; }
}
</style>
