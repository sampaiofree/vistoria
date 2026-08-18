<script setup>
import { computed } from 'vue';
import { markerPresentationStyle } from '@/components/domain/inspection-locations/inspectionLocationMarkerStyle.js';

const props = defineProps({
    marker: { type: Object, required: true },
    layout: { type: Object, required: true },
    viewWidth: { type: Number, required: true },
    viewHeight: { type: Number, required: true },
    interactive: { type: Boolean, default: false },
    selected: { type: Boolean, default: false },
});

defineEmits(['select']);

const presentation = computed(() => markerPresentationStyle(
    props.marker.style,
    props.marker.geometry,
    props.viewWidth,
));

function points(values = []) {
    return values.map(([x, y]) => `${x * props.viewWidth},${y * props.viewHeight}`).join(' ');
}
</script>

<template>
    <g
        :class="interactive ? 'cursor-pointer' : 'pointer-events-none'"
        @click.stop="interactive && $emit('select', marker)"
    >
        <title>{{ marker.defect_code || marker.photo_legend || 'Marcação de localização' }}</title>

        <template v-for="(shape, index) in (marker.geometry?.shapes || [])" :key="index">
            <circle
                v-if="shape.type === 'point'"
                :cx="shape.x * viewWidth"
                :cy="shape.y * viewHeight"
                r="11"
                :fill="presentation.color"
                :stroke="presentation.color"
                :stroke-width="selected ? Math.max(5, presentation.strokeWidth) : presentation.strokeWidth"
                :stroke-dasharray="presentation.dashArray"
                :opacity="presentation.opacity"
            />
            <rect
                v-else-if="shape.type === 'rectangle'"
                :x="shape.x * viewWidth"
                :y="shape.y * viewHeight"
                :width="shape.width * viewWidth"
                :height="shape.height * viewHeight"
                :fill="presentation.color"
                :fill-opacity="presentation.fillOpacity"
                :stroke="presentation.stroke"
                :stroke-width="presentation.strokeWidth"
                :stroke-dasharray="presentation.dashArray"
                :stroke-opacity="presentation.opacity"
            />
            <polygon
                v-else-if="shape.type === 'polygon'"
                :points="points(shape.points)"
                :fill="presentation.color"
                :fill-opacity="presentation.fillOpacity"
                :stroke="presentation.stroke"
                :stroke-width="presentation.strokeWidth"
                :stroke-dasharray="presentation.dashArray"
                :stroke-opacity="presentation.opacity"
            />
            <polyline
                v-else
                :points="points(shape.points)"
                fill="none"
                :stroke="presentation.color"
                :stroke-width="selected ? Math.max(5, presentation.strokeWidth) : presentation.strokeWidth"
                :stroke-dasharray="presentation.dashArray"
                :stroke-opacity="presentation.opacity"
            />

            <rect
                v-if="selected && !presentation.borderEnabled && shape.type === 'rectangle'"
                :x="shape.x * viewWidth"
                :y="shape.y * viewHeight"
                :width="shape.width * viewWidth"
                :height="shape.height * viewHeight"
                fill="none"
                stroke="#0F172A"
                stroke-width="2"
                stroke-dasharray="7 5"
                pointer-events="none"
            />
            <polygon
                v-else-if="selected && !presentation.borderEnabled && shape.type === 'polygon'"
                :points="points(shape.points)"
                fill="none"
                stroke="#0F172A"
                stroke-width="2"
                stroke-dasharray="7 5"
                pointer-events="none"
            />
        </template>

        <line
            :x1="layout.anchorX"
            :y1="layout.anchorY"
            :x2="layout.connectorX"
            :y2="layout.connectorY"
            :stroke="presentation.color"
            stroke-width="2"
            :stroke-opacity="presentation.opacity"
        />
        <rect
            :x="layout.x"
            :y="layout.y"
            :width="layout.width"
            :height="layout.height"
            rx="6"
            :fill="presentation.color"
            :stroke="presentation.color"
            :stroke-width="selected ? 2 : 1"
        />
        <text
            :x="layout.x + (layout.width / 2)"
            :y="layout.y + 21"
            text-anchor="middle"
            font-size="14"
            font-weight="700"
            fill="#000000"
        >
            {{ layout.photoLegend }}
        </text>
        <text
            v-if="layout.additionalLines.length"
            :x="layout.x + (layout.width / 2)"
            :y="layout.y + 39"
            text-anchor="middle"
            font-size="12"
            font-weight="500"
            fill="#000000"
        >
            <tspan
                v-for="(line, index) in layout.additionalLines"
                :key="index"
                :x="layout.x + (layout.width / 2)"
                :dy="index === 0 ? 0 : 16"
            >{{ line }}</tspan>
        </text>
    </g>
</template>
