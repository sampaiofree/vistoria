<script setup>
import { computed, ref } from 'vue';
import InspectionLocationMarkerOverlay from '@/components/domain/inspection-locations/InspectionLocationMarkerOverlay.vue';
import { layoutMarkerLegends } from '@/components/domain/inspection-locations/inspectionLocationMarkerPresentation.js';
import { markerPresentationStyle } from '@/components/domain/inspection-locations/inspectionLocationMarkerStyle.js';

const props = defineProps({
    backgroundUrl: { type: String, required: true },
    backgroundWidth: { type: Number, default: 1200 },
    backgroundHeight: { type: Number, default: 800 },
    markers: { type: Array, default: () => [] },
    geometry: { type: Object, required: true },
    style: { type: Object, default: () => ({}) },
    label: { type: String, default: '' },
    photoLegend: { type: String, default: 'FOTOS: —' },
    activeTool: { type: String, default: 'select' },
    selectedPublicId: { type: String, default: null },
});
const emit = defineEmits(['update:geometry', 'zoom', 'working-path', 'select-marker']);
const svg = ref(null);
const zoom = ref(1);
const pan = ref({ x: 0, y: 0 });
const dragStart = ref(null);
const rectangleStart = ref(null);
const workingPoints = ref([]);
const pointerPosition = ref(null);
const viewWidth = 1000;
const viewHeight = computed(() => viewWidth * ((props.backgroundHeight || 800) / (props.backgroundWidth || 1200)));
const viewBox = computed(() => {
    const width = viewWidth / zoom.value;
    const height = viewHeight.value / zoom.value;
    return `${pan.value.x + (viewWidth - width) / 2} ${pan.value.y + (viewHeight.value - height) / 2} ${width} ${height}`;
});
const activeStyle = computed(() => markerPresentationStyle(props.style, props.geometry, viewWidth));
const rectanglePreview = computed(() => rectangleStart.value && pointerPosition.value
    ? rectangleFromPoints(rectangleStart.value, pointerPosition.value)
    : null);
const workingPreviewPoints = computed(() => workingPoints.value.length && pointerPosition.value
    ? [...workingPoints.value, pointerPosition.value]
    : workingPoints.value);
const activeMarker = computed(() => props.geometry?.shapes?.length
    ? {
        presentation_id: '__active__',
        geometry: props.geometry,
        style: props.style,
        label: props.label,
        photo_legend: props.photoLegend,
    }
    : null);
const renderedMarkers = computed(() => activeMarker.value
    ? [...props.markers, activeMarker.value]
    : props.markers);
const legendLayouts = computed(() => layoutMarkerLegends(renderedMarkers.value, viewWidth, viewHeight.value));

function normalized(event) {
    const point = svg.value.createSVGPoint(); point.x = event.clientX; point.y = event.clientY;
    const local = point.matrixTransform(svg.value.getScreenCTM().inverse());
    return [Math.max(0, Math.min(1, local.x / viewWidth)), Math.max(0, Math.min(1, local.y / viewHeight.value))];
}
function change(mutator) {
    const next = JSON.parse(JSON.stringify(props.geometry)); mutator(next); emit('update:geometry', next);
}
function appendShape(shape) { change((next) => next.shapes.push(shape)); }
function rectangleFromPoints(start, end) {
    return {
        type: 'rectangle',
        x: Math.min(start[0], end[0]),
        y: Math.min(start[1], end[1]),
        width: Math.abs(end[0] - start[0]),
        height: Math.abs(end[1] - start[1]),
    };
}
function capturePointer(event) {
    if (svg.value?.setPointerCapture) svg.value.setPointerCapture(event.pointerId);
}
function releasePointer(event) {
    if (svg.value?.hasPointerCapture?.(event.pointerId)) svg.value.releasePointerCapture(event.pointerId);
}
function pointerDown(event) {
    if (props.activeTool === 'pan') {
        capturePointer(event);
        dragStart.value = { x: event.clientX, y: event.clientY, pan: { ...pan.value } };

        return;
    }
    if (props.activeTool === 'rectangle') {
        capturePointer(event);
        rectangleStart.value = normalized(event);
        pointerPosition.value = rectangleStart.value;
    }
}
function pointerMove(event) {
    if (dragStart.value && props.activeTool === 'pan') {
        const rect = svg.value.getBoundingClientRect();
        pan.value = { x: dragStart.value.pan.x - ((event.clientX - dragStart.value.x) / rect.width) * (viewWidth / zoom.value), y: dragStart.value.pan.y - ((event.clientY - dragStart.value.y) / rect.height) * (viewHeight.value / zoom.value) };

        return;
    }
    if (['rectangle', 'point', 'polygon', 'polyline'].includes(props.activeTool)) {
        pointerPosition.value = normalized(event);
    }
}
function pointerUp(event) {
    if (props.activeTool === 'pan') {
        dragStart.value = null;
        releasePointer(event);

        return;
    }
    if (props.activeTool !== 'rectangle' || !rectangleStart.value) return;
    const shape = rectangleFromPoints(rectangleStart.value, normalized(event));
    rectangleStart.value = null;
    pointerPosition.value = null;
    releasePointer(event);
    if (shape.width > 0.002 && shape.height > 0.002) appendShape(shape);
}
function pointerCancel(event) {
    dragStart.value = null;
    rectangleStart.value = null;
    pointerPosition.value = null;
    releasePointer(event);
}
function pointerLeave() {
    if (!rectangleStart.value && !dragStart.value) pointerPosition.value = null;
}
function click(event) {
    if (props.activeTool === 'point') { const [x, y] = normalized(event); appendShape({ type: 'point', x, y }); }
    if (['polygon', 'polyline'].includes(props.activeTool)) {
        const point = normalized(event);
        workingPoints.value.push(point);
        pointerPosition.value = point;
        emit('working-path', true);
    }
}
function finishPath() {
    const minimum = props.activeTool === 'polygon' ? 3 : 2;
    if (workingPoints.value.length >= minimum) appendShape({ type: props.activeTool, points: [...workingPoints.value] });
    cancelPath();
}
function cancelPath() {
    workingPoints.value = [];
    rectangleStart.value = null;
    pointerPosition.value = null;
    emit('working-path', false);
}
function setZoom(next) { zoom.value = Math.max(1, Math.min(5, next)); emit('zoom', zoom.value); }
function resetView() { zoom.value = 1; pan.value = { x: 0, y: 0 }; emit('zoom', zoom.value); }
function keydown(event) {
    if (event.key === 'Escape') cancelPath();
    if (event.key === 'Enter' && props.activeTool === 'point') appendShape({ type: 'point', x: 0.5, y: 0.5 });
    if (event.key === 'Enter' && props.activeTool === 'rectangle') appendShape({ type: 'rectangle', x: 0.35, y: 0.35, width: 0.3, height: 0.3 });
}
function points(points) { return points.map(([x, y]) => `${x * viewWidth},${y * viewHeight.value}`).join(' '); }
function zoomByWheel(event) { event.preventDefault(); setZoom(zoom.value + (event.deltaY < 0 ? 0.25 : -0.25)); }

defineExpose({ finishPath, cancelPath, setZoom, resetView });
</script>

<template>
    <div class="overflow-hidden rounded-2xl bg-slate-950 shadow-inner focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-teal-500 focus-visible:ring-offset-2" tabindex="0" role="group" aria-describedby="inspection-map-editor-help" aria-label="Editor gráfico do mapa. Use Enter para inserir uma forma central com as ferramentas ponto ou retângulo." @keydown="keydown">
        <svg ref="svg" class="block h-auto w-full touch-none select-none" :viewBox="viewBox" @pointerdown="pointerDown" @pointermove="pointerMove" @pointerup="pointerUp" @pointercancel="pointerCancel" @pointerleave="pointerLeave" @click="click" @wheel="zoomByWheel">
            <image :href="backgroundUrl" x="0" y="0" :width="viewWidth" :height="viewHeight" preserveAspectRatio="none" />
            <InspectionLocationMarkerOverlay
                v-for="marker in markers"
                :key="marker.public_id"
                :marker="marker"
                :layout="legendLayouts[marker.public_id]"
                :view-width="viewWidth"
                :view-height="viewHeight"
                :interactive="activeTool === 'select'"
                :selected="selectedPublicId === marker.public_id"
                @select="$emit('select-marker', $event)"
            />
            <InspectionLocationMarkerOverlay
                v-if="activeMarker"
                :marker="activeMarker"
                :layout="legendLayouts.__active__"
                :view-width="viewWidth"
                :view-height="viewHeight"
                selected
            />
            <g>
                <rect v-if="rectanglePreview" :x="rectanglePreview.x * viewWidth" :y="rectanglePreview.y * viewHeight" :width="rectanglePreview.width * viewWidth" :height="rectanglePreview.height * viewHeight" :fill="activeStyle.color" :fill-opacity="activeStyle.fillOpacity" :stroke="activeStyle.stroke" :stroke-width="activeStyle.strokeWidth" stroke-dasharray="10 8" :stroke-opacity="activeStyle.opacity" pointer-events="none" />
                <polyline v-if="workingPreviewPoints.length" :points="points(workingPreviewPoints)" fill="none" :stroke="activeStyle.color" :stroke-width="activeStyle.strokeWidth" stroke-dasharray="10 8" :stroke-opacity="activeStyle.opacity" pointer-events="none" />
                <line v-if="activeTool === 'polygon' && workingPoints.length > 1 && pointerPosition" :x1="pointerPosition[0] * viewWidth" :y1="pointerPosition[1] * viewHeight" :x2="workingPoints[0][0] * viewWidth" :y2="workingPoints[0][1] * viewHeight" :stroke="activeStyle.color" :stroke-width="Math.max(2, activeStyle.strokeWidth / 2)" stroke-dasharray="6 8" opacity="0.65" pointer-events="none" />
                <circle v-for="(point, index) in workingPoints" :key="`working-point-${index}`" :cx="point[0] * viewWidth" :cy="point[1] * viewHeight" r="6" :fill="activeStyle.color" :stroke="activeStyle.color" stroke-width="3" pointer-events="none" />
                <circle v-if="activeTool === 'point' && pointerPosition" :cx="pointerPosition[0] * viewWidth" :cy="pointerPosition[1] * viewHeight" r="11" :fill="activeStyle.color" :stroke="activeStyle.color" :stroke-width="activeStyle.strokeWidth" stroke-dasharray="6 5" :opacity="activeStyle.opacity" pointer-events="none" />
            </g>
        </svg>
    </div>
</template>
