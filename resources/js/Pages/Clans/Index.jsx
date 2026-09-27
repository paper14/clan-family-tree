import { Head, Link, router } from '@inertiajs/react';
import { Button } from '../../design-system';

/** Clan list — the landing screen (planning.md §3, screen 1). */
export default function ClansIndex({ clans, deleted }) {
    const open = (id) => router.post('/current-clan', { clan_id: id, to: 'open' });

    return (
        <>
            <Head title="Clans" />
            <div className="page-head">
                <div className="grow">
                    <h1 className="display">Clans</h1>
                    <div className="small muted">
                        Each clan is its own registry with its own founding couple, tree and printouts. Clans are never combined.
                    </div>
                </div>
                <Link className="cl-btn cl-btn-primary" href="/clans/create">
                    Add clan
                </Link>
            </div>

            {clans.length ? (
                <div className="clancards">
                    {clans.map((c) => (
                        <article key={c.id} className={`clancard ${c.current ? 'cur' : ''}`}>
                            {c.banner && <img className="clan-banner" src={c.banner.url} alt={c.banner.caption || 'Clan group photo'} />}
                            <div>
                                <div className="nm">{c.label}</div>
                                <div className="small muted">{c.origin_place}</div>
                            </div>
                            <div className="small">
                                <span className="muted">Founding couple (Gen 1): </span>
                                <span className="serif" style={{ fontWeight: 600 }}>
                                    {c.foundersText}
                                </span>
                            </div>
                            <div className="stats">
                                <span>
                                    <b>{c.people}</b>people
                                </span>
                                <span>
                                    <b>{c.generations}</b>generations
                                </span>
                                <span>
                                    <b>{c.subclans}</b>subclans
                                </span>
                                {c.unplaced > 0 && (
                                    <span>
                                        <b>{c.unplaced}</b>unplaced
                                    </span>
                                )}
                            </div>
                            {c.crossMarriages > 0 && (
                                <div className="small muted">
                                    {c.crossMarriages} marriage{c.crossMarriages > 1 ? 's' : ''} with other clans
                                </div>
                            )}
                            {c.someUnnumbered && (
                                <div className="note warn small">
                                    <b>Some people aren’t numbered.</b> Their line doesn’t reach the founding couple yet.
                                </div>
                            )}
                            <div className="row">
                                <Button variant="primary" onClick={() => open(c.id)}>
                                    Open
                                </Button>
                                <Link className="cl-btn" href={`/clans/${c.id}/settings`}>
                                    Settings
                                </Link>
                            </div>
                        </article>
                    ))}
                </div>
            ) : (
                <div className="card">
                    <p style={{ margin: 0 }}>No clans yet. Add the first one to start typing in records.</p>
                </div>
            )}

            {deleted.length > 0 && (
                <section style={{ marginTop: 40 }} className="stack">
                    <h2 style={{ fontSize: 18 }}>Deleted clans</h2>
                    {deleted.map((c) => (
                        <div key={c.id} className="row card" style={{ flexDirection: 'row', padding: 16 }}>
                            <span className="serif" style={{ fontWeight: 600, flexGrow: 1 }}>
                                {c.label}{' '}
                                <span className="small muted">
                                    · {c.people} people · deleted {new Date(c.deleted_at).toLocaleDateString()}
                                </span>
                            </span>
                            <Button className="cl-btn-sm" onClick={() => router.post(`/clans/${c.id}/restore`)}>
                                Restore
                            </Button>
                        </div>
                    ))}
                </section>
            )}
        </>
    );
}
