<script setup>
import { onMounted, onUnmounted, ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/components/ui/AppLayout.vue';
import InspectionLocationMapForm from '@/components/domain/inspection-locations/InspectionLocationMapForm.vue';
import InspectionLocationMapStatus from '@/components/domain/inspection-locations/InspectionLocationMapStatus.vue';

const props = defineProps({
    map: { type: Object, required: true },
    inspection: { type: Object, required: true },
    update_url: { type: String, required: true },
    source_url: { type: String, required: true },
    delete_url: { type: String, required: true },
    cancel_url: { type: String, required: true },
});
const form = useForm({ title: props.map.title, description: props.map.description || '', position: props.map.position, lock_version: props.map.lock_version });
const source = useForm({ file: null, lock_version: props.map.lock_version });
const previewUrl = ref(null);
let processingPoll = null;

function clearPreview() {
    if (previewUrl.value) URL.revokeObjectURL(previewUrl.value);
    previewUrl.value = null;
}

function selectFile(event) {
    clearPreview();
    source.file = event.target.files[0] || null;
    source.clearErrors();
    if (source.file) previewUrl.value = URL.createObjectURL(source.file);
}

function pollProcessingStatus() {
    if (processingPoll) window.clearInterval(processingPoll);
    processingPoll = null;
    if (!['pending', 'processing'].includes(props.map.processing_status)) return;
    processingPoll = window.setInterval(() => {
        router.reload({ only: ['map'], preserveScroll: true, preserveState: true });
    }, 2500);
}

watch(() => props.map.lock_version, (version) => {
    form.lock_version = version;
    source.lock_version = version;
});
watch(() => props.map.processing_status, pollProcessingStatus);
function update() { form.put(props.update_url); }
function upload() {
    source.post(props.source_url, {
        forceFormData: true,
        onSuccess: () => {
            clearPreview();
            source.reset('file');
        },
    });
}
function remove() {
    const suffix = props.map.marker_count ? ` e suas ${props.map.marker_count} marcação(ões)` : '';
    if (window.confirm(`Remover este mapa${suffix}?`)) router.delete(props.delete_url);
}
onMounted(pollProcessingStatus);
onUnmounted(() => {
    if (processingPoll) window.clearInterval(processingPoll);
    clearPreview();
});
</script>

<template>
    <AppLayout :title="map.title" :subtitle="`${inspection.equipment.tag} · ${map.category.code} — ${map.category.name}`" wide>
        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_24rem]">
            <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                    <h2 class="text-lg font-semibold text-slate-950">Dados do mapa</h2>
                    <InspectionLocationMapStatus :status="map.processing_status" />
                </div>
                <InspectionLocationMapForm :form="form" :cancel-url="cancel_url" editing @submit="update" />
            </section>

            <aside class="space-y-5">
                <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                    <div class="aspect-[4/3] bg-slate-100">
                        <img v-if="map.background_url" :src="map.background_url" :alt="map.title" class="h-full w-full object-contain">
                        <div v-else class="flex h-full items-center justify-center p-5 text-center text-sm text-slate-500">Imagem-base ainda indisponível.</div>
                    </div>
                    <div class="p-5">
                        <p v-if="map.processing_error" class="mt-3 rounded-xl bg-rose-50 p-3 text-xs text-rose-700">{{ map.processing_error }}</p>
                        <p v-if="map.processing_status === 'failed'" class="mt-3 text-xs font-medium text-rose-700">Escolha outra imagem no formulário abaixo.</p>
                        <a v-if="map.editor_url" :href="map.editor_url" class="mt-3 block rounded-xl bg-slate-950 px-4 py-2.5 text-center text-sm font-semibold text-white hover:bg-teal-700">Abrir editor de marcações</a>
                    </div>
                </section>

                <form class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm" @submit.prevent="upload">
                    <h2 class="font-semibold text-slate-950">Imagem do mapa</h2>
                    <p class="mt-1 text-xs leading-5 text-slate-500">Envie uma imagem PNG, JPEG ou WEBP, de até 50 MB. A imagem será processada automaticamente.</p>
                    <input type="file" accept="image/png,image/jpeg,image/webp" class="mt-4 block w-full text-sm" @change="selectFile">
                    <img v-if="previewUrl" :src="previewUrl" alt="Prévia da nova imagem do mapa" class="mt-4 max-h-48 w-full rounded-xl border border-slate-200 object-contain">
                    <div v-if="Object.keys(source.errors).length" class="mt-3 rounded-xl bg-rose-50 p-3 text-xs leading-5 text-rose-700" role="alert" aria-live="assertive">
                        <p v-for="(message, key) in source.errors" :key="key">{{ Array.isArray(message) ? message.join(' ') : message }}</p>
                    </div>
                    <p v-if="map.processing_status === 'pending' || map.processing_status === 'processing'" class="mt-3 rounded-xl bg-amber-50 p-3 text-xs leading-5 text-amber-800" role="status">A nova imagem está sendo processada. Esta página será atualizada automaticamente.</p>
                    <p v-if="map.processing_status === 'failed' && map.processing_error" class="mt-3 rounded-xl bg-rose-50 p-3 text-xs leading-5 text-rose-700" role="alert">{{ map.processing_error }}</p>
                    <button type="submit" :disabled="!source.file || source.processing" class="mt-4 w-full rounded-xl bg-teal-700 px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-50">{{ source.processing ? 'Enviando…' : 'Substituir imagem' }}</button>
                </form>

                <button type="button" class="w-full rounded-xl border border-rose-200 bg-white px-4 py-2.5 text-sm font-semibold text-rose-700" @click="remove">Remover mapa</button>
            </aside>
        </div>
    </AppLayout>
</template>
