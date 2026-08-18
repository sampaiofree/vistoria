<script setup>
import { computed } from 'vue';
import GeneralAspectsInlineNode from '@/components/domain/inspections/GeneralAspectsInlineNode.vue';

defineOptions({ name: 'GeneralAspectsNode' });

const props = defineProps({
    node: { type: Object, required: true },
});

const textTag = computed(() => {
    if (props.node.type !== 'heading') return 'p';

    const level = Number(props.node.attrs?.level || 1);
    return `h${Math.min(3, Math.max(1, level))}`;
});

const layoutStyle = computed(() => ({
    textAlign: ['left', 'center', 'right', 'justify'].includes(props.node.attrs?.textAlign)
        ? props.node.attrs.textAlign
        : 'left',
    lineHeight: [1, 1.15, 1.5, 2].includes(Number(props.node.attrs?.lineHeight))
        ? Number(props.node.attrs.lineHeight)
        : 1.15,
    marginTop: `${[0, 4, 8, 12].includes(Number(props.node.attrs?.spaceBefore)) ? Number(props.node.attrs.spaceBefore) : 0}pt`,
    marginBottom: `${[0, 4, 8, 12].includes(Number(props.node.attrs?.spaceAfter)) ? Number(props.node.attrs.spaceAfter) : 0}pt`,
    marginLeft: `${[0, 10, 20, 30].includes(Number(props.node.attrs?.indent)) ? Number(props.node.attrs.indent) : 0}mm`,
}));
</script>

<template>
    <component
        :is="textTag"
        v-if="node.type === 'paragraph' || node.type === 'heading'"
        class="general-aspects-text-block"
        :class="node.type === 'heading' ? `general-aspects-heading-${node.attrs?.level || 1}` : 'general-aspects-paragraph'"
        :style="layoutStyle"
    >
        <span v-if="node.reportNumber" class="general-aspects-report-number">{{ node.reportNumber }} </span>
        <GeneralAspectsInlineNode
            v-for="(child, index) in (node.content || [])"
            :key="index"
            :node="child"
        />
        <br v-if="!(node.content || []).length">
    </component>

    <ul v-else-if="node.type === 'bulletList'" class="general-aspects-list general-aspects-bullet-list" :class="{ 'general-aspects-list-continuation': node.reportContinuation }">
        <li v-for="(item, index) in (node.content || [])" :key="index">
            <GeneralAspectsNode v-for="(child, childIndex) in (item.content || [])" :key="childIndex" :node="child" />
        </li>
    </ul>

    <ol
        v-else-if="node.type === 'orderedList'"
        class="general-aspects-list general-aspects-ordered-list"
        :class="{ 'general-aspects-list-continuation': node.reportContinuation }"
        :start="node.attrs?.start || 1"
    >
        <li v-for="(item, index) in (node.content || [])" :key="index">
            <GeneralAspectsNode v-for="(child, childIndex) in (item.content || [])" :key="childIndex" :node="child" />
        </li>
    </ol>
</template>
