import assert from 'node:assert/strict';
import test from 'node:test';
import {
    buildTechnicalGutPayload,
    calculateTechnicalGut,
    technicalGutReady,
    transporterTypesFor,
    urgencyContextsFor,
    urgencyOptionsFor,
} from '../../resources/js/lib/technicalGut.js';

const shared = {
    category: { code: 'CV' },
    safety_impact_options: [{ code: 'safe-2', score: 2 }],
    asset_impact_options: [{ code: 'asset-5', score: 5 }],
    urgency_contexts: [{ code: 'function', options: [{ code: 'function-3', score: 3 }] }],
    trend_groups: [{ code: 'cracking', options: [{ code: 'growing-crack', score: 4 }] }],
};

test('calculates CV gravity from the highest impact and sends only inspector choices', () => {
    const values = {
        condition: 'new',
        safety_impact_code: 'safe-2',
        asset_impact_code: 'asset-5',
        urgency_context_code: 'function',
        urgency_option_code: 'function-3',
        trend_group_code: 'cracking',
        trend_option_code: 'growing-crack',
        gravity: 1,
        urgency: 1,
        trend: 1,
    };

    assert.deepEqual(calculateTechnicalGut(shared, values), { gravity: 5, urgency: 3, trend: 4, score: 60 });
    assert.deepEqual(buildTechnicalGutPayload(shared, values), {
        condition: 'new',
        safety_impact_code: 'safe-2',
        asset_impact_code: 'asset-5',
        urgency_context_code: 'function',
        urgency_option_code: 'function-3',
        trend_group_code: 'cracking',
        trend_option_code: 'growing-crack',
    });
    assert.equal(technicalGutReady(shared, values), true);
    assert.equal(urgencyContextsFor(shared).length, 1);
    assert.deepEqual(urgencyOptionsFor(shared, 'function'), [{ code: 'function-3', score: 3 }]);
});

test('uses the selected REC matrix and transporter type to resolve urgency', () => {
    const definition = {
        ...shared,
        category: { code: 'REC' },
        urgency_matrices: [
            { code: 'structural_function', options: [{ code: 'guardrail', score: 2 }], transporter_types: [] },
            {
                code: 'patio_port_transporter', options: [], transporter_types: [
                    { code: 'elevated_gallery', options: [{ code: 'top_chord', score: 4 }] },
                    { code: 'floor_level', options: [{ code: 'beam', score: 2 }] },
                ],
            },
        ],
        trend_groups: [{ code: 'discontinuity', options: [{ code: 'visible-crack', score: 4 }] }],
    };
    const values = {
        safety_impact_code: 'safe-2', asset_impact_code: 'asset-5',
        urgency_matrix_code: 'patio_port_transporter', transporter_type_code: '', urgency_option_code: 'top_chord',
        trend_group_code: 'discontinuity', trend_option_code: 'visible-crack',
    };

    assert.equal(technicalGutReady(definition, values), false);
    values.transporter_type_code = 'elevated_gallery';
    assert.equal(technicalGutReady(definition, values), true);
    assert.deepEqual(calculateTechnicalGut(definition, values), { gravity: 5, urgency: 4, trend: 4, score: 80 });
    assert.deepEqual(buildTechnicalGutPayload(definition, values), {
        safety_impact_code: 'safe-2',
        asset_impact_code: 'asset-5',
        urgency_matrix_code: 'patio_port_transporter',
        transporter_type_code: 'elevated_gallery',
        urgency_option_code: 'top_chord',
        trend_group_code: 'discontinuity',
        trend_option_code: 'visible-crack',
    });
    assert.equal(transporterTypesFor(definition, 'patio_port_transporter').length, 2);
    assert.deepEqual(urgencyOptionsFor(definition, 'patio_port_transporter', 'floor_level'), [{ code: 'beam', score: 2 }]);
});

test('uses the selected TAC atmosphere to derive urgency and never submits manual scores', () => {
    const definition = {
        category: { code: 'TAC' },
        sources: {
            gravity: { valid: true, score: 3 },
            urgency: {
                mapping: [
                    { value: 'C2', score: 1 },
                    { value: 'C5', score: 4 },
                    { value: 'CX', score: 5 },
                ],
            },
        },
        trend_options: [{ code: 'astm-2-3', score: 4 }],
    };
    const values = {
        condition: 'reinspected', atmospheric_classification: 'CX', trend_option_code: 'astm-2-3', gravity: 1, urgency: 1,
    };

    assert.deepEqual(calculateTechnicalGut(definition, values), { gravity: 3, urgency: 5, trend: 4, score: 60 });
    assert.deepEqual(buildTechnicalGutPayload(definition, values), {
        condition: 'reinspected', atmospheric_classification: 'CX', trend_option_code: 'astm-2-3',
    });
    values.atmospheric_classification = '';
    assert.equal(technicalGutReady(definition, values), false);
});
