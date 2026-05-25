// resources/js/config/elementIcons.js
//
// Single icon library for all element types.
// Each entry is a function (color: string) → SVG inner content string.
// The outer <svg> wrapper is added by the consumers (iconSvgString, iconDataUrl).
//
// To add a new icon:  add one entry here, reference the key in elementTypes.js.
// Nothing else needs to change.

export const elementIcons = {
    // ── Generic ──────────────────────────────────────────────────────────────
    dot: (c) =>
        `<circle cx="12" cy="12" r="8" fill="${c}"/>`,

    ring: (c) =>
        `<circle cx="12" cy="12" r="8" fill="none" stroke="${c}" stroke-width="3"/>`,

    square: (c) =>
        `<rect x="4" y="4" width="16" height="16" rx="2" fill="${c}"/>`,

    diamond: (c) =>
        `<polygon points="12,2 22,12 12,22 2,12" fill="${c}"/>`,

    // ── Routes ───────────────────────────────────────────────────────────────
    route_line: (c) =>
        `<path d="M4 18C4 18 8 12 12 12C16 12 20 6 20 6" stroke="${c}" stroke-width="3" fill="none" stroke-linecap="round"/>`,

    pedestrian: (c) =>
        `<circle cx="12" cy="4" r="2.5" fill="${c}"/>` +
        `<path d="M12 7v6l-3 5M12 13l3 5M9 10h6" stroke="${c}" stroke-width="1.8" fill="none" stroke-linecap="round" stroke-linejoin="round"/>`,

    vehicle: (c) =>
        `<rect x="1" y="8" width="14" height="9" rx="1.5" fill="${c}"/>` +
        `<path d="M15 11h4l2 4v2h-6" fill="${c}"/>` +
        `<circle cx="5" cy="18" r="2" fill="white"/>` +
        `<circle cx="18" cy="18" r="2" fill="white"/>`,

    barrier_line: (c) =>
        `<path d="M3 12h18" stroke="${c}" stroke-width="3" stroke-linecap="round"/>` +
        `<path d="M5 9l4 6M9 9l4 6M13 9l4 6" stroke="${c}" stroke-width="1.5" stroke-linecap="round"/>`,

    fence: (c) =>
        `<path d="M3 10h18M3 14h18" stroke="${c}" stroke-width="2"/>` +
        `<path d="M6 7v10M12 7v10M18 7v10" stroke="${c}" stroke-width="1.5"/>` +
        `<path d="M6 7l-1.5-2.5 1.5 0 1.5-2.5 1.5 2.5 1.5 0-1.5 2.5" fill="${c}"/>` +
        `<path d="M12 7l-1.5-2.5 1.5 0 1.5-2.5 1.5 2.5 1.5 0-1.5 2.5" fill="${c}"/>` +
        `<path d="M18 7l-1.5-2.5 1.5 0 1.5-2.5 1.5 2.5 1.5 0-1.5 2.5" fill="${c}"/>`,

    // ── Markers ──────────────────────────────────────────────────────────────
    buoy: (c) =>
        `<circle cx="12" cy="12" r="9" fill="none" stroke="${c}" stroke-width="3"/>` +
        `<circle cx="12" cy="12" r="3.5" fill="${c}"/>`,

    flag_start: (c) =>
        `<path d="M6 21V3" stroke="${c}" stroke-width="2.5" stroke-linecap="round"/>` +
        `<path d="M6 3h12l-3 4.5L18 12H6V3z" fill="${c}"/>`,

    flag_finish: (c) =>
        `<path d="M6 21V3" stroke="${c}" stroke-width="2.5" stroke-linecap="round"/>` +
        `<rect x="6" y="3" width="12" height="9" fill="${c}"/>` +
        `<rect x="6" y="3" width="3" height="3" fill="white"/>` +
        `<rect x="12" y="3" width="3" height="3" fill="white"/>` +
        `<rect x="9" y="6" width="3" height="3" fill="white"/>` +
        `<rect x="15" y="6" width="3" height="3" fill="white"/>`,

    checkpoint: (c) =>
        `<polygon points="12,2 22,12 12,22 2,12" fill="${c}"/>` +
        `<path d="M9 12l2 2 4-4" stroke="white" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"/>`,

    aid: (c) =>
        `<circle cx="12" cy="12" r="9" fill="${c}"/>` +
        `<path d="M12 7v10M7 12h10" stroke="white" stroke-width="2.5" stroke-linecap="round"/>`,

    medical_cross: (c) =>
        `<rect x="2" y="2" width="20" height="20" rx="3" fill="${c}"/>` +
        `<path d="M12 6v12M6 12h12" stroke="white" stroke-width="3" stroke-linecap="round"/>`,

    warning: (c) =>
        `<polygon points="12,2 22,21 2,21" fill="${c}"/>` +
        `<path d="M12 9v5" stroke="white" stroke-width="2.5" stroke-linecap="round"/>` +
        `<circle cx="12" cy="17.5" r="1.2" fill="white"/>`,

    lightning: (c) =>
        `<path d="M13 2L4 14h7l-2 8 11-12H13l2-8z" fill="${c}"/>`,

    transition_arrows: (c) =>
        `<path d="M5 12h14M15 8l4 4-4 4" stroke="${c}" stroke-width="2.2" fill="none" stroke-linecap="round" stroke-linejoin="round"/>` +
        `<path d="M9 16l-4-4 4-4" stroke="${c}" stroke-width="2.2" fill="none" stroke-linecap="round" stroke-linejoin="round"/>`,

    clock: (c) =>
        `<circle cx="12" cy="12" r="9" fill="none" stroke="${c}" stroke-width="2.5"/>` +
        `<path d="M12 7v5.5l3.5 2" stroke="${c}" stroke-width="2" stroke-linecap="round" fill="none"/>`,

    people: (c) =>
        `<circle cx="8" cy="5" r="2.5" fill="${c}"/>` +
        `<circle cx="16" cy="5" r="2.5" fill="${c}"/>` +
        `<path d="M3 20c0-3.5 2.5-6 5-6h8c2.5 0 5 2.5 5 6" fill="${c}"/>`,

    bag: (c) =>
        `<path d="M7 8V6a5 5 0 0110 0v2h2l1 13H4L5 8h2z" fill="${c}"/>` +
        `<path d="M9 8v2a3 3 0 006 0V8" stroke="white" stroke-width="1.5" fill="none"/>`,

    feed: (c) =>
        `<path d="M8 3v8a4 4 0 008 0V3" stroke="${c}" stroke-width="2" fill="none" stroke-linecap="round"/>` +
        `<path d="M12 11v10" stroke="${c}" stroke-width="2" stroke-linecap="round"/>` +
        `<path d="M9 17h6" stroke="${c}" stroke-width="2" stroke-linecap="round"/>`,

    // ── Zones ────────────────────────────────────────────────────────────────
    zone_restricted: (c) =>
        `<circle cx="12" cy="12" r="10" fill="${c}" opacity="0.9"/>` +
        `<path d="M5.6 5.6l12.8 12.8M18.4 5.6L5.6 18.4" stroke="white" stroke-width="3" stroke-linecap="round"/>`,

    parking: (c) =>
        `<rect x="2" y="2" width="20" height="20" rx="3" fill="${c}"/>` +
        `<path d="M9 18V6h5a3.5 3.5 0 010 7H9" stroke="white" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round"/>`,

    zone_area: (c) =>
        `<rect x="3" y="3" width="18" height="18" rx="2" fill="${c}" opacity="0.7"/>` +
        `<rect x="3" y="3" width="18" height="18" rx="2" fill="none" stroke="${c}" stroke-width="2"/>`,

    spectator_zone: (c) =>
        `<path d="M3 20h18" stroke="${c}" stroke-width="2"/>` +
        `<path d="M5 20V14l2-6h10l2 6v6" fill="${c}" opacity="0.6"/>` +
        `<path d="M5 20V14l2-6h10l2 6v6" fill="none" stroke="${c}" stroke-width="1.5"/>`,

    camera: (c) =>
        `<rect x="2" y="7" width="16" height="12" rx="2" fill="${c}"/>` +
        `<path d="M18 11l4-2v8l-4-2v-4z" fill="${c}"/>` +
        `<circle cx="10" cy="13" r="3" fill="white" opacity="0.6"/>`,

    staging: (c) =>
        `<rect x="3" y="12" width="18" height="9" rx="1" fill="${c}"/>` +
        `<path d="M6 12V8M12 12V5M18 12V8" stroke="${c}" stroke-width="2.5" stroke-linecap="round"/>`,

    village: (c) =>
        `<path d="M2 20L8 8l4 6 4-8 6 14H2z" fill="${c}" opacity="0.8"/>` +
        `<path d="M2 20L8 8l4 6 4-8 6 14" fill="none" stroke="${c}" stroke-width="1.5" stroke-linejoin="round"/>`,

    exclusion: (c) =>
        `<circle cx="12" cy="12" r="10" fill="${c}" opacity="0.85"/>` +
        `<path d="M7 12h10" stroke="white" stroke-width="3.5" stroke-linecap="round"/>`,

    // ── Infrastructure ───────────────────────────────────────────────────────
    tent: (c) =>
        `<path d="M12 2L2 20h20L12 2z" fill="${c}"/>` +
        `<path d="M12 2L9 20h6L12 2z" fill="white" opacity="0.25"/>` +
        `<path d="M2 20h20" stroke="${c}" stroke-width="1.5"/>`,

    generator: (c) =>
        `<rect x="3" y="6" width="18" height="12" rx="2" fill="${c}"/>` +
        `<path d="M12 9l-3 4h4l-3 4" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" fill="none"/>`,

    toilet: (c) =>
        `<circle cx="8" cy="6" r="2.5" fill="${c}"/>` +
        `<circle cx="16" cy="6" r="2.5" fill="${c}"/>` +
        `<path d="M5 10h6v8h-2v3H5v-3H3v-8h2zM13 10h6v8h-2v3h-4v-3h-2v-8h2z" fill="${c}"/>`,

    stage_platform: (c) =>
        `<rect x="2" y="11" width="20" height="9" rx="1" fill="${c}"/>` +
        `<path d="M5 11V7M12 11V4M19 11V7" stroke="${c}" stroke-width="2.5" stroke-linecap="round"/>` +
        `<path d="M3 20h4v1.5H3zM10 20h4v1.5h-4zM17 20h4v1.5h-4z" fill="${c}" opacity="0.6"/>`,

    podium: (c) =>
        `<rect x="7" y="7" width="10" height="13" fill="${c}"/>` +
        `<rect x="1" y="12" width="8" height="8" fill="${c}" opacity="0.75"/>` +
        `<rect x="15" y="15" width="8" height="5" fill="${c}" opacity="0.55"/>` +
        `<path d="M10 7l2-3 2 3" fill="${c}"/>`,

    gantry: (c) =>
        `<rect x="2" y="15" width="20" height="3.5" rx="1" fill="${c}"/>` +
        `<rect x="2" y="5" width="3.5" height="13" fill="${c}"/>` +
        `<rect x="18.5" y="5" width="3.5" height="13" fill="${c}"/>` +
        `<path d="M10 15V9h4v6" stroke="white" stroke-width="1.5" fill="none" stroke-linecap="round"/>`,

    // ── Workforce ────────────────────────────────────────────────────────────
    person_vest: (c) =>
        `<circle cx="12" cy="5" r="3" fill="${c}"/>` +
        `<path d="M5 22c0-5 3.5-8 7-8s7 3 7 8" fill="${c}"/>` +
        `<path d="M9 14l1.5 4h3L15 14" fill="none" stroke="white" stroke-width="1.5" stroke-linejoin="round"/>`,

    shield: (c) =>
        `<path d="M12 2L4 6v7c0 5 4 9 8 10 4-1 8-5 8-10V6L12 2z" fill="${c}"/>`,

    badge_star: (c) =>
        `<path d="M12 2L4 6v7c0 5 4 9 8 10 4-1 8-5 8-10V6L12 2z" fill="${c}"/>` +
        `<polygon points="12,7 13.1,10.2 16.5,10.2 13.9,12.2 14.9,15.3 12,13.3 9.1,15.3 10.1,12.2 7.5,10.2 10.9,10.2" fill="white" opacity="0.9"/>`,

    flame: (c) =>
        `<path d="M12 2c-1.5 2.5-3 4.5-3 7.5a3 3 0 006 0c0-1-.3-2-1-3-.5 1.5-1 2.5-2 3-.8-1.5-.5-5 0-7.5z" fill="${c}"/>` +
        `<path d="M8 14c-1.5 1-2 2.5-2 4a6 6 0 0012 0c0-3-2-5-3-6-.5 2-1.5 3.5-3 4-1-1.5-1.5-4-4-2z" fill="${c}"/>`,

    person_heart: (c) =>
        `<circle cx="12" cy="5" r="3" fill="${c}"/>` +
        `<path d="M5 22c0-5 3.5-8 7-8s7 3 7 8" fill="${c}"/>` +
        `<path d="M12 12c0 0-2-1.5-2-3a2 2 0 014 0c0 1.5-2 3-2 3z" fill="white"/>`,

    person_tie: (c) =>
        `<circle cx="12" cy="5" r="3" fill="${c}"/>` +
        `<path d="M5 22c0-5 3.5-8 7-8s7 3 7 8" fill="${c}"/>` +
        `<path d="M11 14l1 3 1-3-0.5-2.5h-1L11 14z" fill="white"/>`,

    // ── Entry & Access ───────────────────────────────────────────────────────
    gate_entry: (c) =>
        `<rect x="2" y="4" width="3.5" height="16" rx="1" fill="${c}"/>` +
        `<rect x="18.5" y="4" width="3.5" height="16" rx="1" fill="${c}"/>` +
        `<path d="M9 12l4-4M9 12l4 4" stroke="${c}" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"/>`,

    gate_exit: (c) =>
        `<rect x="2" y="4" width="3.5" height="16" rx="1" fill="${c}"/>` +
        `<rect x="18.5" y="4" width="3.5" height="16" rx="1" fill="${c}"/>` +
        `<path d="M15 12l-4-4M15 12l-4 4" stroke="${c}" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"/>`,

    ticket_check: (c) =>
        `<path d="M2 8v8a2 2 0 002 2h16a2 2 0 002-2V8a2 2 0 00-2-2H4a2 2 0 00-2 2z" fill="${c}"/>` +
        `<path d="M8 12l2.5 2.5L16 9" stroke="white" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"/>`,

    wristband: (c) =>
        `<circle cx="12" cy="12" r="9" fill="none" stroke="${c}" stroke-width="3.5"/>` +
        `<path d="M8.5 9.5c1-2 5-2 6 0" stroke="${c}" stroke-width="1.5" fill="none" stroke-linecap="round"/>`,

    accreditation: (c) =>
        `<rect x="5" y="2" width="14" height="20" rx="2" fill="${c}"/>` +
        `<circle cx="12" cy="8" r="3" fill="white" opacity="0.7"/>` +
        `<rect x="8" y="14" width="8" height="1.5" rx="0.7" fill="white" opacity="0.7"/>` +
        `<rect x="9" y="17" width="6" height="1.5" rx="0.7" fill="white" opacity="0.5"/>`,

    // ── Annotations ──────────────────────────────────────────────────────────
    text_label: (c) =>
        `<rect x="4" y="5" width="16" height="3" rx="1.5" fill="${c}"/>` +
        `<rect x="10.5" y="5" width="3" height="14" rx="1.5" fill="${c}"/>`,

    // ── Infrastructure expansions ─────────────────────────────────────────────
    water_drop: (c) =>
        `<path d="M12 3 C8 8 5 12 5 15.5 a7 7 0 0 0 14 0 C19 12 16 8 12 3Z" fill="${c}"/>`,

    food_stall: (c) =>
        `<path d="M5 14 Q12 5 19 14Z" fill="${c}" opacity="0.85"/>` +
        `<rect x="3" y="14" width="18" height="2.5" rx="1" fill="${c}"/>` +
        `<rect x="11" y="16.5" width="2" height="4" fill="${c}" opacity="0.6"/>`,

    bar_tent: (c) =>
        `<rect x="7" y="8" width="9" height="8" rx="1.5" fill="${c}"/>` +
        `<path d="M15.5 11 Q19 11 19 14.5 Q19 17 16 17" stroke="${c}" stroke-width="2" fill="none" stroke-linecap="round"/>` +
        `<rect x="5" y="17" width="14" height="2" rx="1" fill="${c}"/>`,

    banner_sign: (c) =>
        `<rect x="5" y="3" width="14" height="11" rx="1.5" fill="${c}"/>` +
        `<rect x="11" y="14" width="2" height="7" rx="1" fill="${c}" opacity="0.7"/>`,

    bike_rack: (c) =>
        `<circle cx="7" cy="16" r="3.5" stroke="${c}" stroke-width="2" fill="none"/>` +
        `<circle cx="17" cy="16" r="3.5" stroke="${c}" stroke-width="2" fill="none"/>` +
        `<path d="M8.5 12.5 L12 7 L15.5 12.5" stroke="${c}" stroke-width="1.8" fill="none" stroke-linecap="round" stroke-linejoin="round"/>` +
        `<circle cx="12" cy="7" r="1.5" fill="${c}"/>`,
}

// ── Helper: full SVG string ───────────────────────────────────────────────────

export function iconSvgString(iconKey, color, size = 24) {
    const inner = (elementIcons[iconKey] || elementIcons.dot)(color)
    return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="${size}" height="${size}">${inner}</svg>`
}

// ── Helper: data-URL for use in <img src> ─────────────────────────────────────

export function iconDataUrl(iconKey, color, size = 24) {
    return 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(iconSvgString(iconKey, color, size))
}

// ── Helper: HTMLImageElement for MapLibre addImage() ─────────────────────────

export function iconImage(iconKey, color, size = 48) {
    return new Promise((resolve, reject) => {
        const img = new Image(size, size)
        img.onload = () => resolve(img)
        img.onerror = reject
        img.src = iconDataUrl(iconKey, color, size)
    })
}
