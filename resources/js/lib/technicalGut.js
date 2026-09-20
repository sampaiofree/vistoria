export const MANUAL_OPTION = '__manual__';

function categoryCode(definition) {
    return definition?.category?.code ?? definition?.category ?? null;
}

function optionCode(option) {
    return option?.code ?? option?.value ?? null;
}

function findOption(options, code) {
    return (options ?? []).find((option) => optionCode(option) === code) ?? null;
}

function scoreOf(value) {
    const score = Number(value);

    return Number.isInteger(score) && score >= 1 && score <= 5 ? score : null;
}

export function trendOptionsFor(definition, groupCode) {
    if (categoryCode(definition) === 'TAC') return definition?.trend_options ?? [];

    return (definition?.trend_groups ?? [])
        .find((group) => optionCode(group) === groupCode)
        ?.options ?? [];
}

export function calculateTechnicalGut(definition, values) {
    const category = categoryCode(definition);
    let gravity = null;
    let urgency = null;
    let trend = null;

    if (category === 'TAC') {
        gravity = definition?.sources?.gravity?.valid === false
            ? null
            : scoreOf(definition?.sources?.gravity?.score);
        urgency = definition?.sources?.urgency?.valid === false
            ? null
            : scoreOf(definition?.sources?.urgency?.score);
        trend = scoreOf(findOption(definition?.trend_options, values.trend_option_code)?.score);
    } else if (category === 'CV' || category === 'REC') {
        const safety = scoreOf(findOption(definition?.safety_impact_options, values.safety_impact_code)?.score);
        const asset = scoreOf(findOption(definition?.asset_impact_options, values.asset_impact_code)?.score);

        gravity = safety !== null && asset !== null ? Math.max(safety, asset) : null;
        urgency = values.urgency_option_code === MANUAL_OPTION && definition?.urgency_allows_manual
            ? scoreOf(values.urgency_manual_score)
            : scoreOf(findOption(definition?.urgency_options, values.urgency_option_code)?.score);

        if (category === 'CV') {
            trend = scoreOf(values.trend_manual_score);
        } else {
            trend = scoreOf(findOption(
                trendOptionsFor(definition, values.trend_group_code),
                values.trend_option_code,
            )?.score);
        }
    }

    if (gravity === null || urgency === null || trend === null) return null;

    return { gravity, urgency, trend, score: gravity * urgency * trend };
}

export function buildTechnicalGutPayload(definition, values) {
    const category = categoryCode(definition);
    const payload = {};

    if (values.condition) payload.condition = values.condition;

    if (category === 'TAC') {
        payload.trend_option_code = values.trend_option_code;

        return payload;
    }

    payload.safety_impact_code = values.safety_impact_code;
    payload.asset_impact_code = values.asset_impact_code;

    if (values.urgency_option_code === MANUAL_OPTION && definition?.urgency_allows_manual) {
        payload.urgency_manual_description = values.urgency_manual_description;
        payload.urgency_manual_score = values.urgency_manual_score;
    } else {
        payload.urgency_option_code = values.urgency_option_code;
    }

    payload.trend_group_code = values.trend_group_code;

    if (category === 'CV') {
        payload.trend_manual_description = values.trend_manual_description;
        payload.trend_manual_score = values.trend_manual_score;
    } else {
        payload.trend_option_code = values.trend_option_code;
    }

    return payload;
}

export function technicalGutReady(definition, values) {
    const result = calculateTechnicalGut(definition, values);
    if (!result) return false;

    const category = categoryCode(definition);
    if (category === 'TAC') return Boolean(values.trend_option_code);
    if (!values.safety_impact_code || !values.asset_impact_code) return false;

    if (values.urgency_option_code === MANUAL_OPTION
        && !String(values.urgency_manual_description ?? '').trim()) return false;

    if (category === 'CV') {
        return Boolean(values.trend_group_code)
            && Boolean(String(values.trend_manual_description ?? '').trim());
    }

    return Boolean(values.trend_group_code && values.trend_option_code);
}

export function technicalOptionLabel(option, criterion = null) {
    if (!option) return '';

    const label = option.label ?? option.description ?? option.name ?? optionCode(option) ?? '';
    const score = scoreOf(option.score);

    return score === null || !criterion ? label : `${criterion} = ${score} — ${label}`;
}
