<script setup>
import { nextTick, onMounted, ref, watch } from 'vue';
import ReportSummaryPage from '@/components/domain/view-first/ReportSummaryPage.vue';

const props = defineProps({
    entries: { type: Array, default: () => [] },
    signature: { type: String, required: true },
});

const emit = defineEmits(['pages', 'ready']);
const probe = ref(null);
const probeEntries = ref([]);
const probeContinuation = ref(false);
let runId = 0;

async function fits(entries, continuation) {
    probeEntries.value = entries;
    probeContinuation.value = continuation;
    await nextTick();

    return probe.value ? probe.value.scrollHeight <= probe.value.clientHeight + 1 : true;
}

async function paginate() {
    const thisRun = ++runId;
    const signature = props.signature;
    emit('ready', { ready: false, signature });

    if (!props.entries.length) {
        emit('pages', { pages: [[]], signature });
        emit('ready', { ready: true, signature });
        return;
    }

    const result = [];
    let current = [];

    for (const entry of props.entries) {
        if (thisRun !== runId) return;

        const candidate = [...current, entry];
        if (await fits(candidate, result.length > 0)) {
            current = candidate;
            continue;
        }

        if (current.length) {
            result.push(current);
            current = [entry];

            if (!await fits(current, true)) {
                result.push(current);
                current = [];
            }
            continue;
        }

        result.push([entry]);
    }

    if (current.length) result.push(current);
    if (thisRun !== runId) return;

    emit('pages', { pages: result.length ? result : [[]], signature });
    emit('ready', { ready: true, signature });
}

watch(() => [props.entries, props.signature], paginate, { deep: true });
onMounted(paginate);
</script>

<template>
    <div class="report-summary-measure" aria-hidden="true">
        <div ref="probe" class="report-summary-probe">
            <ReportSummaryPage
                :entries="probeEntries"
                :continuation="probeContinuation"
                measuring
            />
        </div>
    </div>
</template>

<style scoped>
.report-summary-measure { position: fixed; left: -10000px; top: 0; width: 182mm; visibility: hidden; pointer-events: none; }
.report-summary-probe { width: 182mm; height: 252mm; overflow: auto; }
</style>
