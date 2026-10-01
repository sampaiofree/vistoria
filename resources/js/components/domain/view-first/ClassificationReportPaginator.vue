<script setup>
import { nextTick, onMounted, ref, watch } from 'vue';
import ClassificationReportTables from '@/components/domain/view-first/ClassificationReportTables.vue';

const props = defineProps({ summary: { type: Object, required: true } });
const emit = defineEmits(['pages', 'ready', 'error']);
const probe = ref(null);
const probePage = ref(null);
let runId = 0;

function emptyPage(includeHeader = false) {
    return { includeHeader, categories: [], specialRows: [], showSpecial: false };
}

function append(page, atom) {
    const next = {
        includeHeader: page.includeHeader,
        categories: [...page.categories],
        specialRows: [...page.specialRows],
        showSpecial: page.showSpecial,
    };

    if (atom.type === 'category') next.categories.push(atom.value);
    if (atom.type === 'special') next.specialRows.push(atom.value);
    if (atom.type === 'special' || atom.type === 'special-empty') next.showSpecial = true;

    return next;
}

async function fits(page, precedingPages) {
    probePage.value = finalize([...precedingPages, page]).at(-1);
    await nextTick();

    return probe.value ? probe.value.scrollHeight <= probe.value.clientHeight + 1 : true;
}

function finalize(pages) {
    let specialStarted = false;
    let classificationStarted = false;

    return pages.map((page) => {
        const result = {
            ...page,
            classificationContinuation: classificationStarted && page.categories.length > 0,
            specialContinuation: specialStarted && page.showSpecial,
        };
        if (page.categories.length) classificationStarted = true;
        if (page.showSpecial) specialStarted = true;

        return result;
    });
}

async function paginate() {
    const thisRun = ++runId;
    emit('ready', false);
    emit('error', null);
    const atoms = [
        ...(props.summary.categories || []).map((value) => ({ type: 'category', value })),
        ...((props.summary.special_assessment_rows || []).length
            ? props.summary.special_assessment_rows.map((value) => ({ type: 'special', value }))
            : [{ type: 'special-empty' }]),
    ];
    const pages = [];
    let current = emptyPage(true);

    if (document.fonts?.ready) await document.fonts.ready;
    if (thisRun !== runId) return;

    const headerFits = await fits(current, pages);
    if (thisRun !== runId) return;
    if (!headerFits) {
        emit('pages', []);
        emit('error', 'A tabela de identificação do equipamento não cabe na página do relatório.');
        return;
    }

    for (const atom of atoms) {
        if (thisRun !== runId) return;

        const candidate = append(current, atom);
        const candidateFits = await fits(candidate, pages);
        if (thisRun !== runId) return;
        if (candidateFits) {
            current = candidate;
            continue;
        }

        pages.push(current);
        current = append(emptyPage(false), atom);
        const nextPageFits = await fits(current, pages);
        if (thisRun !== runId) return;
        if (!nextPageFits) {
            emit('pages', []);
            emit('error', 'Uma tabela do resumo GUT não cabe na página do relatório.');
            return;
        }
    }

    if (current.includeHeader || current.categories.length || current.specialRows.length || current.showSpecial) pages.push(current);
    if (thisRun !== runId) return;

    emit('pages', finalize(pages));
    emit('ready', true);
}

watch(() => props.summary, paginate, { deep: true });
onMounted(paginate);
</script>

<template>
    <div class="classification-report-measure" aria-hidden="true">
        <div ref="probe" class="classification-report-probe">
            <ClassificationReportTables
                v-if="probePage"
                :summary="summary"
                v-bind="probePage"
            />
        </div>
    </div>
</template>

<style scoped>
.classification-report-measure { position: fixed; top: 0; left: -10000px; width: 177mm; visibility: hidden; pointer-events: none; }
.classification-report-probe { width: 177mm; height: 246mm; overflow: auto; font-family: 'Times New Roman', Times, serif !important; }
.classification-report-probe :deep(*) { font-family: 'Times New Roman', Times, serif !important; }
</style>
