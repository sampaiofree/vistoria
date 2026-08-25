<script setup>
import { computed, onBeforeUnmount, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { optimizeImageForUpload } from '@/lib/imageUploadOptimizer.js';

const props = defineProps({
    action: { type: String, required: true },
});

const maxFiles = 10;
const maxFileSize = 25 * 1024 * 1024;
const acceptedTypes = ['image/jpeg', 'image/png', 'image/webp'];
const acceptedTypesAttribute = acceptedTypes.join(',');
const cameraInput = ref(null);
const galleryInput = ref(null);
const queue = ref([]);
const selectionError = ref('');
const preparing = ref(false);
const processing = ref(false);
const currentPosition = ref(0);
const uploadTotal = ref(0);
let queueSequence = 0;
let disposed = false;

const preparingCount = computed(() => queue.value.filter((item) => item.status === 'preparing').length);
const waitingCount = computed(() => queue.value.filter((item) => item.status === 'waiting').length);
const sentCount = computed(() => queue.value.filter((item) => item.status === 'sent').length);
const failedCount = computed(() => queue.value.filter((item) => item.status === 'failed').length);
const busy = computed(() => preparing.value || processing.value);

function statusLabel(status) {
    return {
        preparing: 'Preparando imagem…',
        waiting: 'Aguardando',
        uploading: 'Enviando',
        sent: 'Enviada',
        failed: 'Falhou',
    }[status] ?? status;
}

function statusClasses(status) {
    return {
        preparing: 'bg-amber-100 text-amber-800',
        waiting: 'bg-slate-100 text-slate-700',
        uploading: 'bg-sky-100 text-sky-800',
        sent: 'bg-emerald-100 text-emerald-800',
        failed: 'bg-rose-100 text-rose-800',
    }[status] ?? 'bg-slate-100 text-slate-700';
}

function fileSize(size) {
    if (size < 1024 * 1024) {
        return `${Math.max(1, Math.round(size / 1024))} KB`;
    }

    return `${(size / (1024 * 1024)).toFixed(1)} MB`;
}

function formatValidationError(file) {
    if (file.type && !acceptedTypes.includes(file.type)) {
        return 'Formato não permitido. Use JPG, PNG ou WebP.';
    }

    return null;
}

function uploadValidationError(file) {
    const formatError = formatValidationError(file);

    if (formatError) return formatError;

    if (file.size > maxFileSize) {
        return 'O arquivo excede o limite de 25 MB.';
    }

    return null;
}

function releasePreview(item) {
    if (!item.previewUrl) return;

    URL.revokeObjectURL(item.previewUrl);
    item.previewUrl = null;
}

function clearQueue() {
    queue.value.forEach(releasePreview);
    queue.value = [];
}

function resetCompletedBatch() {
    if (queue.value.length > 0 && queue.value.every((item) => ['sent', 'failed'].includes(item.status))) {
        clearQueue();
    }
}

async function selectFiles(event, source) {
    if (busy.value) return;

    const files = Array.from(event.target.files ?? []);
    event.target.value = '';
    selectionError.value = '';

    if (files.length === 0) return;

    resetCompletedBatch();

    const remainingSlots = maxFiles - queue.value.length;

    if (files.length > remainingSlots) {
        selectionError.value = remainingSlots > 0
            ? `Você pode adicionar mais ${remainingSlots} ${remainingSlots === 1 ? 'imagem' : 'imagens'} neste envio.`
            : `Selecione no máximo ${maxFiles} imagens por envio.`;
        return;
    }

    const capturedAt = source === 'camera' ? new Date().toISOString() : null;
    const newItems = files.map((file) => {
        const error = formatValidationError(file);
        queueSequence += 1;

        return {
            id: `${Date.now()}-${queueSequence}`,
            file,
            originalSize: file.size,
            source,
            capturedAt,
            previewUrl: null,
            status: error ? 'failed' : 'preparing',
            error,
            warning: null,
        };
    });

    queue.value.push(...newItems);

    if (!newItems.some((item) => item.status === 'preparing')) return;

    preparing.value = true;

    try {
        for (const item of newItems) {
            if (item.status !== 'preparing') continue;

            const result = await optimizeImageForUpload(item.file);

            if (disposed) return;

            item.file = result.file;
            item.error = uploadValidationError(result.file);
            item.warning = result.warning && !item.error
                ? `${result.warning} O arquivo original será enviado.`
                : result.warning;
            item.status = item.error ? 'failed' : 'waiting';
            item.previewUrl = result.file.type.startsWith('image/') ? URL.createObjectURL(result.file) : null;
        }
    } finally {
        preparing.value = false;
    }
}

function chooseCamera() {
    cameraInput.value?.click();
}

function chooseGallery() {
    galleryInput.value?.click();
}

function removeItem(id) {
    if (busy.value) return;

    const index = queue.value.findIndex((item) => item.id === id);

    if (index === -1 || !['waiting', 'failed'].includes(queue.value[index].status)) return;

    releasePreview(queue.value[index]);
    queue.value.splice(index, 1);
    selectionError.value = '';
}

function submit() {
    if (busy.value || waitingCount.value === 0) return;

    processing.value = true;
    currentPosition.value = 0;
    uploadTotal.value = waitingCount.value;
    uploadNext();
}

function uploadNext() {
    const index = queue.value.findIndex((item) => item.status === 'waiting');

    if (index === -1) {
        processing.value = false;
        currentPosition.value = 0;
        uploadTotal.value = 0;
        queue.value.forEach(releasePreview);

        return;
    }

    const item = queue.value[index];
    item.status = 'uploading';
    item.error = null;
    currentPosition.value += 1;

    const payload = { file: item.file };

    if (item.capturedAt) {
        payload.captured_at = item.capturedAt;
    }

    router.post(props.action, payload, {
        forceFormData: true,
        preserveScroll: true,
        preserveState: true,
        only: ['evidence', 'capabilities', 'flash'],
        onSuccess: () => {
            item.status = 'sent';
        },
        onError: (errors) => {
            item.status = 'failed';
            item.error = errors.file ?? Object.values(errors)[0] ?? 'Não foi possível enviar esta imagem.';
        },
        onFinish: () => uploadNext(),
    });
}

onBeforeUnmount(() => {
    disposed = true;
    clearQueue();
});
</script>

<template>
    <form class="rounded-2xl border border-dashed border-teal-300 bg-teal-50/60 p-4" @submit.prevent="submit">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-sm font-semibold text-slate-950">Adicionar fotografia</p>
                <p class="mt-1 text-xs leading-5 text-slate-600">Selecione até 10 imagens JPG, PNG ou WebP. Antes do envio, elas serão reduzidas para até 2.048 px e aproximadamente 2 MB.</p>
            </div>
            <button type="submit" :disabled="busy || waitingCount === 0" class="inline-flex min-h-10 items-center rounded-xl bg-teal-700 px-4 py-2 text-sm font-semibold text-white transition hover:bg-teal-800 disabled:cursor-not-allowed disabled:opacity-50">
                {{ preparing ? `Preparando ${preparingCount} ${preparingCount === 1 ? 'imagem' : 'imagens'}…` : (processing ? `Enviando ${currentPosition}/${uploadTotal}…` : `Enviar ${waitingCount} ${waitingCount === 1 ? 'foto' : 'fotos'}`) }}
            </button>
        </div>
        <div class="mt-4">
            <span class="text-xs font-bold uppercase tracking-wider text-slate-600">Origem da fotografia</span>
            <div class="mt-1.5 flex flex-wrap gap-2">
                <input
                    ref="cameraInput"
                    type="file"
                    :accept="acceptedTypesAttribute"
                    capture="environment"
                    :disabled="busy"
                    class="sr-only"
                    @change="selectFiles($event, 'camera')"
                >
                <button
                    type="button"
                    :disabled="busy"
                    class="camera-action min-h-11 items-center rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-700 disabled:cursor-not-allowed disabled:opacity-50"
                    @click="chooseCamera"
                >
                    Tirar foto
                </button>

                <input
                    ref="galleryInput"
                    type="file"
                    multiple
                    :accept="acceptedTypesAttribute"
                    :disabled="busy"
                    class="sr-only"
                    @change="selectFiles($event, 'gallery')"
                >
                <button
                    type="button"
                    :disabled="busy"
                    class="inline-flex min-h-11 items-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:border-slate-400 hover:text-slate-950 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:opacity-50"
                    @click="chooseGallery"
                >
                    Escolher da galeria
                </button>
            </div>
            <p class="mt-2 text-xs leading-5 text-slate-500">No celular, “Tirar foto” solicita a câmera traseira quando o navegador oferece suporte.</p>
            <p v-if="selectionError" class="mt-1.5 text-xs font-medium text-rose-600">{{ selectionError }}</p>
        </div>

        <div v-if="queue.length" class="mt-4 space-y-2" aria-live="polite">
            <div v-for="item in queue" :key="item.id" class="flex items-center justify-between gap-3 rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm">
                <div class="flex min-w-0 items-center gap-3">
                    <img v-if="item.previewUrl" :src="item.previewUrl" alt="Prévia da fotografia selecionada" class="h-16 w-20 shrink-0 rounded-lg bg-slate-100 object-cover">
                    <div v-else class="flex h-16 w-20 shrink-0 items-center justify-center rounded-lg bg-slate-100 px-2 text-center text-[10px] font-semibold uppercase text-slate-500">
                        {{ item.source === 'camera' ? 'Câmera' : 'Imagem' }}
                    </div>
                    <div class="min-w-0">
                        <p class="truncate font-medium text-slate-800">{{ item.file.name }}</p>
                        <p class="mt-0.5 text-xs text-slate-500">
                            <template v-if="item.originalSize !== item.file.size">{{ fileSize(item.originalSize) }} → {{ fileSize(item.file.size) }}</template>
                            <template v-else>{{ fileSize(item.file.size) }}</template>
                            · {{ item.source === 'camera' ? 'Câmera' : 'Galeria' }}
                        </p>
                        <p v-if="item.warning" class="mt-1 text-xs font-medium text-amber-700">{{ item.warning }}</p>
                        <p v-if="item.error" class="mt-1 text-xs font-medium text-rose-600">{{ item.error }}</p>
                    </div>
                </div>
                <div class="flex shrink-0 flex-col items-end gap-2">
                    <span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="statusClasses(item.status)">{{ statusLabel(item.status) }}</span>
                    <button
                        v-if="!busy && ['waiting', 'failed'].includes(item.status)"
                        type="button"
                        class="text-xs font-semibold text-rose-700 hover:text-rose-800"
                        @click="removeItem(item.id)"
                    >
                        Remover
                    </button>
                </div>
            </div>
        </div>

        <p v-if="!processing && queue.length && (sentCount || failedCount)" class="mt-3 text-xs text-slate-600">
            {{ sentCount }} enviada(s) com sucesso<span v-if="failedCount">; {{ failedCount }} com falha</span>.
        </p>
    </form>
</template>

<style scoped>
.camera-action {
    display: none;
}

@media (hover: none) and (pointer: coarse) {
    .camera-action {
        display: inline-flex;
    }
}
</style>
