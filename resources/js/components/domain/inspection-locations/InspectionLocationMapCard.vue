<script setup>
import { Link, router } from '@inertiajs/vue3';
import InspectionLocationMapStatus from './InspectionLocationMapStatus.vue';

const props = defineProps({ map: { type: Object, required: true }, variant: { type: String, default: 'card' } });

function remove() {
    if (props.map.delete_url && window.confirm('Remover este mapa de localização?')) {
        router.delete(props.map.delete_url, { preserveScroll: true });
    }
}
</script>

<template>
    <article :class="variant === 'list' ? 'flex flex-col gap-4 rounded-2xl border border-slate-200 bg-white p-4 sm:flex-row sm:items-center' : 'overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm'">
        <div :class="variant === 'list' ? 'h-28 shrink-0 overflow-hidden rounded-xl bg-slate-100 sm:w-44' : 'aspect-[16/9] bg-slate-100'">
            <img v-if="map.background_url" :src="map.background_url" :alt="map.title" class="h-full w-full object-contain">
            <div v-else class="flex h-full items-center justify-center px-5 text-center text-sm text-slate-500">
                {{ map.processing_status === 'failed' ? 'Não foi possível gerar a imagem-base.' : 'Imagem-base ainda não disponível.' }}
            </div>
        </div>
        <div :class="variant === 'list' ? 'min-w-0 flex-1' : 'p-4 sm:p-5'">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <h3 class="truncate font-semibold text-slate-950">{{ map.category?.code ? map.category.code + ' · ' : '' }}{{ map.title }}</h3>
                    <p class="mt-1 text-xs text-slate-500">Ordem {{ map.position }} · {{ map.marker_count }} marcação(ões)</p>
                </div>
                <InspectionLocationMapStatus :status="map.processing_status" :label="map.processing_status_label" />
            </div>
            <p v-if="map.description" class="mt-3 line-clamp-2 text-sm leading-6 text-slate-600">{{ map.description }}</p>
            <p v-if="map.source.document" class="mt-3 text-xs text-slate-500">
                Documento: {{ map.source.document.title }} <span v-if="map.source.document.revision">· Rev. {{ map.source.document.revision }}</span>
            </p>
            <p v-if="map.processing_error" class="mt-3 rounded-xl bg-rose-50 p-3 text-xs text-rose-700">{{ map.processing_error }}</p>
            <p v-if="map.processing_status === 'failed'" class="mt-2 text-xs font-medium text-rose-700">Abra o gerenciamento e escolha outra imagem.</p>
            <div class="mt-4 flex flex-wrap gap-2">
                <Link v-if="map.editor_url" :href="map.editor_url" class="rounded-xl bg-teal-700 px-3.5 py-2 text-sm font-semibold text-white hover:bg-teal-800">Abrir editor</Link>
                <Link v-if="map.edit_url" :href="map.edit_url" class="rounded-xl bg-slate-950 px-3.5 py-2 text-sm font-semibold text-white hover:bg-teal-700">Gerenciar</Link>
                <button v-if="map.capabilities.delete" type="button" class="rounded-xl border border-rose-200 px-3.5 py-2 text-sm font-semibold text-rose-700" @click="remove">Remover</button>
            </div>
        </div>
    </article>
</template>
