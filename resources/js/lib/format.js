// Display wording shared by the chart, outline and print. Ported from the prototype.

const MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

/** "27 September 2026" */
export function longDate(d = new Date()) {
    return `${d.getDate()} ${MONTHS[d.getMonth()]} ${d.getFullYear()}`;
}

/**
 * CHART generation: depth along the path walked from the starting person (planning.md §2.7).
 * Clan numbering = the start's stored generation + depth; relative numbering = depth alone
 * (the start is Gen 0). Never the same thing as a member's stored generation.
 */
export function chartGen(rootGeneration, depth, relative) {
    if (relative) return depth;
    return rootGeneration == null ? null : rootGeneration + depth;
}

/**
 * Every chart and printout captions its own numbering (planning.md §4.1):
 *   "Santos clan · Gen 1 = founding couple Isko and Sela · printed 27 September 2026"
 *   "Santos clan · subclan of Andres Santos (Gen 0) · printed …"
 */
export function caption({ clan, start, relative, withDate = true }) {
    const parts = [clan.label];
    if (relative) {
        parts.push(`${start.is_subclan_head ? 'subclan of ' : 'descendants of '}${start.name} (Gen 0)`);
    } else {
        if (!start.is_founder) parts.push(`branch of ${start.name}`);
        parts.push(`Gen 1 = founding couple ${clan.foundersText}`);
    }
    if (withDate) parts.push(`printed ${longDate()}`);
    return parts.join(' · ');
}

/** Sheet title: the subclan's name for a relative subclan sheet, "The Santos clan", or "Descendants of X". */
export function sheetTitle({ clan, start, relative }) {
    if (relative && start.is_subclan_head) return start.subclan_name || `Subclan of ${start.name}`;
    return start.is_founder ? `The ${clan.label}` : `Descendants of ${start.name}`;
}

/** Lower-case and strip accents, keeping one character per code point (so indexes line up). */
export function fold(text) {
    return Array.from(String(text ?? ''))
        .map((c) => {
            const f = c.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
            return f.length === 1 ? f : c.toLowerCase().charAt(0) || c;
        })
        .join('');
}

export function tokens(q) {
    return fold(q).trim().split(/\s+/).filter(Boolean);
}

/** AND-match every word, ignoring case and accents ("pena" finds "Peña"). */
export function matchesAll(haystack, toks) {
    if (!toks.length) return false;
    const h = fold(haystack);
    return toks.every((t) => h.includes(t));
}

/** Split text into [{text, hit}] runs, marking every token occurrence (accent-insensitive). */
export function highlightRuns(text, toks) {
    const chars = Array.from(String(text ?? ''));
    if (!toks.length || !chars.length) return [{ text: chars.join(''), hit: false }];
    const folded = fold(chars.join(''));
    const fchars = Array.from(folded);
    const hit = new Array(chars.length).fill(false);
    for (const t of toks) {
        const tl = Array.from(t).length;
        let from = 0;
        for (;;) {
            const i = folded.indexOf(t, from);
            if (i < 0) break;
            // convert UTF-16 index in folded to code point index
            const cp = Array.from(folded.slice(0, i)).length;
            for (let k = cp; k < cp + tl && k < fchars.length; k++) hit[k] = true;
            from = i + t.length;
        }
    }
    const runs = [];
    chars.forEach((c, i) => {
        const last = runs[runs.length - 1];
        if (last && last.hit === hit[i]) last.text += c;
        else runs.push({ text: c, hit: hit[i] });
    });
    return runs;
}
