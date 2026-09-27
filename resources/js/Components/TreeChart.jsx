import { Link } from '@inertiajs/react';
import { hierarchy, tree as d3tree } from 'd3-hierarchy';
import { chartGen } from '../lib/format';

// Mirrors the design tokens --node-width (176px), --node-gap (24px) and --generation-gap (72px).
export const NODE_W = 176;
export const NODE_H = 92;
export const JOIN_W = 52;
export const GAP = 24;
export const VGAP = 72;

/** Drop collapsed branches (they were still walked, so their counts are known). */
function visible(node, collapsed) {
    const isCollapsed = collapsed.has(node.id) && node.nKids > 0;
    return { ...node, collapsed: node.collapsed || isCollapsed, kids: isCollapsed ? [] : node.kids.map((k) => visible(k, collapsed)) };
}

/**
 * Lay out the descendants payload with d3-hierarchy. Each unit is the clan-line person plus
 * their spouse boxes; units are separated by their widths plus the node gap, siblings left to
 * right in sibling order (the order the query returned them in).
 */
export function layoutTree(root, collapsed = new Set()) {
    const data = visible(root, collapsed);
    const h = hierarchy(data, (n) => (n.kids.length ? n.kids : null));
    h.each((n) => {
        const s = n.data.spouses.length;
        n.uw = NODE_W * (1 + s) + JOIN_W * s;
    });
    d3tree()
        .nodeSize([1, NODE_H + VGAP])
        .separation((a, b) => a.uw / 2 + b.uw / 2 + GAP)(h);

    let minX = Infinity;
    let maxX = -Infinity;
    h.each((n) => {
        minX = Math.min(minX, n.x - n.uw / 2);
        maxX = Math.max(maxX, n.x + n.uw / 2);
    });
    const units = [];
    let maxY = 0;
    h.each((n) => {
        const x = n.x - n.uw / 2 - minX;
        units.push({ node: n.data, x, y: n.y, cx: x + NODE_W / 2, depth: n.depth, h: n });
        maxY = Math.max(maxY, n.y + NODE_H);
    });

    // Descent links leave the clan-line person's box, not the marriage join.
    const byNode = new Map(units.map((u) => [u.h, u]));
    const paths = [];
    for (const u of units) {
        const kids = u.h.children || [];
        if (!kids.length) continue;
        const bar = u.y + NODE_H + VGAP / 2;
        const centers = kids.map((k) => byNode.get(k).cx);
        const lo = Math.min(u.cx, ...centers);
        const hi = Math.max(u.cx, ...centers);
        let d = `M${u.cx} ${u.y + NODE_H}V${bar}M${lo} ${bar}H${hi}`;
        for (const c of centers) d += `M${c} ${bar}V${u.y + NODE_H + VGAP}`;
        paths.push(d);
    }

    const widest = {};
    for (const u of units) widest[u.depth] = (widest[u.depth] || 0) + 1 + u.node.spouses.length;

    return { units, paths, w: maxX - minX, h: maxY + 24, maxRow: Math.max(0, ...Object.values(widest)) };
}

function Box({ p, x, y, clanLine, gen, opts, rootClanId, founderStyle }) {
    const hideDates = opts.redact && p.is_living;
    const dt = opts.dates && !hideDates ? p.span : '';
    const living = !opts.print && p.is_living;
    const cls = `tn ${clanLine ? 'clan' : ''} ${founderStyle ? 'founder' : ''} ${opts.selected === p.id ? 'sel' : ''}`;
    const style = { left: x, top: y, width: NODE_W, height: NODE_H };
    const inner = (
        <>
            {opts.photos && (
                <span className="photo" aria-hidden="true">
                    {p.portrait ? <img src={p.portrait} alt="" /> : p.initials}
                </span>
            )}
            <span className="tx">
                {gen != null && <span className="gen">Gen {gen}</span>}
                <span className="nm">
                    {p.name}
                    {p.nickname && <span className="nick"> “{p.nickname}”</span>}
                </span>
                {(dt || living) && (
                    <span className="dt">
                        {dt}
                        {living && (
                            <>
                                {dt ? ' · ' : ''}
                                <b style={{ color: 'var(--living)' }}>Living</b>
                            </>
                        )}
                    </span>
                )}
                {p.clan_id !== rootClanId && <span className="oc">{p.clan_label}</span>}
                {opts.hidden && p.hidden_link && <span className="hid">hidden link: {p.hidden_link}</span>}
            </span>
        </>
    );
    return opts.print ? (
        <div className={cls} style={style}>
            {inner}
        </div>
    ) : (
        <Link className={cls} href={`/people/${p.id}`} style={style}>
            {inner}
        </Link>
    );
}

/**
 * The chart. `layout` comes from layoutTree(); `data` is the descendants payload.
 * opts: dates, photos, redact, hidden, print, selected, relative.
 */
export default function TreeChart({ data, layout, opts, onToggle }) {
    const people = data.people;
    const rootClanId = data.rootPerson.clan_id;
    const rootGen = data.rootPerson.generation;
    const out = [];

    for (const u of layout.units) {
        const n = u.node;
        const p = people[n.id];
        const g = chartGen(rootGen, n.d, opts.relative);
        out.push(<Box key={`p${n.id}`} p={p} x={u.x} y={u.y} clanLine gen={g} opts={opts} rootClanId={rootClanId} founderStyle={p.is_founder && p.clan_id === rootClanId} />);
        let x = u.x + NODE_W;
        for (const s of n.spouses) {
            const sp = people[s.id];
            const yr = s.marriage || '';
            out.push(
                <div key={`j${n.id}-${s.id}`} className="join" style={{ left: x, top: u.y, width: JOIN_W, height: NODE_H }} title="Married">
                    <svg width={JOIN_W} height="2" style={{ position: 'absolute', top: NODE_H / 2 - 12, left: 0 }} aria-hidden="true">
                        <path d={`M0 1H${JOIN_W}`} stroke="var(--spouse)" strokeWidth="1.5" />
                    </svg>
                    =
                    {opts.dates && yr && <span>{yr.length > 9 ? `${yr.slice(0, 9)}…` : yr}</span>}
                </div>,
            );
            x += JOIN_W;
            // A co-founder spouse box is framed and numbered (Gen 1); other spouse boxes show no generation.
            const cofounder = sp.is_founder && p.is_founder && sp.clan_id === rootClanId;
            out.push(<Box key={`s${n.id}-${s.id}`} p={sp} x={x} y={u.y} clanLine={cofounder} gen={cofounder ? g : null} opts={opts} rootClanId={rootClanId} founderStyle={cofounder} />);
            x += NODE_W;
        }
        const by = u.y + NODE_H + 4;
        if (n.nKids && !n.cut && !opts.print) {
            out.push(
                <button
                    key={`t${n.id}`}
                    type="button"
                    className="ttg"
                    style={{ left: u.cx - (n.collapsed ? 30 : 12), top: by, width: n.collapsed ? 60 : undefined }}
                    aria-expanded={!n.collapsed}
                    aria-label={`${n.collapsed ? 'Expand' : 'Collapse'} ${p.name}’s branch`}
                    onClick={() => onToggle?.(n.id)}
                >
                    {n.collapsed ? `+ ${n.total}` : '−'}
                </button>,
            );
        }
        if (n.cut || (n.collapsed && opts.print)) {
            out.push(
                <div key={`m${n.id}`} className="tmore" style={{ left: u.cx - 60, top: by, width: 120 }}>
                    {n.total} more below
                </div>,
            );
        }
    }

    return (
        <div className="tree" style={{ width: layout.w, height: layout.h }}>
            <svg width={layout.w} height={layout.h} viewBox={`0 0 ${layout.w} ${layout.h}`} aria-hidden="true">
                <path d={layout.paths.join('')} fill="none" stroke="var(--lineage)" strokeWidth="1.5" />
            </svg>
            {out}
        </div>
    );
}
