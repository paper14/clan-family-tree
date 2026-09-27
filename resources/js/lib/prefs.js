// Per-browser conveniences only (the view you last used, depth, numbering…). Storage can be
// blocked or cleared, so every read and write is wrapped and the app works without it.

export function readPref(key, fallback) {
    try {
        const raw = window.localStorage.getItem(`clan:${key}`);
        return raw == null ? fallback : { ...fallback, ...JSON.parse(raw) };
    } catch {
        return fallback;
    }
}

export function writePref(key, value) {
    try {
        window.localStorage.setItem(`clan:${key}`, JSON.stringify(value));
    } catch {
        /* storage unavailable: nothing to do */
    }
}

/** One-shot hand-off between pages (e.g. "Print this view" → Print). */
export function handOff(key, value) {
    try {
        window.sessionStorage.setItem(`clan:${key}`, JSON.stringify(value));
    } catch {
        /* ignore */
    }
}

export function takeHandOff(key) {
    try {
        const raw = window.sessionStorage.getItem(`clan:${key}`);
        window.sessionStorage.removeItem(`clan:${key}`);
        return raw ? JSON.parse(raw) : null;
    } catch {
        return null;
    }
}
