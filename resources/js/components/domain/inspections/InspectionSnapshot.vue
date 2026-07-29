<script setup>
defineProps({ snapshot: { type: Object, required: true }, version: { type: [String, Number], default: 1 } });
const sections = ['organization', 'client', 'unit', 'area', 'subarea', 'equipment'];
const labels = { organization: 'Organização', client: 'Cliente', unit: 'Unidade', area: 'Área', subarea: 'Subárea', equipment: 'Equipamento' };
</script>
<template>
    <div>
        <div class="mb-3 flex items-center justify-between"><p class="text-sm text-slate-500">Registro imutável do contexto no momento da criação.</p><span class="text-xs text-slate-400">Versão {{ version }}</span></div>
        <dl class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <div v-for="section in sections" :key="section" class="rounded-lg bg-slate-50 p-3">
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ labels[section] }}</dt>
                <dd class="mt-1 font-medium text-slate-900">{{ snapshot[section]?.tag || snapshot[section]?.name || '—' }}</dd>
                <dd v-if="snapshot[section]?.tag && snapshot[section]?.name" class="text-sm text-slate-500">{{ snapshot[section].name }}</dd>
                <dd v-if="snapshot[section]?.code" class="text-sm text-slate-500">Código {{ snapshot[section].code }}</dd>
            </div>
        </dl>
    </div>
</template>
