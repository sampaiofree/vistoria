<script setup>
import GeneralAspectsNode from '@/components/domain/inspections/GeneralAspectsNode.vue';

defineProps({
    document: { type: Object, default: () => ({ type: 'doc', content: [] }) },
});
</script>

<template>
    <div class="general-aspects-content">
        <GeneralAspectsNode
            v-for="(node, index) in (document?.content || [])"
            :key="index"
            :node="node"
        />
    </div>
</template>

<style>
.general-aspects-content { color: inherit; overflow-wrap: anywhere; }
.general-aspects-text-block { padding: 0; }
.general-aspects-paragraph { font-size: 1em; font-weight: 400; }
.general-aspects-heading-1 { font-size: 1.45em; font-weight: 800; }
.general-aspects-heading-2 { font-size: 1.25em; font-weight: 750; }
.general-aspects-heading-3 { font-size: 1.1em; font-weight: 700; }
.general-aspects-list { margin: 4pt 0 4pt 7mm; padding-left: 5mm; }
.general-aspects-list .general-aspects-list { margin-top: 2pt; margin-bottom: 2pt; }
.general-aspects-bullet-list { list-style: disc; }
.general-aspects-bullet-list .general-aspects-bullet-list { list-style: circle; }
.general-aspects-ordered-list { list-style: decimal; }
.general-aspects-list > li { padding-left: 1.5mm; }
.general-aspects-list-continuation { list-style: none; }
.general-aspects-list > li > .general-aspects-paragraph { display: inline; margin-left: 0 !important; }
</style>
