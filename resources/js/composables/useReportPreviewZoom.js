import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { MAX_REPORT_ZOOM, MIN_REPORT_ZOOM, reportPreviewScale, stepReportZoom } from '@/lib/reportPreviewZoom.js';

export function useReportPreviewZoom(previewRoot, toolbar) {
    const contentWidth = ref(0);
    const manualPercent = ref(null);
    const activeOrientation = ref('portrait');
    let resizeObserver;
    let resizeFrame;
    let scrollFrame;
    let revision = 0;

    const getPageElements = () => [...(previewRoot.value?.querySelectorAll('.report-a4-page') ?? [])];
    const activePercent = computed(() => reportPreviewScale(contentWidth.value, activeOrientation.value, manualPercent.value) * 100);
    const fitting = computed(() => manualPercent.value === null);
    const canDecrease = computed(() => activePercent.value > MIN_REPORT_ZOOM + 0.001);
    const canIncrease = computed(() => activePercent.value < MAX_REPORT_ZOOM - 0.001);

    function readingPosition() {
        const root = previewRoot.value;
        if (!root) return null;
        const readingY = (toolbar.value?.getBoundingClientRect().bottom ?? 0) + 12;
        const element = getPageElements().find((page) => page.getBoundingClientRect().bottom > readingY);
        if (!element) return null;
        const rect = element.getBoundingClientRect();
        activeOrientation.value = element.dataset.reportOrientation;
        if (rect.top >= window.innerHeight || readingY >= window.innerHeight) return null;
        const viewport = root.getBoundingClientRect();
        const x = viewport.left + root.clientWidth / 2;
        const y = Math.max(rect.top, readingY);
        return { element, x, y, horizontal: (x - rect.left) / rect.width, vertical: (y - rect.top) / rect.height };
    }

    async function preservePosition(change) {
        // Keep a point within the current page fixed, even when preceding pages
        // have a different orientation and therefore a different fit scale.
        const anchor = readingPosition();
        const currentRevision = ++revision;
        change();
        await nextTick();
        if (currentRevision !== revision || !previewRoot.value) return;
        if (anchor?.element.isConnected) {
            const rect = anchor.element.getBoundingClientRect();
            previewRoot.value.scrollLeft += rect.left + rect.width * anchor.horizontal - anchor.x;
            window.scrollBy({ top: rect.top + rect.height * anchor.vertical - anchor.y, behavior: 'instant' });
        }
        readingPosition();
    }

    function updateWidth() {
        if (!previewRoot.value) return;
        const root = previewRoot.value;
        const style = getComputedStyle(root);
        const width = Math.max(0, root.clientWidth - parseFloat(style.paddingLeft) - parseFloat(style.paddingRight) - 2);
        if (Math.abs(width - contentWidth.value) < 0.5) return;
        preservePosition(() => { contentWidth.value = width; });
    }

    function changeZoom(direction) {
        readingPosition();
        if (direction > 0 ? !canIncrease.value : !canDecrease.value) return;
        preservePosition(() => { manualPercent.value = stepReportZoom(activePercent.value, direction); });
    }

    function fitWidth() {
        preservePosition(() => { manualPercent.value = null; });
    }

    function onScroll() {
        if (scrollFrame) return;
        scrollFrame = requestAnimationFrame(() => {
            scrollFrame = null;
            readingPosition();
        });
    }

    onMounted(() => {
        updateWidth();
        resizeObserver = new ResizeObserver(() => {
            if (resizeFrame) return;
            resizeFrame = requestAnimationFrame(() => {
                resizeFrame = null;
                updateWidth();
            });
        });
        resizeObserver.observe(previewRoot.value);
        window.addEventListener('scroll', onScroll, { passive: true });
    });

    onBeforeUnmount(() => {
        revision += 1;
        resizeObserver?.disconnect();
        cancelAnimationFrame(resizeFrame);
        cancelAnimationFrame(scrollFrame);
        window.removeEventListener('scroll', onScroll);
    });

    return {
        getPageElements, fitting, canDecrease, canIncrease, changeZoom, fitWidth,
        zoomLabel: computed(() => `${Math.round(activePercent.value)}%`),
        pagePreviewStyle: (page) => ({ '--report-preview-scale': String(reportPreviewScale(contentWidth.value, page.orientation, manualPercent.value)) }),
    };
}
