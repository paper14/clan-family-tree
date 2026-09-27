import { router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { Badge, Button } from '../design-system';
import { NameNick } from './ui';

/**
 * Inline add / edit marriage (screen 8). Search first, create second: the spouse field
 * searches name and nickname across ALL clans, so an existing person is linked rather than
 * retyped. No sex is pre-selected for a new spouse — an assumed opposite is a guess.
 */
export default function MarriageForm({ person, marriage, onClose }) {
    const [q, setQ] = useState('');
    const [results, setResults] = useState([]);
    const [sel, setSel] = useState(marriage?.spouse ? { ...marriage.spouse, already: false } : null);
    const [create, setCreate] = useState(false);
    const [fields, setFields] = useState({
        date_text: marriage?.date_text || '',
        date: marriage?.date || '',
        place: marriage?.place || '',
        status: marriage?.status || 'married',
        notes: marriage?.notes || '',
        new_given: '',
        new_nick: '',
        new_last: '',
        new_sex: '',
    });
    const [errors, setErrors] = useState({});
    const [busy, setBusy] = useState(false);
    const qRef = useRef();
    const set = (k) => (e) => setFields({ ...fields, [k]: e.target.value });

    useEffect(() => {
        if (!sel && !create) qRef.current?.focus();
    }, [sel, create]);

    useEffect(() => {
        const t = q.trim();
        if (t.length < 2) {
            setResults([]);
            return;
        }
        const ctl = new AbortController();
        const timer = setTimeout(() => {
            fetch(`/spouse-search?${new URLSearchParams({ q: t, person: person.id })}`, { headers: { Accept: 'application/json' }, signal: ctl.signal })
                .then((r) => r.json())
                .then(setResults)
                .catch(() => {});
        }, 120);
        return () => {
            clearTimeout(timer);
            ctl.abort();
        };
    }, [q, person.id]);

    const submit = (e) => {
        e.preventDefault();
        if (sel && !marriage && sel.already && !window.confirm(`${person.name} is already recorded as married to ${sel.name}. Add a second marriage record anyway?`)) return;
        const data = { ...fields, create: create ? 1 : 0, spouse_id: !create && sel ? sel.id : '', person_id: person.id };
        const opts = {
            preserveScroll: true,
            onStart: () => setBusy(true),
            onFinish: () => setBusy(false),
            onError: setErrors,
            onSuccess: onClose,
        };
        if (marriage) router.put(`/marriages/${marriage.id}`, data, opts);
        else router.post(`/people/${person.id}/marriages`, data, opts);
    };

    const remove = () => {
        if (!window.confirm('Remove this marriage record? The people stay; its family pictures are deleted.')) return;
        router.delete(`/marriages/${marriage.id}`, { preserveScroll: true, onSuccess: onClose });
    };

    const errorMsg = errors.new_given || errors.date || errors.spouse_id;

    let pick;
    if (sel) {
        pick = (
            <div className="cl-field">
                <span className="cl-label">Spouse</span>
                <div className="row picked">
                    <span style={{ flexGrow: 1, minWidth: 0 }}>
                        <b className="serif" style={{ fontSize: 16 }}>
                            <NameNick name={sel.name} nickname={sel.nickname} />
                        </b>{' '}
                        <span className="small muted">
                            · {sel.clan_label}
                            {sel.generation != null ? ` · Gen ${sel.generation}` : ''}
                            {sel.span ? ` · ${sel.span}` : ''}
                        </span>
                    </span>
                    <Button className="cl-btn-sm" onClick={() => setSel(null)}>
                        Change
                    </Button>
                </div>
            </div>
        );
    } else if (create) {
        pick = (
            <div className="stack newbox">
                <div className="row">
                    <b style={{ flexGrow: 1 }}>New person, married in</b>
                    <Button variant="link" className="cl-btn-sm" onClick={() => setCreate(false)}>
                        Search again
                    </Button>
                </div>
                <p className="small muted" style={{ margin: 0 }}>
                    Added to the {person.clan_label}. Only a name is needed — a nickname alone is fine.
                </p>
                <div className="grid2" style={{ gap: 12, alignItems: 'start' }}>
                    <div className={`cl-field ${errors.new_given ? 'cl-field-error' : ''}`}>
                        <label className="cl-label" htmlFor="m-g">
                            Given name
                        </label>
                        <input className="cl-input" id="m-g" value={fields.new_given} onChange={set('new_given')} autoFocus />
                    </div>
                    <div className="cl-field">
                        <label className="cl-label" htmlFor="m-nk">
                            Nickname
                        </label>
                        <input className="cl-input" id="m-nk" placeholder="How the family knows them" value={fields.new_nick} onChange={set('new_nick')} />
                    </div>
                    <div className="cl-field">
                        <label className="cl-label" htmlFor="m-l">
                            Surname or maiden surname
                        </label>
                        <input className="cl-input" id="m-l" value={fields.new_last} onChange={set('new_last')} />
                    </div>
                    <fieldset className="cl-field" style={{ border: 0, padding: 0, margin: 0 }}>
                        <legend className="cl-label">Sex</legend>
                        <div className="radios">
                            {[['male', 'Male'], ['female', 'Female'], ['unknown', 'Unknown']].map(([v, l]) => (
                                <label key={v}>
                                    <input type="radio" name="new_sex" value={v} checked={fields.new_sex === v} onChange={set('new_sex')} />
                                    {l}
                                </label>
                            ))}
                        </div>
                    </fieldset>
                </div>
                <span className="cl-hint">Sex decides whether they are written as husband or wife. Nothing is assumed from {person.name}’s.</span>
            </div>
        );
    } else {
        const t = q.trim();
        pick = (
            <>
                <div className="cl-field">
                    <label className="cl-label" htmlFor="m-q">
                        Spouse — search every clan by name or nickname
                    </label>
                    <input className="cl-input" id="m-q" ref={qRef} autoComplete="off" placeholder="Start typing a name" value={q} onChange={(e) => setQ(e.target.value)} />
                </div>
                {t.length >= 2 && (
                    <div className="stack" style={{ gap: 4 }}>
                        {results.map((x) => (
                            <button key={x.id} type="button" className="mres" onClick={() => setSel(x)}>
                                <span style={{ flexGrow: 1, minWidth: 0 }}>
                                    <b className="serif">
                                        <NameNick name={x.name} nickname={x.nickname} />
                                    </b>
                                    <br />
                                    <span className="small muted">
                                        {x.clan_label}
                                        {x.generation != null ? ` · Gen ${x.generation}` : x.marriedIn ? ' · married in' : ''}
                                        {x.span ? ` · ${x.span}` : ''}
                                        {x.spouses ? ` · married to ${x.spouses}` : ''}
                                    </span>
                                </span>
                                {x.already ? (
                                    <Badge tone="warn">Already married to them</Badge>
                                ) : (
                                    <span className="small" style={{ color: 'var(--lineage)', fontWeight: 600 }}>
                                        Link
                                    </span>
                                )}
                            </button>
                        ))}
                        <Button
                            className="cl-btn-sm"
                            style={{ alignSelf: 'flex-start' }}
                            onClick={() => {
                                setFields({ ...fields, new_given: t });
                                setCreate(true);
                            }}
                        >
                            {results.length ? 'None of these — add a new person' : `No match — add “${t}” as a new person`}
                        </Button>
                    </div>
                )}
                <div className="small muted">Leave empty if the spouse isn’t recorded.</div>
            </>
        );
    }

    return (
        <form className="stack mform" onSubmit={submit} noValidate>
            {errorMsg && (
                <div className="note error" role="alert">
                    <b>Can’t save —</b> {errorMsg}
                </div>
            )}
            {pick}
            <div className="grid2" style={{ gap: 12 }}>
                <div className="cl-field">
                    <label className="cl-label" htmlFor="m-dt">
                        Date, as written
                    </label>
                    <input className="cl-input" id="m-dt" placeholder="abt. 1926" value={fields.date_text} onChange={set('date_text')} />
                </div>
                <div className={`cl-field ${errors.date ? 'cl-field-error' : ''}`}>
                    <label className="cl-label" htmlFor="m-d">
                        Exact date (if known)
                    </label>
                    <input className="cl-input" type="date" id="m-d" value={fields.date} onChange={set('date')} />
                </div>
            </div>
            <div className="grid2" style={{ gap: 12 }}>
                <div className="cl-field">
                    <label className="cl-label" htmlFor="m-p">
                        Place
                    </label>
                    <input className="cl-input" id="m-p" value={fields.place} onChange={set('place')} />
                </div>
                <div className="cl-field">
                    <label className="cl-label" htmlFor="m-s">
                        Status
                    </label>
                    <select className="cl-input" id="m-s" value={fields.status} onChange={set('status')}>
                        {['married', 'separated', 'widowed', 'unknown'].map((s) => (
                            <option key={s} value={s}>
                                {s}
                            </option>
                        ))}
                    </select>
                </div>
            </div>
            <div className="cl-field">
                <label className="cl-label" htmlFor="m-n">
                    Notes
                </label>
                <input className="cl-input" id="m-n" value={fields.notes} onChange={set('notes')} />
            </div>
            <div className="row">
                {marriage && (
                    <Button variant="danger" className="cl-btn-sm" onClick={remove}>
                        Remove
                    </Button>
                )}
                <span style={{ flexGrow: 1 }} />
                <Button className="cl-btn-sm" onClick={onClose}>
                    Cancel
                </Button>
                <Button type="submit" variant="primary" className="cl-btn-sm" disabled={busy}>
                    Save marriage
                </Button>
            </div>
        </form>
    );
}
