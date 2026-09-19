import assert from 'node:assert/strict';
import test from 'node:test';
import { calculateCivilVolume, formatCivilMeasurement } from '../../resources/js/lib/civilQuantity.js';

test('calculates unit and total volumes with fractional dimensions and quantity', () => {
    assert.deepEqual(calculateCivilVolume({ length: 2.5, height: 0.2, width: 0.4, quantity: 1.5 }), {
        unitVolume: '0.2', totalVolume: '0.3',
    });
    assert.deepEqual(calculateCivilVolume({ length: 2, height: 3, width: 4, quantity: 2.5 }), {
        unitVolume: '24', totalVolume: '60',
    });
});

test('preserves very small volumes without intermediate rounding or floating point artifacts', () => {
    assert.deepEqual(calculateCivilVolume({ length: '0.0001', height: '0.0001', width: '0.0001', quantity: '0.0001' }), {
        unitVolume: '0.000000000001', totalVolume: '0.0000000000000001',
    });
    assert.deepEqual(calculateCivilVolume({ length: 0.1, height: 0.2, width: 0.3, quantity: 0.1 }), {
        unitVolume: '0.006', totalVolume: '0.0006',
    });
});

test('does not show a calculated volume for incomplete or invalid measurements', () => {
    for (const width of ['', null, undefined, 0, -1, 'abc', '0.00001']) {
        assert.equal(calculateCivilVolume({ length: 1, height: 1, width, quantity: 1 }), null);
    }
    assert.equal(calculateCivilVolume({ length: 1, height: 1, width: 1, quantity: 0 }), null);
});

test('formats decimals in Portuguese and expands scientific notation in persisted volumes', () => {
    assert.equal(formatCivilMeasurement('1234.5000'), '1.234,5');
    assert.equal(formatCivilMeasurement('0.0000000000000001'), '0,0000000000000001');
    assert.equal(formatCivilMeasurement(1e-16), '0,0000000000000001');
    assert.equal(formatCivilMeasurement(null), '—');
});
