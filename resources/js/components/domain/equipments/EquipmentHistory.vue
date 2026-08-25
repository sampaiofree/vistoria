<script setup>
import { computed, nextTick, ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import InspectionStatusBadge from '@/components/domain/inspections/InspectionStatusBadge.vue';
import UiIcon from '@/components/ui/UiIcon.vue';

const props = defineProps({
    entries: { type: Array, default: () => [] },
    emissionTypes: { type: Array, default: () => [] },
    canManage: { type: Boolean, default: false },
    storeUrl: { type: String, default: '' },
});

const editingEntry = ref(null);
const formSection = ref(null);

const blankData = () => ({
    emission_type: 'C',
    revision_date: '',
    preparer_name: '',
    reviewer_name: '',
    approver_name: '',
    releaser_name: '',
});

const form = useForm(blankData());
const isEditing = computed(() => editingEntry.value !== null);

function closeForm() {
    if (formSection.value) {
        formSection.value.open = false;
    }
}

function resetForm() {
    editingEntry.value = null;
    form.defaults(blankData());
    form.reset();
    form.clearErrors();
    closeForm();
}

function reloadHistory() {
    router.reload({
        only: ['history_entries'],
        preserveScroll: true,
    });
}

function openForm() {
    nextTick(() => {
        if (formSection.value) {
            formSection.value.open = true;
            formSection.value.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    });
}

function editEntry(entry) {
    editingEntry.value = entry;
    form.defaults({
        emission_type: entry.emission_type,
        revision_date: entry.revision_date_input,
        preparer_name: entry.preparer_name,
        reviewer_name: entry.reviewer_name,
        approver_name: entry.approver_name,
        releaser_name: entry.releaser_name,
    });
    form.reset();
    form.clearErrors();
    openForm();
}

function submit() {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            resetForm();
            reloadHistory();
        },
    };

    if (isEditing.value) {
        form.put(editingEntry.value.update_url, options);
        return;
    }

    form.post(props.storeUrl, options);
}

function removeEntry(entry) {
    if (!window.confirm(`Remover a revisão ${entry.revision_number}?`)) {
        return;
    }

    if (editingEntry.value?.public_id === entry.public_id) {
        resetForm();
    }

    router.delete(entry.destroy_url, {
        preserveScroll: true,
        onSuccess: reloadHistory,
    });
}
</script>

<template>
    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-3 border-b border-slate-200 px-5 py-5 sm:flex-row sm:items-start sm:justify-between sm:px-6">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-teal-700">Rastreabilidade</p>
                <h2 class="mt-1 text-lg font-semibold text-slate-950">Histórico do equipamento</h2>
                <p class="mt-1 text-sm text-slate-500">Inspeções do sistema e revisões anteriores em uma única cronologia.</p>
            </div>
            <span class="rounded-full bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-600">
                {{ entries.length }} registro(s)
            </span>
        </div>

        <div v-if="entries.length" class="overflow-x-auto">
            <table class="w-full min-w-[1200px] text-left text-sm">
                <thead class="sticky top-0 z-10 bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500 shadow-[0_1px_0_rgb(226_232_240)]">
                    <tr>
                        <th scope="col" class="whitespace-nowrap px-5 py-3 sm:px-6">Revisão</th>
                        <th scope="col" class="whitespace-nowrap px-3 py-3">Origem</th>
                        <th scope="col" class="whitespace-nowrap px-3 py-3">Tipo</th>
                        <th scope="col" class="whitespace-nowrap px-3 py-3">Status</th>
                        <th scope="col" class="whitespace-nowrap px-3 py-3">Data</th>
                        <th scope="col" class="whitespace-nowrap px-3 py-3">Preparador</th>
                        <th scope="col" class="whitespace-nowrap px-3 py-3">Verificador</th>
                        <th scope="col" class="whitespace-nowrap px-3 py-3">Aprovador</th>
                        <th scope="col" class="whitespace-nowrap px-3 py-3">Liberador</th>
                        <th scope="col" class="px-5 py-3 text-right sm:px-6"><span class="sr-only">Ações</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    <tr v-for="entry in entries" :key="entry.key" class="transition hover:bg-slate-50/80" :class="entry.is_current ? 'bg-teal-50/60' : 'bg-white'">
                        <td class="px-5 py-3 align-top sm:px-6">
                            <div class="flex items-center gap-2">
                                <span v-if="entry.revision_number" class="whitespace-nowrap font-semibold text-slate-950">Rev. {{ entry.revision_number }}</span>
                                <span v-else class="whitespace-nowrap font-medium text-slate-600">Sem revisão</span>
                                <span v-if="entry.is_current" class="rounded-full bg-teal-600 px-2 py-0.5 text-[0.65rem] font-semibold uppercase tracking-wide text-white">Atual</span>
                            </div>
                            <p class="mt-0.5 text-xs text-slate-500">{{ entry.description }}</p>
                            <Link v-if="entry.inspection_number" :href="entry.show_url" class="mt-0.5 inline-block text-xs font-semibold text-teal-700 hover:text-teal-900">
                                {{ entry.inspection_number }}
                            </Link>
                        </td>
                        <td class="px-3 py-3 align-top">
                            <span class="rounded-full px-2 py-0.5 text-[0.65rem] font-bold uppercase tracking-wide" :class="entry.source === 'system' ? 'bg-teal-100 text-teal-800' : 'bg-slate-200 text-slate-700'">
                                {{ entry.source_label }}
                            </span>
                        </td>
                        <td class="px-3 py-3 align-top">
                            <span v-if="entry.emission_type" class="whitespace-nowrap rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-700" :title="entry.emission_type_label">
                                T.E. {{ entry.emission_type }}
                            </span>
                            <span v-else class="text-slate-400">—</span>
                        </td>
                        <td class="px-3 py-3 align-top">
                            <InspectionStatusBadge v-if="entry.source === 'system'" :status="entry.status" />
                            <span v-else class="whitespace-nowrap rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-800">
                                Liberada
                            </span>
                        </td>
                        <td class="px-3 py-3 align-top whitespace-nowrap">
                            <p class="font-medium text-slate-800">{{ entry.date ?? '—' }}</p>
                            <p v-if="entry.date_is_provisional" class="mt-0.5 text-xs text-amber-700">Provisória</p>
                        </td>
                        <td v-for="role in [
                            { key: 'preparer', label: 'Preparador' },
                            { key: 'reviewer', label: 'Verificador' },
                            { key: 'approver', label: 'Aprovador' },
                            { key: 'releaser', label: 'Liberador' },
                        ]" :key="role.key" class="max-w-40 px-3 py-3 align-top">
                            <span class="block truncate font-medium text-slate-700" :title="entry.responsibles?.[role.key] ?? '—'">{{ entry.responsibles?.[role.key] ?? '—' }}</span>
                        </td>
                        <td class="px-5 py-3 text-right align-top whitespace-nowrap sm:px-6">
                            <div v-if="entry.source === 'manual' && canManage" class="flex justify-end gap-3 text-xs">
                                <button type="button" class="font-semibold text-teal-700 hover:text-teal-900" @click="editEntry(entry)">Editar</button>
                                <button type="button" class="font-semibold text-rose-600 hover:text-rose-800" @click="removeEntry(entry)">Excluir</button>
                            </div>
                            <span v-else class="text-slate-300">—</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-else class="p-6 text-center">
            <p class="text-sm font-medium text-slate-700">Nenhum registro histórico cadastrado.</p>
            <p class="mt-1 text-xs text-slate-500">A cronologia aparecerá após a primeira inspeção ou revisão.</p>
        </div>

        <details v-if="canManage" ref="formSection" class="group border-t border-slate-200 bg-slate-50/70">
            <summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-5 py-4 transition hover:bg-slate-100 sm:px-6 [&::-webkit-details-marker]:hidden">
                <span>
                    <span class="block font-semibold text-slate-900">{{ isEditing ? `Editar revisão ${editingEntry.revision_number}` : 'Adicionar histórico manual' }}</span>
                    <span class="mt-0.5 block text-sm text-slate-500">A numeração será calculada pela cronologia completa.</span>
                </span>
                <UiIcon name="chevron-right" class="h-5 w-5 shrink-0 text-slate-500 transition group-open:rotate-90" />
            </summary>

            <form class="border-t border-slate-200 px-5 py-5 sm:px-6 sm:py-6" @submit.prevent="submit">
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <label class="text-sm font-medium text-slate-700">
                        <span>Tipo de emissão</span>
                        <select v-model="form.emission_type" class="mt-1.5 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5" required>
                            <option v-for="option in emissionTypes" :key="option.value" :value="option.value">{{ option.value }} — {{ option.label }}</option>
                        </select>
                        <span v-if="form.errors.emission_type" class="mt-1 block text-xs text-rose-600">{{ form.errors.emission_type }}</span>
                    </label>
                    <label class="text-sm font-medium text-slate-700">
                        <span>Data da revisão</span>
                        <input v-model="form.revision_date" type="date" class="mt-1.5 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5" required>
                        <span v-if="form.errors.revision_date" class="mt-1 block text-xs text-rose-600">{{ form.errors.revision_date }}</span>
                    </label>
                    <label v-for="role in [
                        { key: 'preparer_name', label: 'Preparador' },
                        { key: 'reviewer_name', label: 'Verificador' },
                        { key: 'approver_name', label: 'Aprovador' },
                        { key: 'releaser_name', label: 'Liberador' },
                    ]" :key="role.key" class="text-sm font-medium text-slate-700">
                        <span>{{ role.label }}</span>
                        <input v-model="form[role.key]" type="text" maxlength="180" class="mt-1.5 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5" required>
                        <span v-if="form.errors[role.key]" class="mt-1 block text-xs text-rose-600">{{ form.errors[role.key] }}</span>
                    </label>
                </div>

                <div class="mt-5 flex flex-wrap justify-end gap-3">
                    <button type="button" class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:border-slate-400" @click="resetForm">Cancelar</button>
                    <button type="submit" class="rounded-xl bg-teal-700 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-teal-800" :disabled="form.processing">
                        {{ form.processing ? 'Salvando…' : (isEditing ? 'Salvar alterações' : 'Adicionar revisão') }}
                    </button>
                </div>
            </form>
        </details>
    </section>
</template>
