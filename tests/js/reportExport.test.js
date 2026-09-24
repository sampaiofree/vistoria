import assert from 'node:assert/strict';
import test from 'node:test';
import { reportPageDimensions } from '../../resources/js/lib/reportExport.js';

test('uses A4 dimensions appropriate to each report page orientation', () => {
    assert.deepEqual(reportPageDimensions('portrait'), {
        orientation: 'portrait', widthMm: 210, heightMm: 297,
        widthPx: 794, heightPx: 1123,
        widthTwips: 11906, heightTwips: 16838,
    });
    assert.deepEqual(reportPageDimensions('landscape'), {
        orientation: 'landscape', widthMm: 297, heightMm: 210,
        widthPx: 1123, heightPx: 794,
        widthTwips: 16838, heightTwips: 11906,
    });
});
