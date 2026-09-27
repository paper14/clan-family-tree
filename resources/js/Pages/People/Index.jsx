import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import { Badge, Button } from '../../design-system';
import { NameNick } from '../../Components/ui';
import { fold, tokens } from '../../lib/format';

const SORTS = {
    gen: (a, b) => (a.numbered === b.numbered ? 0 : a.numbered ? -1 : 1) || (a.g ?? 999) - (b.g ?? 999) || a.fam - b.fam || a.name.localeCompare(b.name),
    last: (a, b) => (a.last_name || '~').localeCompare(b.last_name || '~') || a.given_name.localeCompare(b.given_name),
    given: (a, b) => a.given_name.localeCompare(b.given_name),
    recent: (a, b) => b.id - a.id,
};

/** How many unplaced people the "To place" panel lists before pointing to the paged list. */
const TO_PLACE = 10;

/** 1 … 4 5 6 … 20: first, last, and two either side of the current page (null = a gap). */
function pageNumbers(current, pages) {
    const out = [];
    for (let n = 1; n <= pages; n++) {
        if (n === 1 || n === pages || Math.abs(n - current) <= 2) out.push(n);
        else if (out[out.length - 1] !== null) out.push(null);
    }
    return out;
}

/** People list (screen 4). Membership: this clan's members only. */
export default function PeopleIndex({ clan, people, total, root, heads, unplaced }) {
    const [q, setQ] = useState('');
    const [gen, setGen] = useState('all');
    const [show, setShow] = useState('all');
    const [sort, setSort] = useState('gen');

    const maxGen = people.reduce((m, p) => Math.max(m, p.g == null ? -1 : p.g), 0);
    const genOpts = [];
    for (let g = root ? 0 : 1; g <= maxGen; g++) genOpts.push(g);

    const list = useMemo(() => {
        const toks = tokens(q);
        const out = people.filter((p) => {
            if (toks.length) {
                const hay = fold(`${p.name} ${p.nickname || ''} ${p.subclan_name || ''}`);
                if (!toks.every((t) => hay.includes(t))) return false;
            }
            if (gen === 'none' && p.g != null) return false;
            if (gen !== 'all' && gen !== 'none' && p.g !== +gen) return false;
            if (show === 'clan' && p.g == null) return false;
            if (show === 'unplaced' && !p.unplaced) return false;
            if (show === 'married' && !p.marriedIn) return false;
            if (show === 'living' && !p.living) return false;
            if (show === 'heads' && !p.head) return false;
            return true;
        });
        return out.sort(SORTS[sort]);
    }, [people, q, gen, show, sort]);

    // Paged in the browser, so search and filters stay instant; any filter change goes back to page 1.
    const [page, setPage] = useState(1);
    const [perPage, setPerPage] = useState(50);
    useEffect(() => setPage(1), [q, gen, show, sort, perPage, root?.id]);
    const pages = Math.max(1, Math.ceil(list.length / perPage));
    const current = Math.min(page, pages);
    const from = (current - 1) * perPage;
    const shown = list.slice(from, from + perPage);
    const goTo = (n) => {
        setPage(n);
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    const setRoot = (id) => {
        setGen('all');
        router.get('/people', id ? { root: id } : {}, { preserveState: true, preserveScroll: true, replace: true });
    };

    /** Show in tree: this person as the start; view, depth and numbering are kept (planning.md §3.1). */
    const showInTree = (id) => router.visit(`/tree?start=${id}`);

    return (
        <>
            <Head title="People" />
            <div className="page-head">
                <div className="grow">
                    <h1 className="display">People</h1>
                    <div className="small muted">
                        {total} in the {clan.label} · {unplaced.length} unplaced
                    </div>
                </div>
                <Link className="cl-btn" href="/children">
                    Add children
                </Link>
                <Link className="cl-btn cl-btn-primary" href="/people/create">
                    Add person
                </Link>
            </div>
            <div style={{ display: 'flex', gap: 32, alignItems: 'flex-start', flexWrap: 'wrap' }}>
                <section style={{ flex: '1 1 640px', minWidth: 0 }}>
                    <form className="filters" role="search" onSubmit={(e) => e.preventDefault()}>
                        <div className="cl-field" style={{ flex: '1 1 100%' }}>
                            <label className="cl-label" htmlFor="f-root">
                                Numbering
                            </label>
                            <select className="cl-input" id="f-root" value={root?.id ?? ''} onChange={(e) => setRoot(e.target.value)}>
                                <option value="">Whole clan — Gen 1 is the founding couple</option>
                                {heads.map((h) => (
                                    <option key={h.id} value={h.id}>
                                        {h.label}
                                    </option>
                                ))}
                            </select>
                            {root ? (
                                <span className="cl-hint">Showing only {root.name}’s line, numbered from them.</span>
                            ) : heads.length ? null : (
                                <span className="cl-hint">Mark a person as a subclan head to number a branch from its own head.</span>
                            )}
                        </div>
                        <div className="cl-field" style={{ flex: '1 1 220px' }}>
                            <label className="cl-label" htmlFor="f-q">
                                Search names
                            </label>
                            <input className="cl-input" id="f-q" type="search" value={q} onChange={(e) => setQ(e.target.value)} placeholder="Given, last or nickname" autoComplete="off" />
                        </div>
                        <div className="cl-field" style={{ width: 160 }}>
                            <label className="cl-label" htmlFor="f-gen">
                                Generation
                            </label>
                            <select className="cl-input" id="f-gen" value={gen} onChange={(e) => setGen(e.target.value)}>
                                <option value="all">All</option>
                                {genOpts.map((g) => (
                                    <option key={g} value={g}>
                                        Gen {g}
                                        {!root && g === 1 ? ' — founders' : root && g === 0 ? ' — head' : ''}
                                    </option>
                                ))}
                                {!root && <option value="none">No generation</option>}
                            </select>
                        </div>
                        <div className="cl-field" style={{ width: 170 }}>
                            <label className="cl-label" htmlFor="f-show">
                                Show
                            </label>
                            <select className="cl-input" id="f-show" value={show} onChange={(e) => setShow(e.target.value)}>
                                <option value="all">Everyone</option>
                                <option value="clan">Numbered</option>
                                <option value="unplaced">Unplaced only</option>
                                <option value="married">Married in</option>
                                <option value="living">Living</option>
                                <option value="heads">Subclan heads</option>
                            </select>
                        </div>
                        <div className="cl-field" style={{ width: 220 }}>
                            <label className="cl-label" htmlFor="f-sort">
                                Sort by
                            </label>
                            <select className="cl-input" id="f-sort" value={sort} onChange={(e) => setSort(e.target.value)}>
                                <option value="gen">Generation, then family order</option>
                                <option value="last">Last name</option>
                                <option value="given">Given name</option>
                                <option value="recent">Recently added</option>
                            </select>
                        </div>
                    </form>

                    {list.length ? (
                        <>
                            <div className="list">
                                <div className="lrow lhead">
                                    <span style={{ textAlign: 'right' }}>Gen{root ? '*' : ''}</span>
                                    <span>Name · link</span>
                                    <span>Dates</span>
                                    <span />
                                </div>
                                {shown.map((p) => (
                                    <div key={p.id} className="lrow">
                                        <span className="g">{p.g == null ? '–' : p.g}</span>
                                        <span style={{ minWidth: 0 }}>
                                            <span className="n">
                                                <Link href={`/people/${p.id}`}>
                                                    <NameNick name={p.name} nickname={p.nickname} />
                                                </Link>
                                            </span>
                                            <br />
                                            <span className="p">{p.sub}</span>
                                        </span>
                                        <span className="d">{p.span}</span>
                                        <span className="f">
                                            {p.unplaced && <Badge tone="warn">Unplaced</Badge>}
                                            {p.founder && <Badge tone="gilt">Founder</Badge>}
                                            {p.head && (
                                                <span title={p.subclan_name || ''}>
                                                    <Badge tone="lineage">Subclan head</Badge>
                                                </span>
                                            )}
                                            {p.marriedIn && <Badge>Married in</Badge>}
                                            {p.living && <Badge tone="living">Living</Badge>}
                                            {p.numbered && (
                                                <button type="button" className="cl-btn cl-btn-link cl-btn-sm tr" onClick={() => showInTree(p.id)}>
                                                    Show in tree
                                                </button>
                                            )}
                                        </span>
                                    </div>
                                ))}
                            </div>
                            {pages > 1 && (
                                <nav className="pager" aria-label="Pages">
                                    <Button className="cl-btn-sm" disabled={current === 1} onClick={() => goTo(current - 1)}>
                                        Previous
                                    </Button>
                                    {pageNumbers(current, pages).map((n, i) =>
                                        n === null ? (
                                            <span key={`gap${i}`} className="muted" aria-hidden="true">
                                                …
                                            </span>
                                        ) : (
                                            <Button
                                                key={n}
                                                className="cl-btn-sm"
                                                variant={n === current ? 'primary' : 'quiet'}
                                                aria-current={n === current ? 'page' : undefined}
                                                onClick={() => goTo(n)}
                                            >
                                                {n}
                                            </Button>
                                        ),
                                    )}
                                    <Button className="cl-btn-sm" disabled={current === pages} onClick={() => goTo(current + 1)}>
                                        Next
                                    </Button>
                                    <span style={{ flexGrow: 1 }} />
                                    <label className="small muted" htmlFor="f-per">
                                        Per page
                                    </label>
                                    <select className="cl-input" id="f-per" style={{ width: 'auto' }} value={perPage} onChange={(e) => setPerPage(+e.target.value)}>
                                        {[25, 50, 100, 200].map((n) => (
                                            <option key={n} value={n}>
                                                {n}
                                            </option>
                                        ))}
                                    </select>
                                </nav>
                            )}
                            <p className="small muted">
                                Showing {pages > 1 ? `${from + 1}–${from + shown.length} of ` : ''}
                                {list.length === total || pages === 1 ? `${list.length}${pages > 1 ? '' : ` of ${total}`}` : `${list.length} matching (${total} in the clan)`}.
                                {root ? ` *Gen numbered from ${root.name} as Gen 0; their absolute numbers are unchanged.` : ''}
                            </p>
                        </>
                    ) : (
                        <div className="card">
                            <p className="muted" style={{ margin: 0 }}>
                                Nobody matches. Clear the search or filters.
                            </p>
                        </div>
                    )}
                </section>

                <aside style={{ flex: '0 1 300px', display: 'flex', flexDirection: 'column', gap: 16 }}>
                    <div className="card" style={{ padding: 20 }}>
                        <div className="row">
                            <h2 style={{ flexGrow: 1 }}>To place</h2>
                            <Badge tone="warn">{unplaced.length} unplaced</Badge>
                        </div>
                        <p className="small muted" style={{ margin: 0 }}>
                            Typed in, no parent linked yet. They join the tree as soon as a clan-line parent is set.
                        </p>
                        {unplaced.length ? (
                            unplaced.slice(0, TO_PLACE).map((p) => (
                                <div key={p.id} className="row" style={{ borderTop: '1px solid var(--rule)', paddingTop: 8, flexWrap: 'nowrap' }}>
                                    <div style={{ flexGrow: 1, minWidth: 0 }}>
                                        <Link href={`/people/${p.id}`} className="serif" style={{ fontWeight: 600, fontSize: 17, color: 'var(--ink)', textDecoration: 'none' }}>
                                            <NameNick name={p.name} nickname={p.nickname} />
                                        </Link>
                                        <div className="small muted">{p.birth || 'no dates'}</div>
                                    </div>
                                    <Link className="small" style={{ fontWeight: 600, whiteSpace: 'nowrap' }} href={`/people/${p.id}/edit#parents`}>
                                        Link parent
                                    </Link>
                                </div>
                            ))
                        ) : (
                            <p className="small muted" style={{ margin: 0 }}>
                                Everyone is placed.
                            </p>
                        )}
                        {unplaced.length > TO_PLACE && (
                            <Button
                                variant="link"
                                className="cl-btn-sm"
                                style={{ alignSelf: 'flex-start', paddingLeft: 0 }}
                                onClick={() => {
                                    setShow('unplaced');
                                    window.scrollTo({ top: 0, behavior: 'smooth' });
                                }}
                            >
                                and {unplaced.length - TO_PLACE} more — show them all in the list
                            </Button>
                        )}
                    </div>
                    <div className="box small muted">
                        <b style={{ color: 'var(--ink)' }}>Married in</b>
                        Spouses with no parents in this clan are not counted as unplaced. A spouse from another clan stays in their own clan and appears here
                        only on the tree.
                    </div>
                </aside>
            </div>
        </>
    );
}
