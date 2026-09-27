import { useRef } from 'react';
import { Button } from '../design-system';
import { Cross } from './ui';

export function blankRow(last = '') {
    return { given_name: '', middle_name: '', last_name: last, nickname: '', sex: 'unknown', birth_date_text: '', birth_date: '', rel: 'biological' };
}

/** Last name pre-fills from whichever parent is male; else the clan-line parent's (implementation-notes.md §4). */
export function defaultLast(parent, other) {
    if (parent?.sex === 'male') return parent.last_name || '';
    if (other?.sex === 'male') return other.last_name || '';
    return parent?.last_name || '';
}

export function filledCount(rows) {
    return rows.filter((r) => (r.given_name || r.nickname).trim()).length;
}

export function saveLabel(rows) {
    const n = filledCount(rows);
    return `Save ${n} ${n === 1 ? 'child' : 'children'}`;
}

/** "Each child gets A and B as parents, joins the Santos clan at Gen 4, and follows the 3 already recorded (numbered from 4)." */
export function summary(parent, other, existing) {
    if (!parent) return 'Choose the clan-line parent first.';
    const gen = parent.generation_stored ?? parent.generation;
    return (
        `Each child gets ${parent.name}${other ? ` and ${other.name}${other.clan_id !== parent.clan_id ? ` (${other.clan_label})` : ''}` : ''} as parents, ` +
        `joins the ${parent.clan_label}${gen != null ? ` at Gen ${gen + 1}` : ''}` +
        `${existing ? `, and follows the ${existing} already recorded (numbered from ${existing + 1})` : ''}. Empty rows are skipped.`
    );
}

/**
 * A family typed in one pass: row order is sibling order, numbered after any children
 * already recorded. Enter in the last row adds another.
 */
export default function ChildRows({ rows, setRows, compact, existing, relations, newRow }) {
    const ref = useRef();
    const cols = compact
        ? '40px minmax(120px,1.3fr) minmax(100px,1fr) minmax(100px,1.1fr) 110px minmax(110px,1fr) 40px'
        : '40px minmax(120px,1.3fr) minmax(100px,1fr) minmax(90px,1fr) minmax(100px,1.1fr) 110px minmax(110px,1fr) 150px 120px 40px';
    const minWidth = compact ? 760 : 1120;
    const head = compact
        ? ['#', 'Given name', 'Nickname', 'Last name', 'Sex', 'Born, as written', '']
        : ['#', 'Given name', 'Nickname', 'Middle', 'Last name', 'Sex', 'Born, as written', 'Exact date', 'Relation', ''];

    const update = (i, k, v) => setRows(rows.map((r, j) => (j === i ? { ...r, [k]: v } : r)));
    const focusRow = (i) => setTimeout(() => ref.current?.querySelector(`[data-row="${i}"] input`)?.focus(), 0);

    const onKeyDown = (e, i) => {
        if (e.key !== 'Enter') return;
        e.preventDefault();
        if (i === rows.length - 1) setRows([...rows, newRow()]);
        focusRow(i + 1);
    };

    const input = (r, i, k, label, extra = {}) => (
        <input className="cl-input" aria-label={`${label}, child ${existing + i + 1}`} value={r[k]} onChange={(e) => update(i, k, e.target.value)} onKeyDown={(e) => onKeyDown(e, i)} {...extra} />
    );

    return (
        <section className="kids" aria-label="Children, first to last" ref={ref}>
            <div className="krow h" style={{ gridTemplateColumns: cols, minWidth }}>
                {head.map((h, i) => (
                    <span key={i}>{h}</span>
                ))}
            </div>
            {rows.map((r, i) => {
                const n = existing + i + 1;
                const sex = (
                    <select className="cl-input" aria-label={`Sex, child ${n}`} value={r.sex} onChange={(e) => update(i, 'sex', e.target.value)}>
                        <option value="unknown">—</option>
                        <option value="male">Male</option>
                        <option value="female">Female</option>
                    </select>
                );
                return (
                    <div className="krow" data-row={i} key={i} style={{ gridTemplateColumns: cols, minWidth }}>
                        <span className="pos" title="Position among siblings">
                            {n}
                        </span>
                        {input(r, i, 'given_name', 'Given name', { placeholder: 'Given name' })}
                        {input(r, i, 'nickname', 'Nickname')}
                        {!compact && input(r, i, 'middle_name', 'Middle name')}
                        {input(r, i, 'last_name', 'Last name')}
                        {sex}
                        {input(r, i, 'birth_date_text', 'Born as written', { placeholder: 'abt. 1930' })}
                        {!compact && input(r, i, 'birth_date', 'Exact date', { type: 'date' })}
                        {!compact && (
                            <select className="cl-input" aria-label={`Relation, child ${n}`} value={r.rel} onChange={(e) => update(i, 'rel', e.target.value)}>
                                {relations.map((x) => (
                                    <option key={x} value={x}>
                                        {x[0].toUpperCase() + x.slice(1)}
                                    </option>
                                ))}
                            </select>
                        )}
                        <Button className="cl-btn-sm cl-btn-icon" aria-label={`Remove row ${i + 1}`} onClick={() => setRows(rows.filter((_, j) => j !== i))}>
                            <Cross />
                        </Button>
                    </div>
                );
            })}
            <div style={{ padding: '12px 14px' }}>
                <Button
                    onClick={() => {
                        setRows([...rows, newRow()]);
                        focusRow(rows.length);
                    }}
                >
                    Add another row
                </Button>
            </div>
        </section>
    );
}
