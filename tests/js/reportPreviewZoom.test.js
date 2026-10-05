import assert from 'node:assert/strict';
import test from 'node:test';
import { reportPreviewScale, stepReportZoom } from '../../resources/js/lib/reportPreviewZoom.js';

test('fits both A4 orientations into the available width, including narrow mobile screens', () => {
    for (const width of [280, 740, 1500]) {
        for (const [orientation, millimeters] of [['portrait', 210], ['landscape', 297]]) {
            const originalWidth = millimeters * 96 / 25.4;
            assert.ok(Math.abs(originalWidth * reportPreviewScale(width, orientation) - width) < 0.001);
        }
    }
    assert.equal(reportPreviewScale(0), 1);
});

test('manual zoom uses the same original-size percentage regardless of orientation and viewport', () => {
    for (const percent of [50, 75, 100, 125, 150, 175, 200]) {
        assert.equal(reportPreviewScale(280, 'portrait', percent), percent / 100);
        assert.equal(reportPreviewScale(1500, 'landscape', percent), percent / 100);
    }
});

test('moves from an automatic scale to the adjacent 25-point step within the manual limits', () => {
    assert.equal(stepReportZoom(83.7, 1), 100);
    assert.equal(stepReportZoom(83.7, -1), 75);
    assert.equal(stepReportZoom(100, 1), 125);
    assert.equal(stepReportZoom(100, -1), 75);
    assert.equal(stepReportZoom(99.999999, 1), 125);
    assert.equal(stepReportZoom(100.000001, -1), 75);
    assert.equal(stepReportZoom(31, 1), 50);
    assert.equal(stepReportZoom(240, -1), 200);
    assert.equal(stepReportZoom(200, 1), 200);
    assert.equal(stepReportZoom(50, -1), 50);
});
