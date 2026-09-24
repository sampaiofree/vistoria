import assert from 'node:assert/strict';
import test from 'node:test';
import {
    buildNativeQuantityPayload,
    calculateCivilQuantity,
    calculateRecQuantity,
    calculateTacQuantity,
    formatNativeMeasurement,
} from '../../resources/js/lib/nativeQuantity.js';

function close(actual, expected) {
    assert.ok(Math.abs(actual - expected) < 1e-9, `${actual} should be close to ${expected}`);
}

test('previews CIVIL and TAC while keeping calculated results out of requests', () => {
    assert.deepEqual(calculateCivilQuantity({ length: 2, height: 0.5, width: 0.3, quantity: 2 }), {
        unitValue: 0.3, totalValue: 0.6, unit: 'm3', mode: 'calculated',
    });
    assert.deepEqual(calculateTacQuantity({ area: 12.75 }), {
        unitValue: null, totalValue: 12.75, unit: 'm2', mode: 'manual',
    });
    assert.deepEqual(buildNativeQuantityPayload('CV', {
        length: 2, height: 0.5, width: 0.3, quantity: 2, unitValue: 0.3, totalValue: 0.6,
    }), { length: 2, height: 0.5, width: 0.3, quantity: 2 });
    assert.deepEqual(buildNativeQuantityPayload('CV', {
        length: 2, height: 0.5, width: 0.3, unitValue: 0.3, totalValue: 0.3,
    }), { length: 2, height: 0.5, width: 0.3, quantity: 1 });
    assert.deepEqual(buildNativeQuantityPayload('CV', {
        length: 2, height: 0.5, width: 0.3, quantity: 3,
    }), { length: 2, height: 0.5, width: 0.3, quantity: 3 });
});

test('calculates every REC formula from document 09', () => {
    const cases = [
        ['profile_w', { flange_width: 200, flange_thickness: 10, web_height: 300, web_thickness: 8, length: 2 }, 98.4704],
        ['profile_l', { width: 76, thickness: 6, length: 2.8 }, 19.25448],
        ['profile_u', { height: 200, web_thickness: 8, width: 75, flange_thickness: 10, length: 2 }, 46.158],
        ['profile_ue', { height: 200, web_thickness: 8, flange_width: 75, fold_width: 20, flange_thickness: 10, length: 2 }, 49.298],
        ['smooth_plate', { width: 1000, length: 2000, thickness: 10 }, 157],
        ['flat_bar', { width: 100, length: 2000, thickness: 10 }, 15.7],
        ['checkered_plate', { width: 1000, length: 2000, thickness: 5 }, 78.5],
        ['guardrail', { length: 2.5 }, 75],
        ['caged_ladder', { length: 2.5 }, 150],
        ['unequal_angle', { leg_1: 80, leg_2: 60, thickness: 6, length: 2 }, 12.6228],
        ['metalon', { side_1: 100, side_2: 50, thickness: 4, length: 2 }, 17.8352],
        ['tee_profile', { flange_width: 100, web_height: 80, thickness: 8, length: 2 }, 21.6032],
    ];

    for (const [element, inputs, unit] of cases) {
        const result = calculateRecQuantity({ element, ...inputs, quantity: 1.5 });
        close(result.unitValue, unit);
        close(result.totalValue, unit * 1.5);
    }

    const tubular = calculateRecQuantity({ element: 'tubular_profile', outer_diameter: 100, thickness: 5, length: 2000, quantity: 2 });
    close(tubular.unitValue, 23.428427214145884);
    close(tubular.totalValue, 46.85685442829177);
});

test('handles manual REC weights and rejects impossible geometry', () => {
    assert.deepEqual(calculateRecQuantity({ element: 'roof_sheet', total_weight: 125.5 }), {
        unitValue: null, totalValue: 125.5, unit: 'kg', mode: 'manual',
    });
    assert.equal(calculateRecQuantity({ element: 'tubular_profile', outer_diameter: 10, thickness: 5, length: 1000, quantity: 1 }), null);
    assert.equal(calculateRecQuantity({ element: 'profile_w', flange_width: 100, flange_thickness: 5, web_height: 10, web_thickness: 5, length: 1, quantity: 1 }), null);
});

test('builds a REC request from definition fields and formats only the presentation', () => {
    const definition = { elements: [{ code: 'profile_l', mode: 'calculated', fields: [
        { key: 'width' }, { key: 'thickness' }, { key: 'length' },
    ] }] };
    assert.deepEqual(buildNativeQuantityPayload('REC', {
        element: 'profile_l', width: 76, thickness: 6, length: 2.8, quantity: 2,
        unitValue: 19.25448, totalValue: 38.50896,
    }, definition), { element: 'profile_l', width: 76, thickness: 6, length: 2.8, quantity: 2 });
    assert.deepEqual(buildNativeQuantityPayload('REC', {
        element: 'profile_l', width: 76, thickness: 6, length: 2.8,
        unitValue: 19.25448, totalValue: 19.25448,
    }, definition), { element: 'profile_l', width: 76, thickness: 6, length: 2.8, quantity: 1 });
    assert.deepEqual(buildNativeQuantityPayload('REC', {
        element: 'profile_l', width: 76, thickness: 6, length: 2.8, quantity: 3,
    }, definition), { element: 'profile_l', width: 76, thickness: 6, length: 2.8, quantity: 3 });
    assert.equal(formatNativeMeasurement(38.50896), '38,51');
    assert.equal(formatNativeMeasurement(1234.5), '1.234,50');
});
