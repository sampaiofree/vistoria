<script setup>
import { nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { Link } from '@inertiajs/vue3';
import DefectConditionBadge from './DefectConditionBadge.vue';

const props = defineProps({
    open: { type: Boolean, default: false },
    history: { type: Array, default: () => [] },
    defectCode: { type: String, default: 'Avaria' },
});

const emit = defineEmits(['close']);
const dialog = ref(null);
let previousFocus = null;

function close() {
    emit('close');
}

function onKeydown(event) {
    if (event.key === 'Escape') close();
}

function formatQuantity(value) {
    const number = Number(value);
    return Number.isFinite(number)
        ? new Intl.NumberFormat('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(number)
        : '—';
}

watch(() => props.open, async (open) => {
    if (typeof document === 'undefined') return;

    if (open) {
        previousFocus = document.activeElement;
        document.body.style.overflow = 'hidden';
        document.addEventListener('keydown', onKeydown);
        await nextTick();
        dialog.value?.focus();
        return;
    }

    document.body.style.overflow = '';
    document.removeEventListener('keydown', onKeydown);
    previousFocus?.focus?.();
});

onBeforeUnmount(() => {
    if (typeof document === 'undefined') return;
    document.body.style.overflow = '';
    document.removeEventListener('keydown', onKeydown);
});
</script>

<template>
    <Teleport to="body">
        <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 p-4" @click.self="close">
            <section ref="dialog" role="dialog" aria-modal="true" :aria-label="`Histórico da avaria ${defectCode}`" tabindex="-1" class="max-h-[94vh] w-full max-w-5xl overflow-y-auto rounded-3xl bg-white shadow-2xl outline-none">
                <header class="sticky top-0 z-10 flex items-center justify-between gap-4 border-b border-slate-200 bg-white px-5 py-4 sm:px-6">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.16em] text-teal-700">Histórico da avaria</p>
                        <h2 class="mt-1 text-xl font-semibold text-slate-950">{{ defectCode }}</h2>
                    </div>
                    <button type="button" class="rounded-xl border border-slate-300 px-3.5 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50" @click="close">Fechar</button>
                </header>

                <div class="space-y-5 p-5 sm:p-6">
                    <article v-for="item in history" :key="item.id" class="rounded-2xl border border-slate-200 p-5">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <Link :href="item.inspection.show_url" class="font-semibold text-teal-700 hover:text-teal-800">{{ item.inspection.number || 'Inspeção' }}</Link>
                                <p class="mt-1 text-xs text-slate-500">{{ item.assessed_at || 'Data não informada' }}</p>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                <DefectConditionBadge :condition="item.condition" />
                                <span class="rounded border border-slate-200 bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">{{ item.classification?.code || 'Sem classificação' }}</span>
                            </div>
                        </div>

                        <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-3">
                            <div v-if="item.tel" class="rounded-xl bg-slate-50 p-3"><dt class="text-xs font-semibold uppercase text-slate-500">TEL</dt><dd class="mt-1 font-medium text-slate-900">Impacto {{ item.tel.impact?.score ?? '—' }} · Risco {{ item.tel.fall_risk?.score ?? '—' }} · Pontuação {{ item.tel.score ?? '—' }}</dd></div>
                            <div v-else class="rounded-xl bg-slate-50 p-3"><dt class="text-xs font-semibold uppercase text-slate-500">GUT</dt><dd class="mt-1 font-medium text-slate-900">G {{ item.gut?.gravity ?? '—' }} · U {{ item.gut?.urgency ?? '—' }} · T {{ item.gut?.trend ?? '—' }}</dd></div>
                            <div v-if="!item.tel" class="rounded-xl bg-slate-50 p-3"><dt class="text-xs font-semibold uppercase text-slate-500">Quantidade</dt><dd class="mt-1 font-medium text-slate-900">{{ item.quantity ? `${formatQuantity(item.quantity.value)} ${item.quantity.unit_symbol}` : '—' }}</dd></div>
                            <div class="rounded-xl bg-slate-50 p-3"><dt class="text-xs font-semibold uppercase text-slate-500">Localização</dt><dd class="mt-1 font-medium text-slate-900">{{ item.location_description || '—' }}</dd></div>
                        </dl>

                        <div v-if="item.quantity?.snapshot?.items?.length" class="mt-4 rounded-xl border border-slate-200 p-3">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Itens do quantitativo publicado</p>
                            <ol class="mt-2 divide-y divide-slate-100">
                                <li v-for="quantityItem in item.quantity.snapshot.items" :key="quantityItem.position" class="flex items-center justify-between gap-3 py-2 text-sm">
                                    <span class="text-slate-700">Item {{ quantityItem.position }} · {{ quantityItem.description || quantityItem.element?.label || 'Sem descrição' }}</span>
                                    <strong class="shrink-0 text-slate-950">{{ formatQuantity(quantityItem.total) }} {{ item.quantity.unit_symbol }}</strong>
                                </li>
                            </ol>
                        </div>

                        <div class="mt-4 space-y-3 text-sm text-slate-700">
                            <p v-if="item.reason"><strong class="text-slate-900">Justificativa:</strong> {{ item.reason }}</p>
                            <p v-if="item.comment" class="whitespace-pre-line"><strong class="text-slate-900">Comentário:</strong> {{ item.comment }}</p>
                            <p v-if="item.recommendation" class="whitespace-pre-line"><strong class="text-slate-900">Recomendação:</strong> {{ item.recommendation }}</p>
                            <p v-if="item.internal_notes" class="whitespace-pre-line rounded-xl border border-amber-200 bg-amber-50 p-3 text-amber-950"><strong>Observações internas:</strong> {{ item.internal_notes }}</p>
                        </div>

                        <div v-if="item.photos?.length" class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                            <a v-for="photo in item.photos" :key="photo.id" :href="photo.url || undefined" target="_blank" rel="noopener" class="overflow-hidden rounded-xl border border-slate-200 bg-slate-100">
                                <img v-if="photo.thumbnail_url" :src="photo.thumbnail_url" :alt="photo.caption || photo.title" loading="lazy" class="aspect-[4/3] w-full object-cover">
                                <div v-else class="flex aspect-[4/3] items-center justify-center p-3 text-center text-xs text-slate-500">{{ photo.status === 'ready' ? 'Imagem indisponível' : 'Processamento não concluído' }}</div>
                                <p class="truncate px-3 py-2 text-xs text-slate-600">{{ photo.caption || photo.title }}</p>
                            </a>
                        </div>
                    </article>

                    <p v-if="!history.length" class="rounded-2xl border border-dashed border-slate-300 p-6 text-center text-sm text-slate-500">Nenhuma avaliação anterior disponível.</p>
                </div>
            </section>
        </div>
    </Teleport>
</template>
