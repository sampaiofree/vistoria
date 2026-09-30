<script setup>
import { nextTick, onMounted, ref, watch } from 'vue';
import ReportQuantityPage from '@/components/domain/view-first/ReportQuantityPage.vue';
import { paginateQuantityRows, ReportQuantityRowOverflowError } from '@/lib/reportQuantityPagination.js';

const props = defineProps({
    items: { type: Array, default: () => [] },
    type: { type: String, required: true },
});

const emit = defineEmits(['pages', 'ready', 'error']);
const probe = ref(null);
const probePage = ref(null);
let runId = 0;

async function fits(items, continuation) {
    probePage.value = {
        type: props.type,
        items,
        continuation,
        annexTitle: `ANEXO Z – QUANTITATIVO GERAL – ${props.type === 'civil-quantity' ? 'CIVIL' : 'REC'}`,
    };
    await nextTick();

    return probe.value ? probe.value.scrollHeight <= probe.value.clientHeight + 1 : true;
}

async function paginate() {
    const thisRun = ++runId;
    emit('ready', false);
    emit('error', null);

    if (!props.items.length) {
        emit('pages', []);
        emit('ready', true);
        return;
    }

    let result;
    try {
        result = await paginateQuantityRows(props.items, fits, () => thisRun === runId);
    } catch (error) {
        if (thisRun !== runId) return;
        emit('pages', []);
        emit('error', error instanceof ReportQuantityRowOverflowError
            ? `${error.message} Reduza o texto desta linha para exportar o relatório.`
            : 'Não foi possível paginar o quantitativo. Tente novamente.');
        return;
    }
    if (result === null) return;

    emit('pages', result.map((items, index) => ({
        type: props.type,
        key: `${props.type}-${index}`,
        category: props.type === 'rec-quantity' ? 'REC' : 'CV',
        items,
        orientation: 'landscape',
        continuation: index > 0,
    })));
    emit('ready', true);
}

watch(() => [props.items, props.type], paginate, { deep: true });
onMounted(paginate);
</script>

<template>
    <div class="report-quantity-measure" aria-hidden="true">
        <div ref="probe" class="report-quantity-probe">
            <ReportQuantityPage v-if="probePage" :page="probePage" measuring />
        </div>
    </div>
</template>

<style scoped>
.report-quantity-measure { position: fixed; top: 0; left: -10000px; width: 277mm; visibility: hidden; pointer-events: none; }
.report-quantity-probe { width: 277mm; height: 155mm; overflow: auto; }
</style>
