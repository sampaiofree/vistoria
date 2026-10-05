<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { clampPhotoOffset, MAX_PHOTO_ZOOM, MIN_PHOTO_ZOOM, zoomPhotoAt } from '@/lib/reportPhotoZoom.js';

const props = defineProps({
    photos: { type: Array, required: true },
    initialIndex: { type: Number, default: 0 },
    title: { type: String, required: true },
});

const emit = defineEmits(['close', 'select-photo']);
const dialog = ref(null);
const closeButton = ref(null);
const viewport = ref(null);
const image = ref(null);
const index = ref(props.initialIndex);
const scale = ref(1);
const offset = ref({ x: 0, y: 0 });
const failed = ref(false);
const pointers = new Map();
let resizeObserver;

const photo = computed(() => props.photos[index.value] ?? null);
const availableIndices = computed(() => props.photos.flatMap((item, photoIndex) => item?.url ? [photoIndex] : []));
const position = computed(() => availableIndices.value.indexOf(index.value));
const transform = computed(() => `translate(${offset.value.x}px, ${offset.value.y}px) scale(${scale.value})`);
const imageSize = () => ({ width: image.value?.naturalWidth ?? 0, height: image.value?.naturalHeight ?? 0 });
const viewportSize = () => ({ width: viewport.value?.clientWidth ?? 0, height: viewport.value?.clientHeight ?? 0 });

function constrain() {
    offset.value = clampPhotoOffset(offset.value, scale.value, viewportSize(), imageSize());
}

function changeScale(value, clientX = null, clientY = null) {
    const rect = viewport.value?.getBoundingClientRect();
    if (!rect) return;
    const point = {
        x: (clientX ?? rect.left + rect.width / 2) - rect.left - rect.width / 2,
        y: (clientY ?? rect.top + rect.height / 2) - rect.top - rect.height / 2,
    };
    const next = zoomPhotoAt(offset.value, scale.value, value, point, viewportSize(), imageSize());
    scale.value = next.scale;
    offset.value = next.offset;
}

function resetZoom() {
    scale.value = 1;
    offset.value = { x: 0, y: 0 };
    pointers.clear();
}

function changePhoto(direction) {
    const next = availableIndices.value[position.value + direction];
    if (next === undefined) return;
    index.value = next;
    emit('select-photo', next);
}

function onWheel(event) {
    if (event.ctrlKey || event.metaKey) return;
    event.preventDefault();
    changeScale(scale.value + (event.deltaY < 0 ? 0.25 : -0.25), event.clientX, event.clientY);
}

function pair() {
    const values = [...pointers.values()].slice(0, 2);
    if (values.length < 2) return null;
    return {
        x: (values[0].x + values[1].x) / 2,
        y: (values[0].y + values[1].y) / 2,
        distance: Math.hypot(values[0].x - values[1].x, values[0].y - values[1].y),
    };
}

function onPointerDown(event) {
    if (event.pointerType === 'mouse' && event.button !== 0) return;
    viewport.value?.setPointerCapture(event.pointerId);
    pointers.set(event.pointerId, { x: event.clientX, y: event.clientY });
}

function onPointerMove(event) {
    const previous = pointers.get(event.pointerId);
    if (!previous) return;
    const oldPair = pair();
    pointers.set(event.pointerId, { x: event.clientX, y: event.clientY });
    const nextPair = pair();

    if (oldPair && nextPair && oldPair.distance > 0) {
        offset.value = {
            x: offset.value.x + nextPair.x - oldPair.x,
            y: offset.value.y + nextPair.y - oldPair.y,
        };
        changeScale(scale.value * nextPair.distance / oldPair.distance, nextPair.x, nextPair.y);
    } else if (scale.value > 1) {
        offset.value = clampPhotoOffset({
            x: offset.value.x + event.clientX - previous.x,
            y: offset.value.y + event.clientY - previous.y,
        }, scale.value, viewportSize(), imageSize());
    }
}

function onPointerEnd(event) {
    pointers.delete(event.pointerId);
    if (viewport.value?.hasPointerCapture(event.pointerId)) viewport.value.releasePointerCapture(event.pointerId);
}

function onKeydown(event) {
    if (event.ctrlKey || event.metaKey || event.altKey) return;
    if (event.key === 'Escape') {
        event.preventDefault();
        emit('close');
    } else if (event.key === '+' || event.key === '=') {
        event.preventDefault();
        changeScale(scale.value + 0.25);
    } else if (event.key === '-') {
        event.preventDefault();
        changeScale(scale.value - 0.25);
    } else if (event.key === '0') {
        event.preventDefault();
        resetZoom();
    } else if (event.key.startsWith('Arrow')) {
        event.preventDefault();
        if (scale.value === 1) {
            if (event.key === 'ArrowLeft') changePhoto(-1);
            if (event.key === 'ArrowRight') changePhoto(1);
        } else {
            const shift = { ArrowLeft: [40, 0], ArrowRight: [-40, 0], ArrowUp: [0, 40], ArrowDown: [0, -40] }[event.key];
            if (shift) offset.value = clampPhotoOffset({ x: offset.value.x + shift[0], y: offset.value.y + shift[1] }, scale.value, viewportSize(), imageSize());
        }
    } else if (event.key === 'Tab' && dialog.value) {
        const focusable = [...dialog.value.querySelectorAll('button:not([disabled])')];
        if (!focusable.length) return;
        if (event.shiftKey && document.activeElement === focusable[0]) {
            event.preventDefault();
            focusable.at(-1).focus();
        } else if (!event.shiftKey && document.activeElement === focusable.at(-1)) {
            event.preventDefault();
            focusable[0].focus();
        }
    }
}

watch(index, () => {
    failed.value = false;
    resetZoom();
});

onMounted(async () => {
    document.addEventListener('keydown', onKeydown);
    resizeObserver = new ResizeObserver(constrain);
    resizeObserver.observe(viewport.value);
    await nextTick();
    closeButton.value?.focus();
});

onBeforeUnmount(() => {
    resizeObserver?.disconnect();
    document.removeEventListener('keydown', onKeydown);
});
</script>

<template>
    <Teleport to="body">
        <div class="report-defect-photo-viewer fixed inset-0 z-[100] bg-slate-950 text-white">
            <section ref="dialog" role="dialog" aria-modal="true" :aria-label="`Foto ampliada: ${title}`" class="flex h-full min-h-0 flex-col">
                <header class="flex shrink-0 flex-wrap items-center justify-between gap-2 border-b border-white/20 bg-slate-950/95 p-3 sm:px-6">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold">{{ title }}</p>
                        <p class="text-xs text-slate-300">Foto {{ position + 1 }} de {{ availableIndices.length }} · {{ Math.round(scale * 100) }}%</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <button type="button" class="viewer-button" aria-label="Reduzir foto" :disabled="scale <= MIN_PHOTO_ZOOM" @click="changeScale(scale - 0.25)">−</button>
                        <button type="button" class="viewer-button" aria-label="Ampliar foto" :disabled="scale >= MAX_PHOTO_ZOOM" @click="changeScale(scale + 0.25)">+</button>
                        <button type="button" class="viewer-button px-3" aria-label="Restaurar tamanho da foto" :disabled="scale === 1" @click="resetZoom">Ajustar</button>
                        <button ref="closeButton" type="button" class="viewer-button px-3" aria-label="Voltar aos detalhes da avaria" @click="emit('close')">Fechar</button>
                    </div>
                </header>
                <div
                    ref="viewport"
                    class="relative min-h-0 flex-1 overflow-hidden bg-slate-900"
                    :class="scale > 1 ? 'cursor-grab active:cursor-grabbing' : ''"
                    style="touch-action: none"
                    @wheel="onWheel"
                    @pointerdown="onPointerDown"
                    @pointermove="onPointerMove"
                    @pointerup="onPointerEnd"
                    @pointercancel="onPointerEnd"
                >
                    <img
                        v-if="photo?.url && !failed"
                        ref="image"
                        :key="photo.id"
                        :src="photo.url"
                        :alt="photo.caption || title"
                        :style="{ transform }"
                        class="h-full w-full select-none object-contain"
                        draggable="false"
                        @load="constrain"
                        @error="failed = true"
                    >
                    <p v-else class="absolute inset-0 flex items-center justify-center p-6 text-center text-sm">Fotografia indisponível.</p>
                </div>
                <footer class="flex shrink-0 flex-wrap items-center justify-between gap-2 border-t border-white/20 bg-slate-950 p-3 sm:px-6">
                    <button type="button" class="viewer-button px-3" aria-label="Foto anterior" :disabled="position <= 0" @click="changePhoto(-1)">← Anterior</button>
                    <p class="min-w-0 flex-1 text-center text-xs text-slate-300">{{ photo?.caption || 'Arraste para mover a foto ampliada' }}</p>
                    <button type="button" class="viewer-button px-3" aria-label="Próxima foto" :disabled="position >= availableIndices.length - 1" @click="changePhoto(1)">Próxima →</button>
                </footer>
            </section>
        </div>
    </Teleport>
</template>

<style scoped>
.viewer-button { min-height: 44px; min-width: 44px; border: 1px solid rgb(255 255 255 / 40%); border-radius: 8px; background: rgb(255 255 255 / 10%); font-weight: 600; }
.viewer-button:hover:not(:disabled) { background: rgb(255 255 255 / 25%); }
.viewer-button:disabled { opacity: .4; cursor: default; }
.viewer-button:focus-visible { outline: 2px solid #5eead4; outline-offset: 2px; }
@media print { .report-defect-photo-viewer { display: none !important; } }
</style>
