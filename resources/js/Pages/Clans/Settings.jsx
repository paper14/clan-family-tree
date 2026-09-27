import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { Button, Field } from '../../design-system';
import { ErrorNote, Options } from '../../Components/ui';
import PhotoPanel from '../../Components/PhotoPanel';

/** Clan settings (screen 2): details, Set founding couple with its preview, group photos, subclans, delete. */
export default function ClanSettings({ clan, founderOptions, chosen, spouseOptions, preview, heads, photos }) {
    const { errors } = usePage().props;
    const form = useForm({ name: clan.name, origin_place: clan.origin_place || '', notes: clan.notes || '' });
    const [applying, setApplying] = useState(false);

    const saveDetails = (e) => {
        e.preventDefault();
        form.put(`/clans/${clan.id}`, { preserveScroll: true });
    };

    // Picking a founder (or spouse) reloads the preview; nothing changes until "Back up, then …".
    const choose = (founder, spouse) => {
        const q = { founder: founder || 0 };
        if (spouse !== undefined) q.spouse = spouse || 0;
        router.get(`/clans/${clan.id}/settings`, q, { preserveScroll: true, preserveState: true, replace: true, only: ['chosen', 'spouseOptions', 'preview'] });
    };

    const apply = () => {
        setApplying(true);
        router.post(`/clans/${clan.id}/founders`, { founder_id: chosen.founder, spouse_id: chosen.spouse || '' }, { preserveScroll: true, onFinish: () => setApplying(false) });
    };

    const del = () => {
        if (window.confirm(`Delete the ${clan.label}? Its ${clan.people} people go with it. You can restore it from the clan list.`)) {
            router.delete(`/clans/${clan.id}`);
        }
    };

    return (
        <div className="stack" style={{ maxWidth: 780, gap: 24 }}>
            <Head title={`${clan.label} — settings`} />
            <header className="stack" style={{ gap: 4 }}>
                <Link className="small" href="/">
                    ‹ Clans
                </Link>
                <h1 className="display">{clan.label}</h1>
                <p className="muted" style={{ margin: 0 }}>
                    {clan.people} people · founding couple {clan.foundersText}
                </p>
            </header>
            <ErrorNote errors={errors} keys={['name', 'founders', 'spouse_id']} />

            <form className="card" onSubmit={saveDetails} noValidate>
                <h2>Details</h2>
                <div className="grid2">
                    <Field label="Clan name" required value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} className={form.errors.name ? 'cl-field-error' : ''} />
                    <Field label="Origin place" value={form.data.origin_place} onChange={(e) => form.setData('origin_place', e.target.value)} />
                </div>
                <Field label="Notes" multiline style={{ minHeight: 120 }} value={form.data.notes} onChange={(e) => form.setData('notes', e.target.value)} hint="Oral history, sources, and previous founding couples." />
                <div className="row">
                    <span style={{ flexGrow: 1 }} />
                    <Button type="submit" variant="primary" disabled={form.processing}>
                        Save details
                    </Button>
                </div>
            </form>

            <section className="card" id="founders">
                <h2>Founding couple</h2>
                <p className="small muted" style={{ margin: 0 }}>
                    Gen 1 is counted from here. When research finds the founders’ own parents, add them as people, link the current founder to them as a
                    parent, then set them here.
                </p>
                <div className="grid2">
                    <div className="cl-field cl-field-clan">
                        <label className="cl-label" htmlFor="f-founder">
                            Founder
                        </label>
                        <select className="cl-input" id="f-founder" value={chosen.founder ?? ''} onChange={(e) => choose(e.target.value)}>
                            <option value="">— Not set —</option>
                            <Options options={founderOptions} />
                        </select>
                    </div>
                    <div className="cl-field">
                        <label className="cl-label" htmlFor="f-spouse">
                            Founder’s spouse
                        </label>
                        <select className="cl-input" id="f-spouse" value={chosen.spouse ?? ''} disabled={!chosen.founder} onChange={(e) => choose(chosen.founder, e.target.value)}>
                            <option value="">— None —</option>
                            <Options options={spouseOptions} />
                        </select>
                        <span className="cl-hint">Only people married to the founder are listed.</span>
                    </div>
                </div>
                {preview && (
                    <div className="panel-warn" role="alert">
                        <b style={{ color: 'var(--warn)' }}>This renumbers the whole {clan.label}.</b>
                        <ul>
                            <li>
                                {preview.changed} of {preview.total} people get a new generation number; {preview.numbered} will be numbered in total.
                            </li>
                            {preview.old && (
                                <li>
                                    {preview.old.name} moves from Gen {preview.old.from ?? '–'} to {preview.old.to == null ? 'unnumbered' : `Gen ${preview.old.to}`}.
                                </li>
                            )}
                            {preview.lost > 0 && (
                                <li>
                                    <b>
                                        {preview.lost} {preview.lost === 1 ? 'person would lose their number' : 'people would lose their number'}
                                    </b>{' '}
                                    — their line doesn’t reach {preview.founderName}. Link the old founders to the new couple first.
                                </li>
                            )}
                            {preview.founderHasParents && <li>{preview.founderName} still has parents recorded — they will sit above Gen 1 unnumbered.</li>}
                            <li>The previous founding couple is written into the clan notes with today’s date.</li>
                            <li>Subclan sheets numbered from their own head are not affected.</li>
                        </ul>
                        <p className="small" style={{ margin: 0 }}>
                            <b>A backup of all clans is taken automatically</b> just before renumbering.
                        </p>
                        <div className="row">
                            <Button className="cl-btn-sm" onClick={() => choose(clan.founder_id, clan.founder_spouse_id)}>
                                Keep the current couple
                            </Button>
                            <Button variant="danger" onClick={apply} disabled={applying}>
                                {applying ? 'Backing up…' : 'Back up, then set founding couple and renumber'}
                            </Button>
                        </div>
                    </div>
                )}
            </section>

            <section className="card">
                <h2>Clan group photos</h2>
                <p className="small muted" style={{ margin: 0 }}>
                    One per reunion is typical. The main one heads every full-clan printout.
                </p>
                <PhotoPanel panel={{ ...photos, title: 'Clan group' }} clanId={clan.id} />
            </section>

            <section className="card">
                <h2>Subclans</h2>
                {heads.length ? (
                    heads.map((h) => (
                        <div key={h.id} className="row" style={{ borderTop: '1px solid var(--rule)', paddingTop: 8 }}>
                            <span style={{ flexGrow: 1 }}>
                                <Link className="serif" style={{ fontWeight: 600 }} href={`/people/${h.id}`}>
                                    {h.name}
                                </Link>{' '}
                                <span className="small muted">
                                    {h.subclan_name || ''} · Gen {h.generation} · {h.people} people
                                </span>
                            </span>
                            <Link className="cl-btn cl-btn-sm" href={`/print?start=${h.id}&subclan=1`}>
                                Print subclan
                            </Link>
                        </div>
                    ))
                ) : (
                    <p className="small muted" style={{ margin: 0 }}>
                        None marked yet. Tick “Subclan head” on a person to list them here and in the print and list screens.
                    </p>
                )}
            </section>

            <section className="card" style={{ borderColor: 'var(--danger)' }}>
                <h2>Delete clan</h2>
                <p className="small muted" style={{ margin: 0 }}>
                    Takes its {clan.people} people out of view with it. It can be restored from the clan list.
                </p>
                <div>
                    <Button variant="danger" onClick={del}>
                        Delete {clan.label}…
                    </Button>
                </div>
            </section>
        </div>
    );
}
