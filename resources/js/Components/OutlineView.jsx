import { Link } from '@inertiajs/react';
import { Badge } from '../design-system';
import { chartGen, highlightRuns, matchesAll } from '../lib/format';

function Hl({ text, toks }) {
    return highlightRuns(text, toks).map((r, i) => (r.hit ? <mark key={i}>{r.text}</mark> : <span key={i}>{r.text}</span>));
}

/** Walk the visible tree depth-first (collapsed branches have no kids here). */
function lines(node, collapsed, out = []) {
    const isCollapsed = collapsed.has(node.id) && node.nKids > 0;
    out.push({ ...node, collapsed: node.collapsed || isCollapsed });
    if (!isCollapsed) node.kids.forEach((k) => lines(k, collapsed, out));
    return out;
}

/** Search counts: matches shown, and matches hidden in collapsed or deeper branches. */
export function outlineCounts(data, collapsed, toks) {
    if (!toks.length) return null;
    const shown = lines(data.root, collapsed);
    const visibleIds = new Set(shown.map((l) => l.id));
    const hayOf = (id) => data.search[id] ?? '';
    const hits = shown.filter((l) => matchesAll(hayOf(l.id), toks)).length;
    const hidden = Object.keys(data.search).filter((id) => !visibleIds.has(+id) && matchesAll(hayOf(id), toks)).length;
    return { hits, hidden };
}

/** The outline rendering of the one descendants query (screen 9). */
export default function OutlineView({ data, collapsed, opts, toks, onToggle }) {
    const people = data.people;
    const rootClan = data.rootPerson.clan_id;

    return lines(data.root, collapsed).map((n) => {
        const p = people[n.id];
        const g = chartGen(data.rootPerson.generation, n.d, opts.relative);
        const toggle = n.nKids ? (
            n.cut ? (
                <span className="otg cut" title="Deeper than the generations shown" aria-hidden="true">
                    ⋯
                </span>
            ) : (
                <button type="button" className="otg" aria-expanded={!n.collapsed} aria-label={`${n.collapsed ? 'Expand' : 'Collapse'} ${p.name}`} onClick={() => onToggle(n.id)}>
                    {n.collapsed ? '▸' : '▾'}
                </button>
            )
        ) : (
            <span className="otg none" />
        );
        return (
            <div key={n.id} className="oln" style={{ paddingLeft: 8 + n.d * 26 }}>
                {toggle}
                <span className="ogen">{g == null ? '' : g}</span>
                <span className="opos">{n.d > 0 ? `${p.sibling_order}.` : ''}</span>
                {opts.photos && (p.portrait ? <img className="othumb" src={p.portrait} alt="" /> : <span className="othumb" aria-hidden="true" />)}
                <span style={{ flexGrow: 1, minWidth: 0 }}>
                    <Link className="onm" href={`/people/${p.id}`}>
                        <Hl text={p.name} toks={toks} />
                        {p.nickname && (
                            <span className="nick">
                                {' “'}
                                <Hl text={p.nickname} toks={toks} />”
                            </span>
                        )}
                    </Link>
                    {p.clan_id !== rootClan && (
                        <>
                            {' '}
                            <span title="Member of this clan">
                                <Badge>{p.clan_label}</Badge>
                            </span>
                        </>
                    )}
                    {opts.dates && p.span && <span className="odt"> {p.span}</span>}
                    {p.is_living && (
                        <>
                            {' '}
                            <Badge tone="living">Living</Badge>
                        </>
                    )}
                    {n.spouses.length > 0 && (
                        <span className="osp">
                            {' = '}
                            {n.spouses.map((s, i) => {
                                const sp = people[s.id];
                                return (
                                    <span key={s.id}>
                                        {i > 0 && ', '}
                                        <Link href={`/people/${sp.id}`}>
                                            <Hl text={sp.name + (sp.nickname ? ` “${sp.nickname}”` : '')} toks={toks} />
                                        </Link>
                                        {sp.clan_id !== p.clan_id && <span className="small muted"> ({sp.clan_label})</span>}
                                    </span>
                                );
                            })}
                        </span>
                    )}
                </span>
                {n.total > 0 && (
                    <span className="ocount">
                        {p.is_subclan_head && (
                            <span style={{ marginRight: 6 }}>
                                <Badge tone="lineage">{p.subclan_name || 'Subclan'}</Badge>
                            </span>
                        )}
                        {p.name} — {n.total}
                        {n.cut ? ' more below' : ' in this chart'}
                    </span>
                )}
            </div>
        );
    });
}
