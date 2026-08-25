<script setup>
import { ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';

const props = defineProps({
    slot: { type: Object, required: true },
    equipmentLabel: { type: String, default: 'Vista geral' },
});

const input = ref(null);
const form = useForm({ file: null });

function chooseFile() {
    input.value?.click();
}

function upload(event) {
    const [file] = event.target.files || [];

    if (!file || !props.slot.upload_url) return;

    form.file = file;
    form.post(props.slot.upload_url, {
        forceFormData: true,
        preserveScroll: true,
        only: ['overview', 'flash'],
        onSuccess: () => form.reset('file'),
        onFinish: () => {
            if (input.value) input.value.value = '';
        },
    });
}

function remove() {
    if (props.slot.photo?.delete_url && window.confirm(`Remover a fotografia ${props.slot.number}?`)) {
        router.delete(props.slot.photo.delete_url, { preserveScroll: true, only: ['overview', 'flash'] });
    }
}

function refresh() {
    router.reload({ only: ['overview'], preserveScroll: true, preserveState: true });
}
</script>

<template>
    <article class="overflow-hidden rounded-md border border-slate-200 bg-white">
        <div class="flex aspect-[4/3] items-center justify-center bg-slate-100">
            <img
                v-if="slot.photo?.thumbnail_url"
                :src="slot.photo.thumbnail_url"
                :alt="`Fotografia ${slot.number} — ${equipmentLabel}`"
                class="h-full w-full object-contain"
            >
            <div v-else class="px-5 text-center text-sm text-slate-500">
                <p class="font-medium text-slate-700">Fotografia {{ slot.number }}</p>
                <p class="mt-1">{{ slot.photo?.status_label || 'Nenhuma imagem enviada' }}</p>
                <p v-if="slot.photo?.processing_error" class="mt-2 text-xs text-rose-700">{{ slot.photo.processing_error }}</p>
            </div>
        </div>

        <div class="border-t border-slate-200 p-3">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-slate-950">Foto {{ slot.number }}</p>
                    <p class="mt-0.5 truncate text-xs text-slate-500">{{ slot.photo?.name || 'Slot vazio' }}</p>
                </div>
                <span
                    v-if="slot.photo"
                    class="shrink-0 rounded px-2 py-1 text-[11px] font-medium"
                    :class="slot.photo.status === 'ready' ? 'bg-emerald-50 text-emerald-700' : slot.photo.status === 'failed' ? 'bg-rose-50 text-rose-700' : 'bg-amber-50 text-amber-700'"
                >
                    {{ slot.photo.status_label }}
                </span>
            </div>

            <p v-if="form.errors.file" class="mt-2 text-xs text-rose-700">{{ form.errors.file }}</p>
            <p v-if="slot.photo?.status === 'failed'" class="mt-2 text-xs font-medium text-rose-700">Escolha outra imagem para substituir este envio.</p>

            <div v-if="slot.upload_url" class="mt-3 flex flex-wrap gap-2">
                <input ref="input" type="file" accept="image/jpeg,image/png,image/webp" class="sr-only" @change="upload">
                <button
                    type="button"
                    class="rounded-md bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-700 disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="form.processing"
                    @click="chooseFile"
                >
                    {{ form.processing ? 'Enviando…' : (slot.photo ? 'Substituir' : 'Enviar foto') }}
                </button>
                <button v-if="slot.photo && ['pending', 'processing'].includes(slot.photo.status)" type="button" class="rounded-md border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50" @click="refresh">Atualizar</button>
                <button v-if="slot.photo?.delete_url" type="button" class="rounded-md border border-rose-200 px-3 py-2 text-xs font-semibold text-rose-700 hover:bg-rose-50" @click="remove">Remover</button>
            </div>
        </div>
    </article>
</template>
