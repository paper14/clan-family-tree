import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { Badge, Button } from '../design-system';

/**
 * One kind of photo attached to one person, marriage or clan (planning.md §2.11).
 * Web-sized copies only; the first photo becomes the main one.
 */
export default function PhotoPanel({ panel, clanId }) {
    const [adding, setAdding] = useState(false);
    const [editing, setEditing] = useState(null);
    const add = useForm({ file: null, caption: '', year: '' });
    const edit = useForm({ caption: '', year: '' });

    const submitAdd = (e) => {
        e.preventDefault();
        add.transform((d) => ({ ...d, kind: panel.kind, type: panel.type, id: panel.id, clan_id: panel.clan_id ?? clanId }));
        add.post('/photos', {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                add.reset();
                setAdding(false);
            },
        });
    };

    const startEdit = (ph) => {
        edit.setData({ caption: ph.caption || '', year: ph.year ? String(ph.year) : '' });
        edit.clearErrors();
        setEditing(ph.id);
    };

    const submitEdit = (e, ph) => {
        e.preventDefault();
        edit.put(`/photos/${ph.id}`, { preserveScroll: true, onSuccess: () => setEditing(null) });
    };

    const remove = (ph) => {
        if (!window.confirm('Delete this photo from the app? Your camera original is not affected.')) return;
        router.delete(`/photos/${ph.id}`, { preserveScroll: true });
    };

    const n = panel.photos.length;
    const addError = add.errors.file || add.errors.year;

    return (
        <div className="phgrp">
            <div className="row">
                <h3 style={{ flexGrow: 1 }}>
                    {panel.title}
                    {panel.sub && (
                        <span className="small muted" style={{ fontWeight: 400 }}>
                            {' '}
                            {panel.sub}
                        </span>
                    )}
                </h3>
                <span className="small muted">
                    {n} photo{n === 1 ? '' : 's'}
                </span>
                {!adding && (
                    <Button className="cl-btn-sm" onClick={() => setAdding(true)}>
                        Add photo
                    </Button>
                )}
            </div>

            {adding && (
                <form className="phform" onSubmit={submitAdd} noValidate>
                    <div className="cl-field">
                        <label className="cl-label" htmlFor={`ph-file-${panel.kind}-${panel.id}`}>
                            Image
                        </label>
                        <input id={`ph-file-${panel.kind}-${panel.id}`} type="file" accept="image/*" className="small" onChange={(e) => add.setData('file', e.target.files[0])} />
                    </div>
                    <div className="cl-field">
                        <label className="cl-label" htmlFor={`ph-cap-${panel.kind}-${panel.id}`}>
                            Caption
                        </label>
                        <input
                            className="cl-input"
                            id={`ph-cap-${panel.kind}-${panel.id}`}
                            value={add.data.caption}
                            onChange={(e) => add.setData('caption', e.target.value)}
                            placeholder={panel.group ? 'Back row, L–R: … · Front row, L–R: …' : 'Where and when, who took it'}
                        />
                    </div>
                    <div className={`cl-field ${add.errors.year ? 'cl-field-error' : ''}`}>
                        <label className="cl-label" htmlFor={`ph-year-${panel.kind}-${panel.id}`}>
                            Year
                        </label>
                        <input className="cl-input" id={`ph-year-${panel.kind}-${panel.id}`} inputMode="numeric" maxLength={4} placeholder="1998" value={add.data.year} onChange={(e) => add.setData('year', e.target.value)} />
                    </div>
                    <div className="row" style={{ gridColumn: '1/-1' }}>
                        <span className="small muted" style={{ flexGrow: 1 }}>
                            {addError ? (
                                <span style={{ color: 'var(--danger)', fontWeight: 600 }}>{addError}</span>
                            ) : (
                                <>
                                    Saved as a web-sized copy. Keep the camera original outside the app.
                                    {n ? '' : ' The first photo becomes the main one.'}
                                </>
                            )}
                        </span>
                        <Button className="cl-btn-sm" onClick={() => setAdding(false)}>
                            Cancel
                        </Button>
                        <Button type="submit" variant="primary" className="cl-btn-sm" disabled={add.processing}>
                            {add.processing ? 'Saving…' : 'Save photo'}
                        </Button>
                    </div>
                </form>
            )}

            {n > 0 ? (
                <div className="phs">
                    {panel.photos.map((ph) => (
                        <figure key={ph.id} className={`ph ${ph.is_primary ? 'primary' : ''}`}>
                            <img src={ph.url} alt={ph.caption || panel.kindLabel} />
                            <figcaption>
                                {editing === ph.id ? (
                                    <form className="stack" style={{ gap: 6 }} onSubmit={(e) => submitEdit(e, ph)}>
                                        <label className="cl-sub" htmlFor={`phe-c-${ph.id}`}>
                                            Caption
                                        </label>
                                        <textarea className="cl-input" id={`phe-c-${ph.id}`} style={{ minHeight: 60 }} value={edit.data.caption} onChange={(e) => edit.setData('caption', e.target.value)} />
                                        <label className="cl-sub" htmlFor={`phe-y-${ph.id}`}>
                                            Year
                                        </label>
                                        <input className="cl-input" id={`phe-y-${ph.id}`} maxLength={4} inputMode="numeric" value={edit.data.year} onChange={(e) => edit.setData('year', e.target.value)} />
                                        {edit.errors.year && <span style={{ color: 'var(--danger)', fontWeight: 600 }}>{edit.errors.year}</span>}
                                        <div className="acts">
                                            <Button className="cl-btn-sm" onClick={() => setEditing(null)}>
                                                Cancel
                                            </Button>
                                            <Button type="submit" variant="primary" className="cl-btn-sm">
                                                Save
                                            </Button>
                                        </div>
                                    </form>
                                ) : (
                                    <>
                                        {ph.is_primary && (
                                            <span style={{ alignSelf: 'flex-start' }}>
                                                <Badge tone="gilt">Main picture</Badge>
                                            </span>
                                        )}
                                        <span>
                                            {ph.year ? <b>{ph.year}</b> : <span className="muted">No year</span>} · {ph.caption || ''}
                                        </span>
                                        <div className="acts">
                                            {!ph.is_primary && (
                                                <Button className="cl-btn-sm" onClick={() => router.post(`/photos/${ph.id}/primary`, {}, { preserveScroll: true })}>
                                                    Make main
                                                </Button>
                                            )}
                                            <Button className="cl-btn-sm" onClick={() => startEdit(ph)}>
                                                Edit
                                            </Button>
                                            <Button variant="link" className="cl-btn-sm cl-btn-danger-text" onClick={() => remove(ph)}>
                                                Delete
                                            </Button>
                                        </div>
                                    </>
                                )}
                            </figcaption>
                        </figure>
                    ))}
                </div>
            ) : (
                !adding && (
                    <p className="small muted" style={{ margin: 0 }}>
                        None yet.
                    </p>
                )
            )}
        </div>
    );
}
