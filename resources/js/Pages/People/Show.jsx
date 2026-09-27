import { Head, Link, router, usePage } from '@inertiajs/react';
import { Fragment, useState } from 'react';
import { Badge, Button } from '../../design-system';
import { Arrow, ErrorNote, NameNick, PersonCard, Photo } from '../../Components/ui';
import PhotoPanel from '../../Components/PhotoPanel';
import MarriageForm from '../../Components/MarriageForm';
import ChildRows, { blankRow, defaultLast, saveLabel, summary } from '../../Components/ChildRows';

/** Inline add children, from this person as the clan-line parent (screen 7). */
function InlineChildren({ inline, onClose }) {
    const { parent, spouses, defaultOther, existing } = inline;
    const [other, setOther] = useState(defaultOther ? String(defaultOther) : '');
    const otherP = spouses.find((s) => String(s.id) === other) || null;
    const newRow = () => blankRow(defaultLast(parent, otherP));
    const [rows, setRows] = useState(() => [newRow(), newRow(), newRow()]);
    const [errors, setErrors] = useState({});
    const [busy, setBusy] = useState(false);
    const count = existing[other] ?? 0;

    const changeOther = (v) => {
        setOther(v);
        const o = spouses.find((s) => String(s.id) === v) || null;
        setRows(rows.map((r) => (r.given_name ? r : { ...r, last_name: defaultLast(parent, o) })));
    };

    const save = () => {
        router.post(`/people/${parent.id}/children`, { other_parent_id: other, rows }, {
            preserveScroll: true,
            onStart: () => setBusy(true),
            onFinish: () => setBusy(false),
            onError: setErrors,
            onSuccess: onClose,
        });
    };

    return (
        <section className="card" style={{ marginTop: 24 }} aria-label={`Add children of ${parent.name}`}>
            <div className="row">
                <h2 style={{ flexGrow: 1 }}>Add children of {parent.name}</h2>
                <div className="cl-field" style={{ width: 320 }}>
                    <label className="cl-label" htmlFor="k-other">
                        Other parent (optional)
                    </label>
                    <select className="cl-input" id="k-other" value={other} onChange={(e) => changeOther(e.target.value)}>
                        <option value="">No other parent recorded</option>
                        {spouses.map((s) => (
                            <option key={s.id} value={s.id}>
                                {s.name}
                                {s.clan_id !== parent.clan_id ? ` (${s.clan_label})` : ''}
                            </option>
                        ))}
                    </select>
                </div>
            </div>
            <p className="small muted" style={{ margin: 0 }}>
                Type them first to last — row order is sibling order. A child known only by nickname is fine: put it in the nickname box.
            </p>
            <ErrorNote errors={errors} />
            <ChildRows rows={rows} setRows={setRows} compact existing={count} relations={[]} newRow={newRow} />
            <div className="row" style={{ flexWrap: 'nowrap' }}>
                <p className="small muted" style={{ flex: '1 1 auto', minWidth: 0, margin: 0 }}>
                    {summary(parent, otherP, count)}
                </p>
                <Button onClick={onClose}>Cancel</Button>
                <Button variant="primary" onClick={save} disabled={busy}>
                    {saveLabel(rows)}
                </Button>
            </div>
        </section>
    );
}

/** Person detail (screen 6). Descent is never clan-scoped: every child shows, whatever their clan. */
export default function PersonShow({ person: p, clan, line, facts, clanParent, otherParent, marriages, childGroups, childCount, photoPanels, inline }) {
    const { errors } = usePage().props;
    const [mOpen, setMOpen] = useState(null); // null | 'new' | marriage id
    const [kidsOpen, setKidsOpen] = useState(false);

    const move = (id, dir) => router.post(`/people/${id}/move`, { dir }, { preserveScroll: true });
    const showInTree = () => router.visit(`/tree?start=${p.id}`);
    const relLabel = (r) => (r ? ` · ${r}` : '');

    const badges = (
        <>
            {p.generation != null && <span className="gen">Gen {p.generation}</span>}
            {p.isFounder ? <Badge tone="gilt">Founding couple</Badge> : p.generation != null ? <Badge tone="lineage">Clan line</Badge> : null}
            {p.is_subclan_head && <Badge tone="lineage">Subclan head{p.subclan_name ? ` · ${p.subclan_name}` : ''}</Badge>}
            {p.unplaced && <Badge tone="warn">Unplaced</Badge>}
            {p.marriedIn && <Badge>Married in</Badge>}
            {p.is_living && <Badge tone="living">Living</Badge>}
        </>
    );

    let parents = [];
    if (clanParent) {
        parents.push(
            <div key="cp" className="stack" style={{ gap: 6 }}>
                <span className="small" style={{ fontWeight: 600, color: 'var(--lineage)' }}>
                    Clan-line parent · {clanParent.role}
                    {relLabel(clanParent.relation)}
                </span>
                <PersonCard person={clanParent} clanLine />
            </div>,
        );
    }
    if (otherParent && !p.hide_second_parent) {
        parents.push(
            <div key="op" className="stack" style={{ gap: 6 }}>
                <span className="small muted" style={{ fontWeight: 600 }}>
                    Other parent · {otherParent.role}
                    {relLabel(otherParent.relation)}
                    {otherParent.other_clan ? ` · from the ${otherParent.clan_label}` : !otherParent.numbered ? ' · married in' : ''}
                </span>
                <PersonCard person={otherParent} clanLine={!otherParent.other_clan && otherParent.numbered} />
            </div>,
        );
    }
    if (otherParent && p.hide_second_parent) {
        parents.push(
            <p key="hid" className="small muted" style={{ margin: 0 }}>
                Other parent hidden at the family’s request. <Link href={`/people/${p.id}/edit#parents`}>Show in form</Link>
            </p>,
        );
    }
    if (!clanParent && !otherParent) {
        parents = [
            p.isFounder ? (
                <p key="f" className="muted" style={{ margin: 0 }}>
                    One of the founding couple of the {clan?.label}.
                </p>
            ) : p.marriedIn ? (
                <p key="m" className="muted" style={{ margin: 0 }}>
                    Married into the clan — parents are not recorded here.
                </p>
            ) : (
                <div key="u" className="note warn">
                    <b>Unplaced.</b> Link a clan-line parent to put this person in the tree. <Link href={`/people/${p.id}/edit#parents`}>Link parent</Link>
                </div>
            ),
        ];
    }
    if (p.isFounder && clanParent) {
        parents.push(
            <div key="fp" className="note info small">
                <b>Founder with parents.</b> To count generations from {clanParent.name} instead, use <Link href={`/clans/${clan.id}/settings`}>Set founding couple</Link>.
            </div>,
        );
    }
    if (p.parentage_note) {
        parents.push(
            <p key="pn" style={{ margin: 0 }} className="small">
                <span className="muted">Parentage note:</span> “{p.parentage_note}”
            </p>,
        );
    }

    const marriageRows = marriages.map((m) =>
        mOpen === m.id ? (
            <MarriageForm key={m.id} person={{ ...p, clan_label: clan?.label }} marriage={m} onClose={() => setMOpen(null)} />
        ) : (
            <div key={m.id} className="row marriage">
                {m.familyPhoto && <img src={m.familyPhoto} alt="Family picture" style={{ width: 44, height: 44, objectFit: 'cover', borderRadius: 'var(--radius-sm)' }} />}
                <span aria-hidden="true" className="serif" style={{ fontSize: 22, color: 'var(--spouse)' }}>
                    =
                </span>
                <div style={{ flexGrow: 1, minWidth: 0 }}>
                    {m.spouse ? (
                        <>
                            <Link href={`/people/${m.spouse.id}`} className="serif" style={{ fontWeight: 600, fontSize: 17 }}>
                                <NameNick name={m.spouse.name} nickname={m.spouse.nickname} />
                            </Link>
                            {m.spouse.other_clan && (
                                <>
                                    {' '}
                                    <Badge>{m.spouse.clan_label}</Badge>
                                </>
                            )}
                        </>
                    ) : (
                        <span className="muted">Spouse not recorded</span>
                    )}
                    <div className="small muted">{[`m. ${m.when || '?'}`, m.place].filter(Boolean).join(', ')}</div>
                </div>
                <Badge>{m.status}</Badge>
                <Button className="cl-btn-sm" onClick={() => setMOpen(m.id)}>
                    Edit
                </Button>
            </div>
        ),
    );

    return (
        <>
            <Head title={p.name} />
            <nav className="crumbs" aria-label="Line of descent" style={{ marginBottom: 16 }}>
                <span>{clan?.label}</span>
                <span aria-hidden="true">·</span>
                {line.map((x, i) => (
                    <Fragment key={x.id}>
                        {i > 0 && <span aria-hidden="true">›</span>}
                        {i === line.length - 1 ? (
                            <span style={{ color: 'var(--ink)', fontWeight: 600 }}>{x.name}</span>
                        ) : (
                            <Link href={`/people/${x.id}`}>{x.name}</Link>
                        )}
                    </Fragment>
                ))}
            </nav>
            <div className="page-head" style={{ alignItems: 'flex-end' }}>
                <Photo person={p} large />
                <div className="grow">
                    <div className="row" style={{ gap: 8 }}>
                        {badges}
                    </div>
                    <h1 className="display">
                        {p.name}
                        {p.nickname && <span style={{ fontWeight: 400, fontStyle: 'italic', fontSize: 28 }}> “{p.nickname}”</span>}
                    </h1>
                    <div className="muted">{[p.life, p.occupation].filter(Boolean).join(' · ')}</div>
                    {p.sibLine && <div className="small muted">{p.sibLine}</div>}
                </div>
                {p.is_subclan_head && p.generation != null && (
                    <Link className="cl-btn" href={`/print?start=${p.id}&subclan=1`}>
                        Print subclan
                    </Link>
                )}
                {p.generation != null && (
                    <Button variant="link" onClick={showInTree}>
                        Show in tree
                    </Button>
                )}
                <Link className="cl-btn cl-btn-primary" href={`/people/${p.id}/edit`}>
                    Edit
                </Link>
            </div>
            <ErrorNote errors={errors} />

            <div className="grid3" style={{ gap: 24, alignItems: 'start' }}>
                <section className="card">
                    <h2>Record</h2>
                    {facts.length > 0 && (
                        <dl className="facts">
                            {facts.map(([k, v]) => (
                                <Fragment key={k}>
                                    <dt>{k}</dt>
                                    <dd>{v}</dd>
                                </Fragment>
                            ))}
                        </dl>
                    )}
                    {p.notes && (
                        <div style={{ borderTop: '1px solid var(--rule)', paddingTop: 16 }}>
                            <div className="small" style={{ fontWeight: 600 }}>
                                Notes
                            </div>
                            <p style={{ margin: '4px 0 0', whiteSpace: 'pre-line' }}>{p.notes}</p>
                        </div>
                    )}
                </section>

                <section className="card">
                    <h2>Parents</h2>
                    {parents}
                    <div className="row" style={{ marginTop: 8 }}>
                        <h2 style={{ flexGrow: 1 }}>Marriages</h2>
                        {mOpen === null && (
                            <Button className="cl-btn-sm" onClick={() => setMOpen('new')}>
                                Add marriage
                            </Button>
                        )}
                    </div>
                    {marriageRows}
                    {mOpen === 'new' && <MarriageForm person={{ ...p, clan_label: clan?.label }} marriage={null} onClose={() => setMOpen(null)} />}
                    {!marriages.length && mOpen !== 'new' && (
                        <p className="small muted" style={{ margin: 0 }}>
                            None recorded.
                        </p>
                    )}
                </section>

                <section className="card">
                    <div className="row">
                        <h2 style={{ flexGrow: 1 }}>Children · {childCount}</h2>
                        {!kidsOpen && (
                            <Button className="cl-btn-sm" onClick={() => setKidsOpen(true)}>
                                Add children
                            </Button>
                        )}
                    </div>
                    {childGroups.length ? (
                        <>
                            {childGroups.map((g) => {
                                const xc = g.kids.filter((k) => k.other_clan).length;
                                const n = g.kids.length;
                                return (
                                    <div key={g.key} className="stack" style={{ gap: 8 }}>
                                        <span className="small muted">
                                            {!g.other ? 'no other parent recorded' : g.hidden ? 'other parent hidden' : `with ${g.other.name}${g.other.other_clan ? ` (${g.other.clan_label})` : ''}`}
                                            {xc > 0 && (
                                                <span style={{ color: 'var(--spouse)' }}>
                                                    {' '}
                                                    · {xc === n ? 'all' : `${xc} of ${n}`} in another clan — Gen shown is from their own clan’s founders
                                                </span>
                                            )}
                                        </span>
                                        {g.kids.map((k, i) => (
                                            <div key={k.id} className="sibrow">
                                                <span className="pos" title="Position among siblings">
                                                    {k.sibling_order}
                                                </span>
                                                <div style={{ flexGrow: 1, minWidth: 0 }}>
                                                    <PersonCard person={k} clanLine={k.clanLine} />
                                                </div>
                                                {n > 1 && (
                                                    <div className="mv">
                                                        <Button className="cl-btn-sm" aria-label={`Move ${k.name} earlier`} disabled={i === 0} onClick={() => move(k.id, -1)}>
                                                            <Arrow up />
                                                        </Button>
                                                        <Button className="cl-btn-sm" aria-label={`Move ${k.name} later`} disabled={i === n - 1} onClick={() => move(k.id, 1)}>
                                                            <Arrow />
                                                        </Button>
                                                    </div>
                                                )}
                                            </div>
                                        ))}
                                    </div>
                                );
                            })}
                            <p className="small muted" style={{ margin: 0 }}>
                                First child at the top. Use the arrows to fix the order — dates are never used to sort.
                            </p>
                        </>
                    ) : (
                        <p className="small muted" style={{ margin: 0 }}>
                            None recorded.
                        </p>
                    )}
                </section>
            </div>

            {kidsOpen && <InlineChildren inline={inline} onClose={() => setKidsOpen(false)} />}

            <section className="card" style={{ marginTop: 24 }} aria-label="Photos">
                <h2>Photos</h2>
                {photoPanels.map((panel) => (
                    <PhotoPanel key={`${panel.kind}-${panel.type}-${panel.id}`} panel={panel} clanId={p.clan_id} />
                ))}
            </section>
        </>
    );
}
