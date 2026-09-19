function scaledInput(value) {
    const input = String(value ?? '').trim();
    if (!/^\d+(?:\.\d{1,4})?$/.test(input)) return null;

    const [integer, fraction = ''] = input.split('.');
    const scaled = BigInt(integer) * 10000n + BigInt(fraction.padEnd(4, '0'));

    return scaled > 0n ? scaled : null;
}

function decimalString(value, scale) {
    const digits = value.toString().padStart(scale + 1, '0');
    const fraction = digits.slice(-scale).replace(/0+$/, '');

    return digits.slice(0, -scale) + (fraction ? `.${fraction}` : '');
}

export function calculateCivilVolume({ length, height, width, quantity }) {
    const values = [length, height, width, quantity].map(scaledInput);
    if (values.some((value) => value === null)) return null;

    const [l, h, w, count] = values;
    const unit = l * h * w;

    return {
        unitVolume: decimalString(unit, 12),
        totalVolume: decimalString(unit * count, 16),
    };
}

export function formatCivilMeasurement(value) {
    if (value === null || value === undefined || value === '') return '—';

    // Expand scientific notation used by JSON numbers for very small volumes.
    const plain = typeof value === 'number' && /e/i.test(String(value))
        ? value.toFixed(16)
        : String(value);
    const [integer, decimal = ''] = plain.split('.');
    const fraction = decimal.replace(/0+$/, '');

    return integer.replace(/\B(?=(\d{3})+(?!\d))/g, '.') + (fraction ? `,${fraction}` : '');
}
