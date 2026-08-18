<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({
    mapId: { type: String, required: true },
    chunkIndex: { type: Number, required: true },
    text: { type: String, default: '' },
    fontSize: { type: Number, default: 9 },
    fitted: { type: Boolean, default: false },
    maxHeightMm: { type: Number, required: true },
});

const emit = defineEmits(['layout']);
const element = ref(null);
const displayedText = ref(props.text);
const measuredFontSize = ref(props.fontSize);
let measurementRun = 0;

const textStyle = computed(() => ({
    height: `${props.maxHeightMm}mm`,
    fontSize: `${measuredFontSize.value}pt`,
}));

async function contentFits() {
    await nextTick();

    return element.value === null
        || element.value.scrollHeight <= element.value.clientHeight + 1;
}

function preferredSplitPosition(text, maximum) {
    for (let index = maximum - 1; index >= Math.floor(maximum * 0.6); index -= 1) {
        if (/\s/u.test(text[index] || '')) {
            return index + 1;
        }
    }

    return maximum;
}

async function largestFittingPrefix(text, run) {
    let lower = 1;
    let upper = Math.max(1, text.length - 1);
    let best = 0;

    while (lower <= upper && run === measurementRun) {
        const middle = Math.floor((lower + upper) / 2);
        displayedText.value = text.slice(0, middle);

        if (await contentFits()) {
            best = middle;
            lower = middle + 1;
        } else {
            upper = middle - 1;
        }
    }

    return preferredSplitPosition(text, Math.max(1, best));
}

async function measure() {
    const run = ++measurementRun;
    const source = props.text;

    displayedText.value = source;

    if (props.fitted) {
        measuredFontSize.value = props.fontSize;

        return;
    }

    for (let size = 9; size >= 5; size -= 0.25) {
        if (run !== measurementRun) return;

        measuredFontSize.value = size;
        displayedText.value = source;

        if (await contentFits()) {
            emit('layout', {
                mapId: props.mapId,
                chunkIndex: props.chunkIndex,
                text: source,
                overflow: null,
                fontSize: size,
            });

            return;
        }
    }

    measuredFontSize.value = 5;
    const splitPosition = await largestFittingPrefix(source, run);

    if (run !== measurementRun) return;

    const visibleText = source.slice(0, splitPosition);
    const overflow = source.slice(splitPosition);
    displayedText.value = visibleText;

    emit('layout', {
        mapId: props.mapId,
        chunkIndex: props.chunkIndex,
        text: visibleText,
        overflow,
        fontSize: 5,
    });
}

onMounted(measure);
onBeforeUnmount(() => {
    measurementRun += 1;
});
watch(
    () => [props.text, props.fontSize, props.fitted, props.maxHeightMm],
    measure,
);
</script>

<template>
    <p ref="element" class="report-map-observation-copy" :style="textStyle">{{ displayedText || '—' }}</p>
</template>

<style scoped>
.report-map-observation-copy {
    box-sizing: border-box;
    margin: 0;
    overflow: hidden;
    padding: 2.5mm 4mm;
    font-family: Georgia, 'Times New Roman', serif;
    line-height: 1.22;
    overflow-wrap: anywhere;
    text-align: center;
    white-space: pre-wrap;
}
</style>
