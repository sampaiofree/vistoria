export function reportMapObservation(map) {
    const comment = String(map?.observations ?? '');
    const interval = map?.damage_rows?.[0]?.photo_interval || '—';
    const photoLine = `FOTOS: ${interval}`;

    return comment ? `${comment}\n${photoLine}` : photoLine;
}
