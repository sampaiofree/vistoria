import assert from 'node:assert/strict';
import test from 'node:test';
import { markerLegendTextColor } from '../../resources/js/components/domain/inspection-locations/inspectionLocationMarkerStyle.js';

test('map legend text stays legible on M3 colors and the default marker', () => {
    assert.equal(markerLegendTextColor('#37174F'), '#FFFFFF');
    assert.equal(markerLegendTextColor('#7553CC'), '#FFFFFF');
    assert.equal(markerLegendTextColor('#D8C2E8'), '#000000');
    assert.equal(markerLegendTextColor('#F1DF00'), '#000000');
});
