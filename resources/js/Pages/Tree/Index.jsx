import { Head, router } from '@inertiajs/react';
import { Fragment, useEffect, useLayoutEffect, useMemo, useRef, useState } from 'react';
import { Button } from '../../design-system';
import { GroupedOptions } from '../../Components/ui';
import TreeChart, { layoutTree } from '../../Components/TreeChart';
import OutlineView, { outlineCounts } from '../../Components/OutlineView';
import { caption, tokens } from '../../lib/format';
import { handOff, readPref, writePref } from '../../lib/prefs';

const DEPTHS = [2, 3, 4, 5, 6, 7, 8, 10];
const DEFAULTS = { view: 'tree', depth: 3, numbering: 'clan', dates: true, photos: false };

function findOption(groups, id) {
    for (const g of groups) for (const o of g.options) if (o.id === id) return o;
    return null;
}

/**
 * Tree / Outline (screen 9). Both views render the one descendants query, so they can't
 * disagree; switching keeps the start, depth, numbering and collapsed branches.
 */
export default function TreeIndex({ clan, startOptions, defaultStart, requestedStart }) {
    const [prefs, setPrefs] = useState(() => {
        // ?view=outline&depth=8 in the address overrides the remembered choice (bookmarkable).
        const url = new URLSearchParams(window.location.search);
        const p = readPref('tree', DEFAULTS);
        if (['tree', 'outline'].includes(url.get('view'))) p.view = url.get('view');
        if (DEPTHS.includes(+url.get('depth'))) p.depth = +url.get('depth');
        return p;
    });
    const { view, depth, numbering, dates, photos } = prefs;
    const setPref = (k, v) => setPrefs((p) => ({ ...p, [k]: v }));
    useEffect(() => writePref('tree', prefs), [prefs]);

    const [start, setStart] = useState(() => {
        if (requestedStart) return requestedStart;
        const saved = readPref(`tree-start-${clan.id}`, { id: null }).id;
        return saved && findOption(startOptions, saved) ? saved : defaultStart;
    });
    const [selected, setSelected] = useState(requestedStart);
    const [collapsed, setCollapsed] = useState(() => new Set());
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(false);
    const [zoom, setZoom] = useState(null);
    const [q, setQ] = useState('');
    const wrap = useRef();

    // Show in tree lands on that person, keeping view, depth and numbering (planning.md §3.1).
    useEffect(() => {
        if (requestedStart) {
            setStart(requestedStart);
            setSelected(requestedStart);
            setCollapsed(new Set());
            setZoom(null);
        }
    }, [requestedStart]);

    useEffect(() => writePref(`tree-start-${clan.id}`, { id: start }), [start, clan.id]);

    // One fetch per start or depth change; every other control is client-side.
    useEffect(() => {
        if (!start) return;
        const ctl = new AbortController();
        setLoading(true);
        fetch(`/tree/data?${new URLSearchParams({ start, depth })}`, { headers: { Accept: 'application/json' }, signal: ctl.signal })
            .then((r) => r.json())
            .then((d) => {
                setData(d);
                setLoading(false);
            })
            .catch(() => {});
        return () => ctl.abort();
    }, [start, depth]);

    const layout = useMemo(() => (data?.root ? layoutTree(data.root, collapsed) : null), [data, collapsed]);

    useLayoutEffect(() => {
        if (view !== 'tree' || !layout || zoom != null || !wrap.current) return;
        const z = Math.max(0.3, Math.min(1, (wrap.current.clientWidth - 64) / layout.w));
        setZoom(Math.floor(z * 10) / 10);
    }, [layout, zoom, view]);

    if (!start) {
        return (
            <>
                <Head title="Tree" />
                <h1 className="display">Tree</h1>
                <div className="card">
                    <p style={{ margin: 0 }}>
                        Nobody in the {clan.label} is numbered yet. Set the founding couple in <a href={`/clans/${clan.id}/settings`}>Clan settings</a>, then
                        add their children.
                    </p>
                </div>
            </>
        );
    }

    const relative = numbering === 'relative';
    const toggle = (id) =>
        setCollapsed((c) => {
            const n = new Set(c);
            if (n.has(id)) n.delete(id);
            else n.add(id);
            return n;
        });

    const pickStart = (id) => {
        const o = findOption(startOptions, id);
        setStart(id);
        setSelected(null);
        setCollapsed(new Set());
        setZoom(null);
        if (o?.head) setPref('numbering', 'relative');
        else if (o?.founder) setPref('numbering', 'clan');
    };

    const fit = () => {
        const w = wrap.current;
        if (!w || !layout) return;
        setZoom(Math.max(0.2, Math.min(1, (w.clientWidth - 64) / layout.w, (w.clientHeight - 64) / layout.h)));
    };
    const zoomBy = (dz) => setZoom((z) => Math.max(0.2, Math.min(2, Math.round(((z || 1) + dz * 0.1) * 10) / 10)));

    const printThis = () => {
        handOff('print', { start, gens: depth, dates, photos, format: view, numbering, collapsed: [...collapsed] });
        router.visit('/print');
    };

    const st = data?.start;
    const toks = tokens(q);
    const counts = data && view === 'outline' ? outlineCounts(data, collapsed, toks) : null;
    const opts = { dates, photos, selected, relative };

    return (
        <>
            <Head title="Tree / Outline" />
            <div className="page-head">
                <div className="grow">
                    <nav className="crumbs" aria-label="Ancestors">
                        {(data?.ancestors || []).map((x) => (
                            <Fragment key={x.id}>
                                <a
                                    href="#"
                                    onClick={(e) => {
                                        e.preventDefault();
                                        pickStart(x.id);
                                    }}
                                >
                                    {x.name}
                                </a>
                                <span aria-hidden="true">›</span>
                            </Fragment>
                        ))}
                    </nav>
                    <h1 className="display">{st ? (st.is_founder ? `The ${clan.label}` : `Descendants of ${st.name}`) : ' '}</h1>
                    <div className="caption">{st ? caption({ clan, start: st, relative, withDate: false }) : ''}</div>
                </div>
                <div className="seg" role="group" aria-label="View">
                    {['tree', 'outline'].map((v) => (
                        <button key={v} type="button" className={view === v ? 'on' : ''} aria-pressed={view === v} style={{ padding: '0 16px' }} onClick={() => setPref('view', v)}>
                            {v === 'tree' ? 'Tree' : 'Outline'}
                        </button>
                    ))}
                </div>
                <Button onClick={printThis}>Print this view</Button>
            </div>

            <form className="filters" onSubmit={(e) => e.preventDefault()}>
                <div className="cl-field" style={{ width: 320 }}>
                    <label className="cl-label" htmlFor="t-start">
                        Start from
                    </label>
                    <select className="cl-input" id="t-start" value={start} onChange={(e) => pickStart(+e.target.value)}>
                        <GroupedOptions groups={startOptions} />
                    </select>
                </div>
                <div className="cl-field" style={{ width: 290 }}>
                    <label className="cl-label" htmlFor="t-num">
                        Numbering
                    </label>
                    <select className="cl-input" id="t-num" value={numbering} onChange={(e) => setPref('numbering', e.target.value)}>
                        <option value="clan">From the clan founders (Gen 1)</option>
                        <option value="relative">Start person is Gen 0</option>
                    </select>
                </div>
                <fieldset className="cl-field" style={{ border: 0, padding: 0, margin: 0 }}>
                    <legend className="cl-label">Generations shown</legend>
                    <div className="seg">
                        {DEPTHS.map((n) => (
                            <button
                                key={n}
                                type="button"
                                className={depth === n ? 'on' : ''}
                                aria-pressed={depth === n}
                                onClick={() => {
                                    setPref('depth', n);
                                    setZoom(null);
                                }}
                            >
                                {n}
                            </button>
                        ))}
                    </div>
                </fieldset>
                <label className="check" style={{ alignSelf: 'center' }}>
                    <input type="checkbox" checked={dates} onChange={(e) => setPref('dates', e.target.checked)} />
                    Dates
                </label>
                <label className="check" style={{ alignSelf: 'center' }}>
                    <input type="checkbox" checked={photos} onChange={(e) => setPref('photos', e.target.checked)} />
                    Photos
                </label>
                {collapsed.size > 0 && (
                    <Button
                        className="cl-btn-sm"
                        style={{ alignSelf: 'center' }}
                        onClick={() => {
                            setCollapsed(new Set());
                            setZoom(null);
                        }}
                    >
                        Expand all ({collapsed.size} collapsed)
                    </Button>
                )}
                <span style={{ flexGrow: 1 }} />
                {view === 'tree' && (
                    <div className="row" style={{ gap: 4 }}>
                        <Button className="cl-btn-icon" aria-label="Zoom out" onClick={() => zoomBy(-1)}>
                            −
                        </Button>
                        <span className="small muted" style={{ minWidth: 48, textAlign: 'center' }}>
                            {zoom ? `${Math.round(zoom * 100)}%` : ''}
                        </span>
                        <Button className="cl-btn-icon" aria-label="Zoom in" onClick={() => zoomBy(1)}>
                            +
                        </Button>
                        <Button onClick={fit}>Fit</Button>
                    </div>
                )}
            </form>

            {view === 'tree' ? (
                <>
                    <section className="tree-wrap" aria-label="Descendant chart" aria-busy={loading} ref={wrap}>
                        {data && layout && zoom != null && (
                            <div style={{ position: 'relative', margin: '0 auto', width: layout.w * zoom + 64, height: layout.h * zoom + 64 }}>
                                <div style={{ position: 'absolute', left: 32, top: 32, transform: `scale(${zoom})`, transformOrigin: '0 0' }}>
                                    <TreeChart data={data} layout={layout} opts={opts} onToggle={toggle} />
                                </div>
                            </div>
                        )}
                    </section>
                    <div className="legend">
                        <span className="row" style={{ gap: 8 }}>
                            <svg width="28" height="10" aria-hidden="true">
                                <path d="M0 5H28" stroke="var(--lineage)" strokeWidth="1.5" />
                            </svg>
                            Clan line
                        </span>
                        <span className="row" style={{ gap: 8 }}>
                            <span className="serif" style={{ color: 'var(--spouse)', fontSize: 17 }}>
                                =
                            </span>
                            Married
                        </span>
                        <span className="row" style={{ gap: 8 }}>
                            <span style={{ width: 20, height: 14, border: '2px solid var(--lineage)', borderRadius: 2 }} />
                            Clan member
                        </span>
                        <span className="row" style={{ gap: 8 }}>
                            <span style={{ width: 20, height: 14, border: '1px solid var(--rule-strong)', borderRadius: 2 }} />
                            Married in · or from another clan
                        </span>
                        <span>Siblings run left to right, first child leftmost, whatever clan each child belongs to. − collapses a branch.</span>
                    </div>
                </>
            ) : (
                <>
                    <section className="card" style={{ padding: '16px 20px', gap: 12 }} aria-label="Outline" aria-busy={loading}>
                        <div className="row" style={{ alignItems: 'flex-end' }}>
                            <div className="cl-field" style={{ flex: '1 1 320px' }}>
                                <label className="cl-label" htmlFor="o-q">
                                    Find in outline
                                </label>
                                <input className="cl-input" id="o-q" type="search" value={q} onChange={(e) => setQ(e.target.value)} placeholder="Name, nickname or spouse" autoComplete="off" />
                            </div>
                            <span className="small muted" style={{ paddingBottom: 10 }}>
                                {counts ? `${counts.hits} match${counts.hits === 1 ? '' : 'es'} shown${counts.hidden ? ` · ${counts.hidden} more in collapsed or deeper branches` : ''}` : ''}
                            </span>
                        </div>
                        <div>{data?.root && <OutlineView data={data} collapsed={collapsed} opts={opts} toks={toks} onToggle={toggle} />}</div>
                    </section>
                    <p className="small muted">
                        Gen, then position among siblings, then name. Counts are people in this chart — the boxes that will print — including descendants who are
                        members of another clan. Gen is counted along the line from the starting person.
                    </p>
                </>
            )}
        </>
    );
}
