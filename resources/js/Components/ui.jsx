import { Link } from '@inertiajs/react';
import { Badge } from '../design-system';

/** Name with the nickname in italics and quotes: Andres Santos “Ando”. */
export function NameNick({ name, nickname }) {
    return (
        <>
            {name}
            {nickname ? <span className="nick"> “{nickname}”</span> : null}
        </>
    );
}

/** Portrait, or initials in the serif on paper-sunk. */
export function Photo({ person, large, className = '' }) {
    return (
        <span className={`photo ${large ? 'lg' : ''} ${className}`} aria-hidden="true">
            {person.portrait ? <img src={person.portrait} alt="" /> : person.initials}
        </span>
    );
}

/**
 * A person card that opens their page. `clanLine` draws the lineage frame; a member of
 * another clan is labelled with that clan (relTo = the clan the page is about).
 */
export function PersonCard({ person, clanLine, showOtherClan = true }) {
    const clan = clanLine ?? person.generation != null;
    return (
        <Link className={`pnode ${clan ? 'clan' : ''}`} href={`/people/${person.id}`}>
            <Photo person={person} />
            <span className="tx">
                {person.generation != null && <span className="gen">Gen {person.generation}</span>}
                <span className="nm">
                    <NameNick name={person.name} nickname={person.nickname} />
                </span>
                {person.life && <span className="dt">{person.life}</span>}
                {showOtherClan && person.other_clan && <span className="oc">{person.clan_label}</span>}
                {person.is_living && <Badge tone="living">Living</Badge>}
            </span>
        </Link>
    );
}

export function Note({ tone = 'warn', title, children, className = '' }) {
    return (
        <div className={`note ${tone} ${className}`} role={tone === 'error' ? 'alert' : undefined}>
            {title && <b>{title}</b>} {children}
        </div>
    );
}

/** The one hard-stop message: "Can’t save — …". Shows the first error. */
export function ErrorNote({ errors, keys }) {
    const list = keys ? keys.map((k) => errors?.[k]).filter(Boolean) : Object.values(errors || {});
    if (!list.length) return null;
    const msg = list[0];
    return (
        <div className="note error" role="alert">
            <b>Can’t save —</b> {msg}
        </div>
    );
}

/** <option>s for a flat list of {id, label}. */
export function Options({ options }) {
    return options.map((o) => (
        <option key={o.id} value={o.id}>
            {o.label}
        </option>
    ));
}

/** <optgroup>s for [{label, options: [{id, label}]}]. */
export function GroupedOptions({ groups }) {
    return groups.map((g) => (
        <optgroup key={g.label} label={g.label}>
            <Options options={g.options} />
        </optgroup>
    ));
}

export function RelationSelect({ id, value, onChange, relations }) {
    return (
        <select className="cl-input" id={id} value={value || 'biological'} onChange={(e) => onChange(e.target.value)}>
            {relations.map((r) => (
                <option key={r} value={r}>
                    {r[0].toUpperCase() + r.slice(1)}
                </option>
            ))}
        </select>
    );
}

export function Radios({ name, value, onChange, options, legend }) {
    return (
        <fieldset className="cl-field" style={{ border: 0, padding: 0, margin: 0 }}>
            {legend && <legend className="cl-label">{legend}</legend>}
            <div className="radios">
                {options.map(([v, label]) => (
                    <label key={v}>
                        <input type="radio" name={name} value={v} checked={String(value) === String(v)} onChange={() => onChange(v)} />
                        {label}
                    </label>
                ))}
            </div>
        </fieldset>
    );
}

/** A little up/down arrow (the only icons are inline SVG arrows). */
export function Arrow({ up }) {
    return (
        <svg width="12" height="12" viewBox="0 0 12 12" aria-hidden="true">
            <path d={up ? 'M2 8l4-4 4 4' : 'M2 4l4 4 4-4'} fill="none" stroke="currentColor" strokeWidth="1.8" />
        </svg>
    );
}

export function Cross() {
    return (
        <svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="1.8" aria-hidden="true">
            <path d="M3 3l10 10M13 3L3 13" />
        </svg>
    );
}
