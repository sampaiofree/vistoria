<script setup>
import { computed, nextTick, ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import InspectionStatusBadge from '@/components/domain/inspections/InspectionStatusBadge.vue';
import UiIcon from '@/components/ui/UiIcon.vue';

const props = defineProps({
    entries: { type: Array, default: () => [] },
    emissionTypes: { type: Array, default: () => [] },
    users: { type: Array, default: () => [] },
    canManage: { type: Boolean, default: false },
    storeUrl: { type: String, default: '' },
});

const editingEntry = ref(null);
const formSection = ref(null);

const blankData = () => ({
    emission_type: 'C',
    revision_date: '',
    preparer_id: '',
    reviewer_id: '',
    approver_id: '',
    releaser_id: '',
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
        only: ['history_entries', 'revision_users'],
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
        preparer_id: entry.preparer_id,
        reviewer_id: entry.reviewer_id,
        approver_id: entry.approver_id,
        releaser_id: entry.releaser_id,
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

        <div v-if="entries.length" class="space-y-0 p-5 sm:p-6">
            <article
                v-for="(entry, index) in entries"
                :key="entry.key"
                class="relative grid grid-cols-[1.25rem_minmax(0,1fr)] gap-3 pb-5 last:pb-0 sm:gap-4 sm:pb-6"
            >
                <div class="relative flex justify-center">
                    <span
                        v-if="index < entries.length - 1"
                        class="absolute bottom-[-1.5rem] top-3 w-px bg-slate-200"
                        aria-hidden="true"
                    />
                    <span
                        class="relative mt-1.5 h-2.5 w-2.5 rounded-full ring-4"
                        :class="entry.source === 'system' ? 'bg-teal-500 ring-teal-50' : 'bg-slate-400 ring-slate-100'"
                        aria-hidden="true"
                    />
                </div>

                <div class="min-w-0 rounded-xl border p-4" :class="entry.is_current ? 'border-teal-200 bg-teal-50/60' : 'border-slate-200 bg-slate-50/60'">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span v-if="entry.revision_number" class="font-semibold text-slate-950">Rev. {{ entry.revision_number }}</span>
                                <span v-else class="font-semibold text-slate-700">Sem revisão oficial</span>
                                <span class="rounded-full px-2.5 py-1 text-[0.68rem] font-bold uppercase tracking-wide" :class="entry.source === 'system' ? 'bg-teal-100 text-teal-800' : 'bg-slate-200 text-slate-700'">
                                    {{ entry.source_label }}
                                </span>
                                <span v-if="entry.is_current" class="rounded-full bg-teal-600 px-2.5 py-1 text-[0.68rem] font-semibold uppercase tracking-wide text-white">
                                    Atual
                                </span>
                            </div>
                            <p class="mt-1 text-sm font-medium text-slate-800">{{ entry.description }}</p>
                            <p v-if="entry.inspection_number" class="mt-1 text-sm text-slate-500">
                                <Link :href="entry.show_url" class="font-semibold text-slate-700 transition hover:text-teal-700">{{ entry.inspection_number }}</Link>
                            </p>
                        </div>

                        <div class="flex flex-wrap items-center gap-2 lg:justify-end">
                            <InspectionStatusBadge v-if="entry.source === 'system'" :status="entry.status" />
                            <span v-else class="rounded-full bg-white px-2.5 py-1 text-xs font-semibold text-slate-700 ring-1 ring-slate-200">
                                T.E. {{ entry.emission_type }} · {{ entry.emission_type_label }}
                            </span>
                        </div>
                    </div>

                    <div class="mt-4 border-t border-slate-200/80 pt-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                            {{ entry.date_label }}
                            <span v-if="entry.date_is_provisional" class="font-normal normal-case tracking-normal text-amber-700"> · provisória</span>
                        </p>
                        <p class="mt-0.5 text-sm font-semibold text-slate-800">{{ entry.date ?? '—' }}</p>
                    </div>

                    <dl class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        <div v-for="role in [
                            { key: 'preparer', label: 'Preparador' },
                            { key: 'reviewer', label: 'Verificador' },
                            { key: 'approver', label: 'Aprovador' },
                            { key: 'releaser', label: 'Liberador' },
                        ]" :key="role.key">
                            <dt class="text-xs text-slate-500">{{ role.label }}</dt>
                            <dd class="mt-0.5 truncate text-sm font-medium text-slate-800">{{ entry.responsibles?.[role.key] ?? '—' }}</dd>
                        </div>
                    </dl>

                    <div v-if="entry.source === 'manual' && canManage" class="mt-4 flex justify-end gap-4 border-t border-slate-200 pt-3 text-sm">
                        <button type="button" class="font-semibold text-teal-700 hover:text-teal-900" @click="editEntry(entry)">Editar</button>
                        <button type="button" class="font-semibold text-rose-600 hover:text-rose-800" @click="removeEntry(entry)">Excluir</button>
                    </div>
                </div>
            </article>
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
                        { key: 'preparer_id', label: 'Preparador' },
                        { key: 'reviewer_id', label: 'Verificador' },
                        { key: 'approver_id', label: 'Aprovador' },
                        { key: 'releaser_id', label: 'Liberador' },
                    ]" :key="role.key" class="text-sm font-medium text-slate-700">
                        <span>{{ role.label }}</span>
                        <select v-model="form[role.key]" class="mt-1.5 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5" required>
                            <option value="" disabled>Selecione</option>
                            <option v-for="user in users" :key="user.id" :value="user.id">
                                {{ user.name }}{{ user.status !== 'active' ? ' (suspenso)' : '' }}
                            </option>
                        </select>
                        <span v-if="form.errors[role.key]" class="mt-1 block text-xs text-rose-600">{{ form.errors[role.key] }}</span>
                    </label>
                </div>

                <p v-if="form.errors.users" class="mt-3 text-xs text-rose-600">{{ form.errors.users }}</p>
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
