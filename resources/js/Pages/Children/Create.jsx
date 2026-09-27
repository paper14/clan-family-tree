import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '../../design-system';
import { ErrorNote, Options, PersonCard } from '../../Components/ui';
import ChildRows, { blankRow, defaultLast, saveLabel, summary } from '../../Components/ChildRows';

/** Add children (screen 7): the screen that gets the most use during transcription. */
export default function ChildrenCreate({ clan, parentOptions, parent, spouses, defaultOther, existing, relations }) {
    const { errors } = usePage().props;
    const [other, setOther] = useState(defaultOther ? String(defaultOther) : '');
    const otherP = spouses.find((s) => String(s.id) === other) || null;
    const newRow = () => blankRow(defaultLast(parent, otherP));
    const [rows, setRows] = useState(() => [newRow(), newRow(), newRow()]);
    const [busy, setBusy] = useState(false);
    const count = parent ? (existing[other] ?? 0) : 0;

    const pickParent = (id) => router.get('/children', id ? { parent: id } : {}, { replace: true });

    const changeOther = (v) => {
        setOther(v);
        const o = spouses.find((s) => String(s.id) === v) || null;
        setRows(rows.map((r) => (r.given_name ? r : { ...r, last_name: defaultLast(parent, o) })));
    };

    const save = () => {
        if (!parent) return;
        router.post(`/people/${parent.id}/children`, { other_parent_id: other, rows }, { onStart: () => setBusy(true), onFinish: () => setBusy(false) });
    };

    return (
        <>
            <Head title="Add children" />
            <div className="page-head">
                <div className="grow">
                    <h1 className="display">Add children</h1>
                    <p className="muted" style={{ margin: 0 }}>
                        Type a whole family in one pass, first child to last — row order is sibling order. Only a name is needed; Enter in the last row adds
                        another.
                    </p>
                </div>
            </div>
            <ErrorNote errors={errors} />
            <section className="card" style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 24, alignItems: 'flex-end', marginBottom: 24 }}>
                <div className="cl-field cl-field-clan" style={{ flex: '1 1 300px' }}>
                    <label className="cl-label" htmlFor="k-parent">
                        Clan-line parent · {clan.label}
                    </label>
                    <select className="cl-input" id="k-parent" value={parent?.id ?? ''} onChange={(e) => pickParent(e.target.value)}>
                        <option value="">— Choose a parent —</option>
                        <Options options={parentOptions} />
                    </select>
                    {!parent && <span className="cl-hint">Only people already numbered in this clan are listed.</span>}
                </div>
                <div className="cl-field" style={{ flex: '1 1 300px' }}>
                    <label className="cl-label" htmlFor="k-other">
                        Other parent (optional)
                    </label>
                    <select className="cl-input" id="k-other" value={other} disabled={!parent} onChange={(e) => changeOther(e.target.value)}>
                        <option value="">No other parent recorded</option>
                        {spouses.map((s) => (
                            <option key={s.id} value={s.id}>
                                {s.name}
                                {s.clan_id !== parent?.clan_id ? ` (${s.clan_label})` : ''}
                                {s.married ? ` — married ${s.married}` : ''}
                            </option>
                        ))}
                    </select>
                    <span className="cl-hint">The parent’s recorded spouses. Leave blank for a solo parent or when the family didn’t name them.</span>
                </div>
                {parent && (
                    <div style={{ flex: '0 1 260px' }}>
                        <PersonCard person={parent} clanLine />
                    </div>
                )}
            </section>
            <ChildRows rows={rows} setRows={setRows} existing={count} relations={relations} newRow={newRow} />
            <div className="row" style={{ marginTop: 20, flexWrap: 'nowrap' }}>
                <p className="small muted" style={{ flex: '1 1 auto', minWidth: 0, margin: 0 }}>
                    {summary(parent, otherP, count)}
                </p>
                <Link className="cl-btn" href={parent ? `/people/${parent.id}` : '/people'}>
                    Cancel
                </Link>
                <Button variant="primary" onClick={save} disabled={busy || !parent}>
                    {saveLabel(rows)}
                </Button>
            </div>
        </>
    );
}
