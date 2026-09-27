import { Head } from '@inertiajs/react';
import { useEffect, useLayoutEffect, useRef, useState } from 'react';
import { Badge, Button } from '../../design-system';
import { GroupedOptions } from '../../Components/ui';
import { chartMetrics, ChartSheet, headPhoto, MARGIN_MM, OutlineSheet, printCaption, printParams, PRESETS, PX_PER_MM, sheetUrl } from '../../Components/PrintSheet';
import { takeHandOff } from '../../lib/prefs';

function findOption(groups, id) {
    for (const g of groups) for (const o of g.options) if (o.id === id) return o;
    return null;
}

const DEFAULTS = { format: 'tree', numbering: 'clan', gens: 3, preset: 'tarp', w: 2438, h: 1219, fit: true, dates: true, photos: false, headPhoto: true, redact: true, hidden: false, collapsed: [] };

/** Print (screen 10): options on the left, a live preview on the right. */
export default function PrintIndex({ clan, startOptions, defaultStart }) {
    const [P, setP] = useState(() => {
        const url = new URLSearchParams(window.location.search);
        const fromTree = takeHandOff('print');
        const P0 = { ...DEFAULTS, start: defaultStart };
        if (fromTree && findOption(startOptions, fromTree.start)) {
            // "Print this view" carries over start, depth, numbering, dates, photos, collapsed and format.
            return { ...P0, ...fromTree, collapsed: fromTree.collapsed || [] };
        }
        const requested = +url.get('start');
        if (requested && findOption(startOptions, requested)) {
            return { ...P0, start: requested, numbering: url.get('subclan') ? 'relative' : P0.numbering };
        }
        return P0;
    });
    const set = (k, v) => setP((p) => ({ ...p, [k]: v }));
    const [data, setData] = useState(null);
    const wrap = useRef();
    const [boxW, setBoxW] = useState(600);

    useLayoutEffect(() => {
        const measure = () => wrap.current && setBoxW(wrap.current.clientWidth - 40);
        measure();
        window.addEventListener('resize', measure);
        return () => window.removeEventListener('resize', measure);
    }, []);

    // The same descendants query as Tree / Outline; print options are its parameters.
    useEffect(() => {
        if (!P.start) return;
        const ctl = new AbortController();
        fetch(`/tree/data?${printParams(P)}`, { headers: { Accept: 'application/json' }, signal: ctl.signal })
            .then((r) => r.json())
            .then(setData)
            .catch(() => {});
        return () => ctl.abort();
    }, [P.start, P.gens, P.hidden, P.collapsed.join(',')]);

    if (!P.start) {
        return (
            <>
                <Head title="Print" />
                <h1 className="display">Print</h1>
                <div className="card">
                    <p style={{ margin: 0 }}>
                        Nobody in the {clan.label} is numbered yet. Set the founding couple first in <a href={`/clans/${clan.id}/settings`}>Clan settings</a>.
                    </p>
                </div>
            </>
        );
    }

    const tree = P.format === 'tree';
    const pickStart = (id) => {
        const o = findOption(startOptions, id);
        setP((p) => ({ ...p, start: id, collapsed: [], numbering: o?.head ? 'relative' : o?.founder ? 'clan' : p.numbering }));
    };
    const pickPreset = (k) => {
        const [, w, h] = PRESETS[k];
        setP((p) => ({ ...p, preset: k, ...(w ? { w, h } : {}) }));
    };
    const setSize = (k, v) => {
        setP((p) => {
            const n = { ...p, [k]: +v || p[k] };
            const pre = PRESETS[p.preset];
            if (p.preset !== 'custom' && (n.w !== pre[1] || n.h !== pre[2])) n.preset = 'custom';
            return n;
        });
    };
    const doPrint = () => window.open(sheetUrl(P), '_blank');

    let preview = null;
    let status = null;
    let note = null;
    if (data?.root) {
        if (tree) {
            const m = chartMetrics(data, P);
            const sw = P.w * PX_PER_MM;
            const sh = P.h * PX_PER_MM;
            const ps = Math.min(boxW / sw, 560 / sh);
            const mg = MARGIN_MM * PX_PER_MM;
            preview = (
                <div className="sheet" data-theme="print" style={{ width: sw * ps, height: sh * ps }}>
                    <div style={{ position: 'absolute', left: 0, top: 0, width: sw, height: sh, transform: `scale(${ps})`, transformOrigin: '0 0', background: 'var(--paper)', color: 'var(--ink)' }}>
                        <div style={{ position: 'absolute', left: mg, top: mg, transform: `scale(${m.scale})`, transformOrigin: '0 0' }}>
                            <ChartSheet data={data} P={P} m={m} />
                        </div>
                    </div>
                </div>
            );
            status = (
                <>
                    <span style={{ flexGrow: 1 }}>
                        Preview · {P.w} × {P.h} mm · widest row {m.layout.maxRow} boxes · sheet holds ≈{m.across} across at 45 mm
                    </span>
                    {!m.fits ? <Badge tone="danger">Doesn’t fit</Badge> : m.nodeMm < 44.5 ? <Badge tone="warn">Names below 45 mm</Badge> : <Badge tone="lineage">Fits</Badge>}
                </>
            );
            note = !m.fits
                ? 'The chart runs off the sheet. Tick “Scale to fill the sheet”, show fewer generations, or print a subclan from its own head.'
                : m.nodeMm < 44.5
                  ? `Scaled to fit: each name box is about ${m.nodeMm.toFixed(0)} mm wide — below the 45 mm needed to read comfortably. Show fewer generations or print subclans separately.`
                  : `Each name box prints about ${m.nodeMm.toFixed(0)} mm wide.`;
        } else {
            const pw = 210 * PX_PER_MM;
            const s = Math.min(1, boxW / pw);
            preview = (
                <div className="sheet" data-theme="print" style={{ width: pw * s, maxHeight: 900, overflow: 'auto' }}>
                    <div style={{ width: pw - 30 * PX_PER_MM, padding: 15 * PX_PER_MM, transform: `scale(${s})`, transformOrigin: '0 0', background: 'var(--paper)', color: 'var(--ink)' }}>
                        <OutlineSheet data={data} P={P} />
                    </div>
                </div>
            );
            status = 'Preview · A4 portrait · pages break automatically when printed';
            note = 'The outline is the practical form for the full record — one line per person.';
        }
    }

    return (
        <>
            <Head title="Print" />
            <div className="page-head">
                <div className="grow">
                    <h1 className="display">Print</h1>
                    <p className="muted" style={{ margin: 0 }}>
                        Every printout is for one clan — here the <b>{clan.label}</b>. Print the main chart from the founders, and each subclan from its own head.
                    </p>
                </div>
            </div>
            <div style={{ display: 'flex', gap: 32, alignItems: 'flex-start', flexWrap: 'wrap' }}>
                <form className="card" onSubmit={(e) => e.preventDefault()} style={{ flex: '0 0 380px', gap: 18 }}>
                    <fieldset className="cl-field" style={{ border: 0, padding: 0, margin: 0 }}>
                        <legend className="cl-label">Format</legend>
                        <div className="radios" style={{ flexDirection: 'column', gap: 0 }}>
                            <label>
                                <input type="radio" name="format" checked={tree} onChange={() => set('format', 'tree')} />
                                Tree chart
                            </label>
                            <label>
                                <input type="radio" name="format" checked={!tree} onChange={() => set('format', 'outline')} />
                                Indented outline (one line per person, A4)
                            </label>
                        </div>
                    </fieldset>
                    <div className="cl-field">
                        <label className="cl-label" htmlFor="p-start">
                            Starting person
                        </label>
                        <select className="cl-input" id="p-start" value={P.start} onChange={(e) => pickStart(+e.target.value)}>
                            <GroupedOptions groups={startOptions} />
                        </select>
                    </div>
                    <fieldset className="cl-field" style={{ border: 0, padding: 0, margin: 0 }}>
                        <legend className="cl-label">Generation numbering</legend>
                        <div className="radios" style={{ flexDirection: 'column', gap: 0 }}>
                            <label>
                                <input type="radio" name="numbering" checked={P.numbering === 'clan'} onChange={() => set('numbering', 'clan')} />
                                From the clan founders (founding couple = Gen 1)
                            </label>
                            <label>
                                <input type="radio" name="numbering" checked={P.numbering === 'relative'} onChange={() => set('numbering', 'relative')} />
                                Relative — starting person is Gen 0
                            </label>
                        </div>
                    </fieldset>
                    <div className="cl-field" style={{ width: 120 }}>
                        <label className="cl-label" htmlFor="p-gens">
                            Generations
                        </label>
                        <input className="cl-input" type="number" min="1" max="12" id="p-gens" value={P.gens} onChange={(e) => set('gens', Math.max(1, Math.min(12, +e.target.value || 3)))} />
                    </div>
                    {tree && (
                        <>
                            <div className="cl-field">
                                <label className="cl-label" htmlFor="p-preset">
                                    Sheet
                                </label>
                                <select className="cl-input" id="p-preset" value={P.preset} onChange={(e) => pickPreset(e.target.value)}>
                                    {Object.entries(PRESETS).map(([k, [label]]) => (
                                        <option key={k} value={k}>
                                            {label}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div className="grid2" style={{ gap: 12 }}>
                                <div className="cl-field">
                                    <label className="cl-sub" htmlFor="p-w">
                                        Width, mm
                                    </label>
                                    <input className="cl-input" type="number" id="p-w" value={P.w} onChange={(e) => setSize('w', e.target.value)} />
                                </div>
                                <div className="cl-field">
                                    <label className="cl-sub" htmlFor="p-h">
                                        Height, mm
                                    </label>
                                    <input className="cl-input" type="number" id="p-h" value={P.h} onChange={(e) => setSize('h', e.target.value)} />
                                </div>
                            </div>
                            <label className="check">
                                <input type="checkbox" checked={P.fit} onChange={(e) => set('fit', e.target.checked)} />
                                <span>
                                    Scale to fill the sheet
                                    <br />
                                    <span className="small muted">Enlarges a small chart, shrinks a large one.</span>
                                </span>
                            </label>
                        </>
                    )}
                    <div className="stack" style={{ gap: 6, borderTop: '1px solid var(--rule)', paddingTop: 14 }}>
                        <label className="check">
                            <input type="checkbox" checked={P.dates} onChange={(e) => set('dates', e.target.checked)} />
                            Show dates
                        </label>
                        <label className="check">
                            <input type="checkbox" checked={P.photos} onChange={(e) => set('photos', e.target.checked)} />
                            <span>
                                Show photos
                                <br />
                                <span className="small muted">{tree ? 'Main portrait on each box.' : 'Family pictures beside their family.'}</span>
                            </span>
                        </label>
                        <label className="check">
                            <input type="checkbox" checked={P.headPhoto} onChange={(e) => set('headPhoto', e.target.checked)} />
                            <span>
                                Group photo at the head
                                <br />
                                <span className="small muted">Clan group photo for the full clan, the subclan’s own photo for a subclan sheet.</span>
                            </span>
                        </label>
                        <label className="check">
                            <input type="checkbox" checked={P.redact} onChange={(e) => set('redact', e.target.checked)} />
                            <span>
                                Living people: name only
                                <br />
                                <span className="small muted">Hides dates, contact and residence.</span>
                            </span>
                        </label>
                    </div>
                    <label className="check" style={{ padding: 12, background: 'var(--warn-soft)', borderRadius: 'var(--radius-sm)' }}>
                        <input type="checkbox" checked={P.hidden} onChange={(e) => set('hidden', e.target.checked)} style={{ accentColor: 'var(--warn)' }} />
                        <span>
                            <b>Include hidden parent links</b>
                            <br />
                            <span className="small">Archive copy only — shows links the family asked to keep out of view.</span>
                        </span>
                    </label>
                    {P.collapsed.length > 0 && (
                        <div className="small muted">
                            {P.collapsed.length} collapsed branch{P.collapsed.length === 1 ? '' : 'es'} from the tree print as “more below”.{' '}
                            <Button variant="link" className="cl-btn-sm" onClick={() => set('collapsed', [])}>
                                Print them too
                            </Button>
                        </div>
                    )}
                    <Button variant="primary" onClick={doPrint} disabled={!data}>
                        Print or save as PDF
                    </Button>
                    <span className="small muted">Opens the sheet and the browser’s print dialog (Chrome or Edge). Choose “Save as PDF”, and turn on “Background graphics”.</span>
                </form>
                <section style={{ flex: '1 1 520px', minWidth: 0 }} className="stack" aria-label="Print preview">
                    <div className="caption">{data?.root ? `Caption: ${printCaption(data, P)}` : ''}</div>
                    <div className="row small muted">{status}</div>
                    <div className="sheet-wrap" ref={wrap}>
                        {preview}
                    </div>
                    <div className="small muted">{note}</div>
                    {data?.root && P.headPhoto && !headPhoto(data, P) && (data.start.is_founder || data.start.is_subclan_head) && (
                        <div className="small muted">
                            No {data.start.is_founder ? 'clan' : 'subclan'} group photo yet — add one in {data.start.is_founder ? 'Clan settings' : 'the head’s person page'}.
                        </div>
                    )}
                </section>
            </div>
        </>
    );
}
