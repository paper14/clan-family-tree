import TreeChart, { layoutTree, NODE_W } from './TreeChart';
import { caption, chartGen, sheetTitle } from '../lib/format';

export const PX_PER_MM = 96 / 25.4;
export const MARGIN_MM = 10;

export const PRESETS = {
    tarp: ['Tarpaulin 8 × 4 ft (landscape)', 2438, 1219],
    a3l: ['A3 landscape', 420, 297],
    a3p: ['A3 portrait', 297, 420],
    a4l: ['A4 landscape', 297, 210],
    a4p: ['A4 portrait', 210, 297],
    custom: ['Custom', null, null],
};

/** The group photo at the head: the clan's for a full-clan sheet, the subclan's for a subclan sheet. */
export function headPhoto(data, P) {
    if (!P.headPhoto) return null;
    if (data.start.is_founder) return data.headPhotos.clan;
    if (data.start.is_subclan_head) return data.headPhotos.subclan;
    return null;
}

/** Every printout's caption (planning.md §4.1), plus the living and archive notes. */
export function printCaption(data, P) {
    return (
        caption({ clan: data.clan, start: data.start, relative: P.numbering === 'relative' }) +
        (P.redact ? ' · living people: name only' : '') +
        (P.hidden ? ' · ARCHIVE COPY, includes hidden links' : '')
    );
}

/** Size and scale of the printed chart on the sheet (ported from the prototype). */
export function chartMetrics(data, P) {
    const layout = layoutTree(data.root);
    const hp = headPhoto(data, P);
    const pad = 24;
    const hpW = 560;
    const hpH = hp ? Math.round(hpW * ((hp.height || 2) / (hp.width || 3))) : 0;
    const titleH = 84 + (hp ? hpH + 44 : 0);
    const chartW = Math.max(layout.w, 700) + pad * 2;
    const chartH = layout.h + titleH + pad * 2 + 24;
    const availW = (P.w - 2 * MARGIN_MM) * PX_PER_MM;
    const availH = (P.h - 2 * MARGIN_MM) * PX_PER_MM;
    const scale = P.fit ? Math.min(4, availW / chartW, availH / chartH) : 1;
    const nodeMm = (NODE_W * scale) / PX_PER_MM;
    const fits = chartW * scale <= availW + 1 && chartH * scale <= availH + 1;
    return { layout, hp, pad, hpW, hpH, titleH, chartW, chartH, availW, availH, scale, nodeMm, fits, across: Math.floor((P.w - 2 * MARGIN_MM) / 45) };
}

/** The tree chart as it prints: head photo, title, caption, chart — at chart scale 1 (the caller scales). */
export function ChartSheet({ data, P, m }) {
    const relative = P.numbering === 'relative';
    return (
        <div style={{ position: 'relative', width: m.chartW, height: m.chartH }}>
            <div style={{ position: 'absolute', left: 0, top: m.pad, width: m.chartW, textAlign: 'center' }}>
                {m.hp && (
                    <>
                        <img src={m.hp.url} alt="" style={{ width: m.hpW, height: m.hpH, objectFit: 'cover', display: 'block', margin: '0 auto' }} />
                        <div style={{ font: 'italic 12px/16px var(--font-sans)', color: 'var(--ink-muted)', margin: '6px 0 14px' }}>{m.hp.caption}</div>
                    </>
                )}
                <div style={{ font: '600 30px/36px var(--font-serif)' }}>{sheetTitle({ clan: data.clan, start: data.start, relative })}</div>
                <div style={{ font: 'italic 13px/18px var(--font-sans)', color: 'var(--ink-muted)', marginTop: 4 }}>{printCaption(data, P)}</div>
            </div>
            <div style={{ position: 'absolute', left: m.pad + (m.chartW - 2 * m.pad - m.layout.w) / 2, top: m.titleH + m.pad }}>
                <TreeChart data={data} layout={m.layout} opts={{ print: true, dates: P.dates, photos: P.photos, redact: P.redact, hidden: P.hidden, relative }} />
            </div>
        </div>
    );
}

/** The indented outline on A4: one line per person, siblings in recorded order. */
export function OutlineSheet({ data, P }) {
    const relative = P.numbering === 'relative';
    const hp = headPhoto(data, P);
    const lines = [];
    (function walk(n) {
        lines.push(n);
        n.kids.forEach(walk);
    })(data.root);
    const people = data.people;
    const dt = (p) => (!P.dates || (P.redact && p.is_living) ? '' : p.span);

    return (
        <div className="outline-print">
            {hp && (
                <figure style={{ margin: '0 0 10pt' }}>
                    <img src={hp.url} alt="" style={{ maxWidth: '100%', maxHeight: '95mm', display: 'block', margin: '0 auto' }} />
                    <figcaption style={{ fontSize: '9pt', color: 'var(--ink-muted)', textAlign: 'center', marginTop: '3pt' }}>{hp.caption}</figcaption>
                </figure>
            )}
            <h1>{sheetTitle({ clan: data.clan, start: data.start, relative })}</h1>
            <div className="meta">{printCaption(data, P)} · one line per person, siblings in recorded order</div>
            {lines.map((l) => {
                const p = people[l.id];
                const g = chartGen(data.rootPerson.generation, l.d, relative);
                const more = (l.collapsed || l.cut) && l.total ? ` [${l.total} more below, printed separately]` : '';
                const fam = P.photos ? data.familyPhotos?.[l.id] || [] : [];
                return (
                    <div key={l.id}>
                        <div className="ln" style={{ paddingLeft: `${l.d * 18}pt` }}>
                            <span className="gn">{g == null ? '' : g}</span>
                            <span>
                                {l.d > 0 ? `${p.sibling_order}. ` : ''}
                                <b>{p.name}</b>
                                {p.nickname ? ` “${p.nickname}”` : ''}
                                {p.clan_id !== data.rootPerson.clan_id ? ` (${p.clan_label})` : ''}
                                {dt(p) && <span className="dd"> {dt(p)}</span>}
                                {l.spouses.length > 0 &&
                                    ` = ${l.spouses
                                        .map((s) => {
                                            const sp = people[s.id];
                                            return sp.name + (sp.nickname ? ` “${sp.nickname}”` : '') + (sp.clan_id !== p.clan_id ? ` (${sp.clan_label})` : '');
                                        })
                                        .join(', ')}`}
                                {P.hidden && p.hidden_link && <i> (hidden link: {p.hidden_link})</i>}
                                {more && <span className="dd">{more}</span>}
                            </span>
                        </div>
                        {fam.length > 0 && (
                            <div style={{ paddingLeft: `${l.d * 18 + 22}pt` }}>
                                {fam.map((ph) => (
                                    <figure key={ph.url} style={{ margin: '4pt 0 8pt', breakInside: 'avoid' }}>
                                        <img src={ph.url} alt="" style={{ width: '62mm', display: 'block' }} />
                                        <figcaption style={{ fontSize: '8.5pt', color: 'var(--ink-muted)', marginTop: '2pt' }}>{ph.caption || 'Family picture'}</figcaption>
                                    </figure>
                                ))}
                            </div>
                        )}
                    </div>
                );
            })}
        </div>
    );
}

/** Query string for the one descendants query, with the print options as parameters. */
export function printParams(P) {
    return new URLSearchParams({
        start: P.start,
        depth: P.gens,
        hidden: P.hidden ? 1 : 0,
        collapsed: (P.collapsed || []).join(','),
    });
}

/** All options, for the print route's URL. */
export function sheetUrl(P) {
    const q = printParams(P);
    for (const k of ['format', 'numbering', 'w', 'h']) q.set(k, P[k]);
    for (const k of ['fit', 'dates', 'photos', 'redact']) q.set(k, P[k] ? 1 : 0);
    q.set('headphoto', P.headPhoto ? 1 : 0);
    q.set('auto', 1);
    return `/print/sheet?${q}`;
}
