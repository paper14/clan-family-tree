import { Head, Link, router, useForm } from '@inertiajs/react';
import { useEffect, useMemo } from 'react';
import { Button, DateField, Field } from '../../design-system';
import { ErrorNote, GroupedOptions, Options, Radios, RelationSelect } from '../../Components/ui';

/** Person form (screen 5). One page, name at the top; only the given name is required. */
export default function PersonForm({ isNew, clan, person, isFounder, siblingCount, clanParentOptions, otherParentGroups, relations, dupes, placement }) {
    const form = useForm({ ...person, again: false });
    const d = form.data;
    const set = (k) => (e) => form.setData(k, e.target.value);
    const err = form.errors;

    useEffect(() => {
        if (window.location.hash === '#parents') document.getElementById('parents')?.scrollIntoView();
    }, []);
    useEffect(() => {
        if (Object.keys(err).length) window.scrollTo({ top: 0, behavior: 'smooth' });
    }, [err]);

    const cp = useMemo(() => clanParentOptions.find((o) => String(o.id) === String(d.clan_parent_id)), [d.clan_parent_id, clanParentOptions]);
    const genHint = isFounder
        ? 'Founder — stays Gen 1.'
        : cp && cp.generation != null
          ? `Will be placed at Gen ${cp.generation + 1}.`
          : cp
            ? 'That parent isn’t numbered yet, so this person won’t be either.'
            : 'No parent yet — will show as unplaced until one is linked.';

    const submit = (again) => (e) => {
        e.preventDefault();
        form.transform((data) => ({ ...data, again }));
        if (isNew) form.post('/people');
        else form.put(`/people/${person.id}`);
    };

    const del = () => {
        if (window.confirm(`Delete ${person.name}? Their children stay in the registry but lose this parent link.`)) {
            router.delete(`/people/${person.id}`);
        }
    };

    const errClass = (k) => (err[k] ? 'cl-field-error' : '');

    return (
        <form onSubmit={submit(false)} noValidate style={{ display: 'flex', gap: 32, alignItems: 'flex-start', flexWrap: 'wrap' }}>
            <Head title={isNew ? 'Add person' : person.name} />
            <div className="stack" style={{ flex: '1 1 640px', maxWidth: 860, gap: 24 }}>
                <header className="stack" style={{ gap: 4 }}>
                    {isNew ? (
                        <Link className="small" href="/people">
                            ‹ People
                        </Link>
                    ) : (
                        <Link className="small" href={`/people/${person.id}`}>
                            ‹ Back to {person.name}
                        </Link>
                    )}
                    <h1 className="display">{isNew ? 'Add person' : person.name}</h1>
                    <p className="muted" style={{ margin: 0 }}>
                        {isNew ? (
                            <>
                                Adding to the <b>{clan.label}</b>.{' '}
                            </>
                        ) : (
                            `${clan.label}. `
                        )}
                        Only the given name is required.
                    </p>
                </header>
                <ErrorNote errors={err} />

                <section className="card">
                    <h2>Name</h2>
                    <div className="grid3">
                        <Field label="Given name" required value={d.given_name} onChange={set('given_name')} className={errClass('given_name')} autoFocus={isNew} />
                        <Field label="Middle name" value={d.middle_name} onChange={set('middle_name')} />
                        <Field label="Last name" value={d.last_name} onChange={set('last_name')} />
                        <Field label="Suffix" value={d.suffix} onChange={set('suffix')} placeholder="Jr., III" />
                        <Field label="Nickname" value={d.nickname} onChange={set('nickname')} />
                        <Field label="Maiden last name" value={d.maiden_last_name} onChange={set('maiden_last_name')} />
                    </div>
                    {dupes.length > 0 && (
                        <div className="note warn">
                            <b>Same name.</b> {dupes.join(', ')} is also in this clan. Names repeat across branches — this saves fine.
                        </div>
                    )}
                </section>

                <section className="card">
                    <h2>Life</h2>
                    <div className="row" style={{ gap: 48, alignItems: 'flex-start' }}>
                        <Radios name="sex" legend="Sex" value={d.sex} onChange={(v) => form.setData('sex', v)} options={[['male', 'Male'], ['female', 'Female'], ['unknown', 'Unknown']]} />
                        <Radios name="is_living" legend="Living?" value={d.is_living} onChange={(v) => form.setData('is_living', v)} options={[['true', 'Yes'], ['false', 'No'], ['null', 'Not known']]} />
                    </div>
                    <DateField
                        label="Born"
                        date={d.birth_date}
                        onDateChange={set('birth_date')}
                        text={d.birth_date_text}
                        onTextChange={set('birth_date_text')}
                        place={d.birth_place}
                        onPlaceChange={set('birth_place')}
                        className={errClass('birth_date')}
                    />
                    <DateField
                        label="Died"
                        date={d.death_date}
                        onDateChange={set('death_date')}
                        text={d.death_date_text}
                        onTextChange={set('death_date_text')}
                        place={d.death_place}
                        onPlaceChange={set('death_place')}
                        className={errClass('death_date')}
                    />
                    <Field label="Burial place" value={d.burial_place} onChange={set('burial_place')} />
                </section>

                <section className="card" id="parents">
                    <h2>Parents</h2>
                    {!isNew && isFounder && (
                        <div className="note info">
                            <b>{person.name} is a founder (Gen 1).</b> You can link their parents here; numbering won’t change until you use{' '}
                            <Link href={`/clans/${clan.id}/settings`}>Set founding couple</Link>.
                        </div>
                    )}
                    <div style={{ display: 'grid', gridTemplateColumns: 'minmax(0,1fr) 150px', gap: '12px 16px', alignItems: 'start' }}>
                        <div className={`cl-field cl-field-clan ${errClass('clan_parent_id')}`}>
                            <label className="cl-label" htmlFor="i-cp">
                                Clan-line parent
                            </label>
                            <select className="cl-input" id="i-cp" value={d.clan_parent_id} onChange={set('clan_parent_id')}>
                                <option value="">— None yet —</option>
                                <Options options={clanParentOptions} />
                            </select>
                            <span className="cl-hint">The parent descended from the founders. Only people in the {clan.label} can be picked.</span>
                        </div>
                        <div className="cl-field">
                            <label className="cl-label" htmlFor="i-cprel">
                                Relation
                            </label>
                            <RelationSelect id="i-cprel" value={d.clan_relation} onChange={(v) => form.setData('clan_relation', v)} relations={relations} />
                        </div>
                        <div className={`cl-field ${errClass('other_parent_id')}`}>
                            <label className="cl-label" htmlFor="i-op">
                                Other parent (optional)
                            </label>
                            <select className="cl-input" id="i-op" value={d.other_parent_id} onChange={set('other_parent_id')}>
                                <option value="">— Not recorded —</option>
                                <GroupedOptions groups={otherParentGroups} />
                            </select>
                            <span className="cl-hint">
                                Leave blank if the family didn’t name them. May be from another clan — the child still belongs to the clan-line parent’s clan.
                            </span>
                        </div>
                        <div className="cl-field">
                            <label className="cl-label" htmlFor="i-oprel">
                                Relation
                            </label>
                            <RelationSelect id="i-oprel" value={d.other_relation} onChange={(v) => form.setData('other_relation', v)} relations={relations} />
                        </div>
                    </div>
                    <div className="cl-field" style={{ maxWidth: 340 }}>
                        <label className="cl-label" htmlFor="i-order">
                            Position among siblings
                        </label>
                        <input className="cl-input" type="number" min="1" id="i-order" value={d.sibling_order} onChange={set('sibling_order')} placeholder="After the last child" />
                        <span className="cl-hint">
                            {!isNew && siblingCount
                                ? `Child ${person.sibling_order} of ${siblingCount}. First child is 1; changing it moves the others along.`
                                : 'First child is 1. Leave blank to add after the existing children.'}{' '}
                            Twins take consecutive places — note which is which in Notes.
                        </span>
                    </div>
                    <Field
                        label="Parentage note"
                        multiline
                        value={d.parentage_note}
                        onChange={set('parentage_note')}
                        placeholder="In the family’s own words — “raised by mother”, “father not named at the family’s request”"
                    />
                    <label className="check" style={{ padding: 12, border: '1px solid var(--rule)', borderRadius: 'var(--radius-sm)' }}>
                        <input type="checkbox" checked={!!d.hide_second_parent} onChange={(e) => form.setData('hide_second_parent', e.target.checked)} />
                        <span>
                            <b>Hide the other parent in views and print</b>
                            <br />
                            <span className="small muted">
                                Only for when both parents are clan members and one is not to be shown. The link stays recorded; it prints only on the archive
                                copy.
                            </span>
                        </span>
                    </label>
                </section>

                <section className="card">
                    <h2>Subclan</h2>
                    <label className="check">
                        <input type="checkbox" checked={!!d.is_subclan_head} onChange={(e) => form.setData('is_subclan_head', e.target.checked)} />
                        <span>
                            <b>Subclan head</b>
                            <br />
                            <span className="small muted">
                                This branch prints its own sheet, numbered from this person as Gen 0. Heads are offered in the list, tree and print screens.
                            </span>
                        </span>
                    </label>
                    <Field label="Subclan name (optional)" value={d.subclan_name} onChange={set('subclan_name')} placeholder="What the branch calls itself" style={{ maxWidth: 420 }} />
                </section>

                <section className="card">
                    <h2>More</h2>
                    <div className="grid2">
                        <Field label="Occupation" value={d.occupation} onChange={set('occupation')} />
                        <Field label="Residence" value={d.residence} onChange={set('residence')} />
                        <Field label="Phone" type="tel" value={d.phone} onChange={set('phone')} />
                        <Field label="Email" type="email" value={d.email} onChange={set('email')} />
                    </div>
                    <p className="small muted" style={{ margin: 0 }}>
                        Photos — portrait, family pictures and group photos — are added on the person’s page.
                    </p>
                    <Field label="Notes" multiline value={d.notes} onChange={set('notes')} placeholder="Including the source, for a researched ancestor" />
                </section>

                <div className="row sticky-bar">
                    {!isNew && (
                        <Button variant="danger" onClick={del}>
                            Delete
                        </Button>
                    )}
                    <span className="small muted" style={{ flexGrow: 1 }}>
                        {genHint}
                    </span>
                    <Link className="cl-btn" href={isNew ? '/people' : `/people/${person.id}`}>
                        Cancel
                    </Link>
                    {isNew && (
                        <Button onClick={submit(true)} disabled={form.processing}>
                            Save and add another
                        </Button>
                    )}
                    <Button type="submit" variant="primary" disabled={form.processing}>
                        Save person
                    </Button>
                </div>
            </div>

            {!isNew && placement && (
                <aside className="card" style={{ flex: '0 1 280px', padding: 20, marginTop: 96 }}>
                    <h2>Placement</h2>
                    <div className="small stack" style={{ gap: 2 }}>
                        {placement.map((x, i) => (
                            <div key={x.id} className="stack" style={{ gap: 2 }}>
                                {i > 0 && (
                                    <span aria-hidden="true" style={{ color: 'var(--lineage)', paddingLeft: 8 }}>
                                        │
                                    </span>
                                )}
                                <span className="gen">Gen {x.generation}</span>
                                <span className="serif" style={{ fontSize: 15, fontWeight: i === placement.length - 1 ? 600 : 400 }}>
                                    {x.name}
                                </span>
                            </div>
                        ))}
                    </div>
                    <p className="small muted" style={{ margin: 0 }}>
                        Changing a parent re-checks the line: nobody can be their own ancestor, and the clan-line parent must be in the same clan.
                    </p>
                </aside>
            )}
        </form>
    );
}
