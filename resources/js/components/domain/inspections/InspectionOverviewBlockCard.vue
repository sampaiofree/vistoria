<script setup>
import { ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import InspectionOverviewPhotoSlot from './InspectionOverviewPhotoSlot.vue';

const props = defineProps({
    block: { type: Object, required: true },
    equipmentLabel: { type: String, default: 'Vista geral' },
});

const editing = ref(false);
const form = useForm({
    comment: props.block.comment || '',
    recommendation: props.block.recommendation || '',
});

watch(
    () => [props.block.comment, props.block.recommendation],
    ([comment, recommendation]) => {
        if (!editing.value) {
            form.defaults({ comment: comment || '', recommendation: recommendation || '' });
            form.reset();
        }
    },
);

function edit() {
    form.clearErrors();
    form.defaults({
        comment: props.block.comment || '',
        recommendation: props.block.recommendation || '',
    });
    form.reset();
    editing.value = true;
}

function cancel() {
    form.reset();
    form.clearErrors();
    editing.value = false;
}

function submit() {
    if (!props.block.update_url) return;

    form.put(props.block.update_url, {
        preserveScroll: true,
        only: ['overview', 'flash'],
        onSuccess: () => { editing.value = false; },
    });
}
</script>

<template>
    <section class="rounded-md border border-slate-200 bg-white">
        <header class="flex flex-col gap-3 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="font-semibold text-slate-950">{{ block.title }}</h2>
                <p class="mt-1 text-sm text-slate-500">Duas imagens independentes, sem vínculo com mapas ou avarias.</p>
            </div>
            <button
                v-if="block.update_url && !editing"
                type="button"
                class="self-start rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 sm:self-auto"
                @click="edit"
            >
                Editar textos
            </button>
        </header>

        <div class="p-5">
            <div class="grid gap-4 md:grid-cols-2">
                <InspectionOverviewPhotoSlot
                    v-for="photoSlot in block.photos"
                    :key="photoSlot.slot"
                    :slot="photoSlot"
                    :equipment-label="equipmentLabel"
                />
            </div>

            <form v-if="editing" class="mt-5 space-y-4 border-t border-slate-200 pt-5" @submit.prevent="submit">
                <div>
                    <label :for="`overview-comment-${block.position}`" class="block text-sm font-semibold text-slate-700">Comentário</label>
                    <textarea
                        :id="`overview-comment-${block.position}`"
                        v-model="form.comment"
                        rows="4"
                        maxlength="600"
                        class="mt-2 w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-950 outline-none focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                    />
                    <div class="mt-1 flex justify-between gap-3 text-xs">
                        <p class="text-rose-700">{{ form.errors.comment }}</p>
                        <span class="ml-auto text-slate-400">{{ form.comment.length }}/600</span>
                    </div>
                </div>
                <div>
                    <label :for="`overview-recommendation-${block.position}`" class="block text-sm font-semibold text-slate-700">Recomendação</label>
                    <textarea
                        :id="`overview-recommendation-${block.position}`"
                        v-model="form.recommendation"
                        rows="4"
                        maxlength="600"
                        class="mt-2 w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-950 outline-none focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                    />
                    <div class="mt-1 flex justify-between gap-3 text-xs">
                        <p class="text-rose-700">{{ form.errors.recommendation }}</p>
                        <span class="ml-auto text-slate-400">{{ form.recommendation.length }}/600</span>
                    </div>
                </div>
                <div class="flex flex-wrap justify-end gap-2">
                    <button type="button" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50" @click="cancel">Cancelar</button>
                    <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700 disabled:cursor-not-allowed disabled:opacity-60" :disabled="form.processing">
                        {{ form.processing ? 'Salvando…' : 'Salvar' }}
                    </button>
                </div>
            </form>

            <dl v-else class="mt-5 grid gap-4 border-t border-slate-200 pt-5 lg:grid-cols-2">
                <div>
                    <dt class="text-sm font-semibold text-slate-700">Comentário</dt>
                    <dd class="mt-2 whitespace-pre-line text-sm leading-6 text-slate-700">{{ block.comment || 'Não informado' }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-semibold text-slate-700">Recomendação</dt>
                    <dd class="mt-2 whitespace-pre-line text-sm leading-6 text-slate-700">{{ block.recommendation || 'Não informada' }}</dd>
                </div>
            </dl>
        </div>
    </section>
</template>
