<script setup>
defineProps({
    form: { type: Object, required: true },
    submitLabel: { type: String, default: 'Salvar' },
    cancelUrl: { type: String, required: true },
    activationImpact: { type: Object, default: null },
    initiallyRequiresLocationMap: { type: Boolean, default: false },
});

defineEmits(['submit']);
</script>

<template>
    <form class="space-y-5" @submit.prevent="$emit('submit')">
        <div class="grid gap-4 md:grid-cols-2">
            <label class="block md:col-span-2">
                <span class="text-sm font-semibold text-slate-700">Nome</span>
                <input v-model="form.name" type="text" maxlength="120" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                <p v-if="form.errors.name" class="mt-1 text-xs text-rose-600">{{ form.errors.name }}</p>
            </label>
            <label class="block">
                <span class="text-sm font-semibold text-slate-700">Código</span>
                <input v-model="form.code" type="text" maxlength="30" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm uppercase">
                <p class="mt-1 text-xs text-slate-500">Somente letras e números, sem espaços.</p>
                <p v-if="form.errors.code" class="mt-1 text-xs text-rose-600">{{ form.errors.code }}</p>
            </label>
            <label class="block">
                <span class="text-sm font-semibold text-slate-700">Posição</span>
                <input v-model.number="form.position" type="number" min="1" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                <p v-if="form.errors.position" class="mt-1 text-xs text-rose-600">{{ form.errors.position }}</p>
            </label>
            <label class="block md:col-span-2">
                <span class="text-sm font-semibold text-slate-700">Descrição</span>
                <textarea v-model="form.description" rows="4" maxlength="5000" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"></textarea>
                <p v-if="form.errors.description" class="mt-1 text-xs text-rose-600">{{ form.errors.description }}</p>
            </label>
            <div class="md:col-span-2 rounded-xl border border-slate-200 bg-slate-50 p-4">
                <label class="flex items-start gap-3">
                    <input v-model="form.requires_location_map" type="checkbox" class="mt-0.5 h-4 w-4 rounded border-slate-300 text-teal-700 focus:ring-teal-600">
                    <span>
                        <span class="block text-sm font-semibold text-slate-800">Exigir mapa de localização</span>
                        <span class="mt-1 block text-xs leading-5 text-slate-500">A inspeção só poderá seguir para verificação quando as avarias desta categoria estiverem localizadas em mapas prontos.</span>
                    </span>
                </label>
                <p v-if="form.errors.requires_location_map" class="mt-2 text-xs text-rose-600">{{ form.errors.requires_location_map }}</p>

                <div v-if="form.requires_location_map && !initiallyRequiresLocationMap" class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950">
                    <p class="font-semibold">Impacto nas inspeções em andamento</p>
                    <p class="mt-1 leading-6">
                        {{ activationImpact?.open_inspections ?? 0 }} inspeção(ões) aberta(s) e
                        {{ activationImpact?.unlocated_assessments ?? 0 }} avaliação(ões) ainda sem marcação serão alcançadas por esta regra.
                    </p>
                    <label class="mt-3 flex items-start gap-3">
                        <input v-model="form.confirm_location_map_requirement" type="checkbox" class="mt-0.5 h-4 w-4 rounded border-amber-400 text-amber-700 focus:ring-amber-600">
                        <span class="text-xs font-medium leading-5">Confirmo a ativação e entendo que ela pode bloquear o envio dessas inspeções para verificação.</span>
                    </label>
                    <p v-if="form.errors.confirm_location_map_requirement" class="mt-2 text-xs text-rose-700">{{ form.errors.confirm_location_map_requirement }}</p>
                </div>
            </div>
        </div>
        <div class="flex flex-wrap justify-end gap-3">
            <a :href="cancelUrl" class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700">Cancelar</a>
            <button type="submit" :disabled="form.processing" class="rounded-xl bg-teal-700 px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-60">{{ submitLabel }}</button>
        </div>
    </form>
</template>
