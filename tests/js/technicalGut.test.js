import assert from 'node:assert/strict';
import test from 'node:test';
import {
    buildTechnicalGutPayload,
    calculateTechnicalGut,
    impactOptionsFor,
    restoreCompatibleTrend,
    technicalGutReady,
    transporterTypesFor,
    trendGroupsFor,
    trendOptionsFor,
    urgencyContextsFor,
    urgencyOptionsFor,
} from '../../resources/js/lib/technicalGut.js';

const shared = {
    category: { code: 'CV' },
    safety_impact_options: [{ code: 'safe-2', score: 2 }],
    asset_impact_options: [{ code: 'asset-5', score: 5 }],
    urgency_contexts: [{ code: 'function', options: [{ code: 'function-3', score: 3 }] }],
    trend_groups: [{ code: 'cracking', urgency_context_code: 'function', options: [{ code: 'growing-crack', score: 4 }] }],
};

for (const category of ['CV', 'REC']) {
    const options = [
        ...Array.from({ length: 5 }, (_, i) => ({ code: `impact-${i + 1}`, score: i + 1 })),
        { code: 'not_applicable', label: 'Não se aplica', score: null, color: null },
    ];
    const definition = {
        ...shared, category: { code: category },
        safety_impact_options: options, asset_impact_options: options,
        urgency_matrices: [{ code: 'structural_function', options: [{ code: 'function-3', score: 3 }] }],
    };
    const base = {
        safety_impact_code: 'impact-2', asset_impact_code: 'impact-5',
        urgency_context_code: 'function', urgency_matrix_code: 'structural_function', urgency_option_code: 'function-3',
        trend_group_code: 'cracking', trend_option_code: 'growing-crack',
    };

    test(`${category} uses the applicable impact and keeps its counterpart explicit in the payload`, () => {
        for (const field of ['safety_impact_code', 'asset_impact_code']) {
            const other = field === 'safety_impact_code' ? 'asset_impact_code' : 'safety_impact_code';
            for (let score = 1; score <= 5; score++) {
                const values = { ...base, [field]: 'not_applicable', [other]: `impact-${score}` };
                assert.deepEqual(calculateTechnicalGut(definition, values), { gravity: score, urgency: 3, trend: 4, score: score * 12 });
                assert.equal(technicalGutReady(definition, values), true);
                assert.equal(buildTechnicalGutPayload(definition, values)[field], 'not_applicable');
            }
        }
    });

    test(`${category} requires two known choices and at least one applicable impact`, () => {
        for (const field of ['safety_impact_code', 'asset_impact_code']) {
            const other = field === 'safety_impact_code' ? 'asset_impact_code' : 'safety_impact_code';
            for (const value of ['not_applicable', '', null, undefined, 'unknown']) {
                const values = { ...base, [field]: value, [other]: 'not_applicable' };
                assert.equal(calculateTechnicalGut(definition, values), null);
                assert.equal(technicalGutReady(definition, values), false);
            }
        }
    });

    test(`${category} keeps the maximum rule for every pair of scored impacts`, () => {
        for (let safety = 1; safety <= 5; safety++) {
            for (let asset = 1; asset <= 5; asset++) {
                assert.equal(calculateTechnicalGut(definition, {
                    ...base, safety_impact_code: `impact-${safety}`, asset_impact_code: `impact-${asset}`,
                }).gravity, Math.max(safety, asset));
            }
        }
    });
}

test('disables only not applicable while the other impact is not applicable and restores it on change', () => {
    const options = [{ code: 'impact', score: 2 }, { code: 'not_applicable', score: null }];
    assert.deepEqual(impactOptionsFor(options, 'not_applicable').map(option => option.disabled), [false, true]);
    assert.deepEqual(impactOptionsFor(options, 'impact').map(option => option.disabled), [false, false]);
    assert.deepEqual(impactOptionsFor(options, '').map(option => option.disabled), [false, false]);
    assert.equal(options[1].disabled, undefined);
});

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

test('CIVIL only exposes trends after complete urgency and filters by context, not element', () => {
    const definition = {
        ...shared,
        urgency_contexts: [
            ...shared.urgency_contexts,
            { code: 'machine_running_path', options: [{ code: 'rail', score: 3 }, { code: 'joint', score: 4 }] },
        ],
        trend_groups: [...shared.trend_groups, { code: 'rail_joint', urgency_context_code: 'machine_running_path', options: [{ code: 'gap', score: 1 }] }],
    };
    const values = {
        safety_impact_code: 'safe-2', asset_impact_code: 'asset-5',
        urgency_context_code: 'function', urgency_option_code: 'function-3',
        trend_group_code: 'cracking', trend_option_code: 'growing-crack',
    };
    for (const incomplete of [{}, { urgency_context_code: 'function' }, { urgency_context_code: 'machine_running_path', urgency_option_code: 'function-3' }]) {
        assert.deepEqual(trendGroupsFor(definition, incomplete), []);
        assert.deepEqual(trendOptionsFor(definition, 'cracking', incomplete), []);
    }
    assert.deepEqual(trendGroupsFor(definition, values).map(group => group.code), ['cracking']);
    assert.deepEqual(restoreCompatibleTrend(definition, values), values);
    for (const element of ['rail', 'joint']) {
        const crossed = { ...values, urgency_context_code: 'machine_running_path', urgency_option_code: element };
        assert.deepEqual(trendGroupsFor(definition, crossed).map(group => group.code), ['rail_joint']);
        assert.equal(calculateTechnicalGut(definition, crossed), null);
        assert.equal(technicalGutReady(definition, crossed), false);
        assert.deepEqual(restoreCompatibleTrend(definition, crossed), { ...crossed, trend_group_code: '', trend_option_code: '' });
        assert.equal(crossed.trend_group_code, 'cracking');
        const compatible = { ...crossed, trend_group_code: 'rail_joint', trend_option_code: 'gap' };
        assert.equal(technicalGutReady(definition, compatible), true);
        assert.deepEqual(restoreCompatibleTrend(definition, compatible), compatible);
    }
    const reverse = { ...values, trend_group_code: 'rail_joint', trend_option_code: 'gap' };
    assert.equal(calculateTechnicalGut(definition, reverse), null);
    assert.deepEqual(restoreCompatibleTrend(definition, reverse), { ...reverse, trend_group_code: '', trend_option_code: '' });
    assert.deepEqual(restoreCompatibleTrend(definition, { ...values, trend_option_code: 'unknown' }), { ...values, trend_option_code: '' });
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
