import assert from 'node:assert/strict';
import test from 'node:test';
import {
    buildTechnicalGutPayload,
    calculateTechnicalGut,
    MANUAL_OPTION,
    technicalGutReady,
} from '../../resources/js/lib/technicalGut.js';

const shared = {
    category: { code: 'CV' },
    safety_impact_options: [{ code: 'safe-2', score: 2 }],
    asset_impact_options: [{ code: 'asset-5', score: 5 }],
    urgency_options: [{ code: 'function-3', score: 3 }],
};

test('calculates CV gravity from the highest impact and sends only inspector choices', () => {
    const values = {
        condition: 'new',
        safety_impact_code: 'safe-2',
        asset_impact_code: 'asset-5',
        urgency_option_code: 'function-3',
        trend_group_code: 'cracking',
        trend_manual_description: 'Fissura crescente',
        trend_manual_score: 4,
        gravity: 1,
        urgency: 1,
        trend: 1,
    };

    assert.deepEqual(calculateTechnicalGut(shared, values), { gravity: 5, urgency: 3, trend: 4, score: 60 });
    assert.deepEqual(buildTechnicalGutPayload(shared, values), {
        condition: 'new',
        safety_impact_code: 'safe-2',
        asset_impact_code: 'asset-5',
        urgency_option_code: 'function-3',
        trend_group_code: 'cracking',
        trend_manual_description: 'Fissura crescente',
        trend_manual_score: 4,
    });
    assert.equal(technicalGutReady(shared, values), true);
});

test('requires a technical description for manual urgency', () => {
    const definition = {
        ...shared,
        category: { code: 'REC' },
        urgency_allows_manual: true,
        trend_groups: [{ code: 'discontinuity', options: [{ code: 'visible-crack', score: 4 }] }],
    };
    const values = {
        safety_impact_code: 'safe-2', asset_impact_code: 'asset-5',
        urgency_option_code: MANUAL_OPTION, urgency_manual_description: '', urgency_manual_score: 2,
        trend_group_code: 'discontinuity', trend_option_code: 'visible-crack',
    };

    assert.equal(technicalGutReady(definition, values), false);
    values.urgency_manual_description = 'Transportador de correia';
    assert.equal(technicalGutReady(definition, values), true);
    assert.equal(buildTechnicalGutPayload(definition, values).urgency_option_code, undefined);
});

test('uses contextual TAC sources and never submits their calculated scores', () => {
    const definition = {
        category: { code: 'TAC' },
        sources: { gravity: { valid: true, score: 3 }, urgency: { valid: true, score: 5 } },
        trend_options: [{ code: 'astm-2-3', score: 4 }],
    };
    const values = { condition: 'reinspected', trend_option_code: 'astm-2-3', gravity: 1, urgency: 1 };

    assert.deepEqual(calculateTechnicalGut(definition, values), { gravity: 3, urgency: 5, trend: 4, score: 60 });
    assert.deepEqual(buildTechnicalGutPayload(definition, values), {
        condition: 'reinspected', trend_option_code: 'astm-2-3',
    });
    definition.sources.urgency.valid = false;
    assert.equal(technicalGutReady(definition, values), false);
});
