import { useId } from 'react';
import { cx } from './cx';

/**
 * Born / Died / Married. Text "as written" is the truth; the exact date is for display and
 * reference only — never used for ordering siblings (planning §2.6).
 */
export function DateField({ label, date, onDateChange, text, onTextChange, place, onPlaceChange, placeLabel = 'Place', className }) {
  const id = useId();
  return (
    <fieldset className={cx('cl-field', 'cl-datefield', className)}>
      <legend className="cl-label">{label}</legend>
      <div className="cl-date-row">
        <div className="cl-date-part">
          <label htmlFor={`${id}-d`} className="cl-sub">Exact date (if known)</label>
          <input id={`${id}-d`} type="date" className="cl-input" value={date ?? ''} onChange={onDateChange} />
        </div>
        <div className="cl-date-part cl-date-text">
          <label htmlFor={`${id}-t`} className="cl-sub">As written in the record</label>
          <input id={`${id}-t`} className="cl-input" placeholder="abt. 1892" value={text ?? ''} onChange={onTextChange} />
        </div>
      </div>
      {placeLabel !== false && (
        <div className="cl-date-part">
          <label htmlFor={`${id}-p`} className="cl-sub">{placeLabel}</label>
          <input id={`${id}-p`} className="cl-input" value={place ?? ''} onChange={onPlaceChange} />
        </div>
      )}
    </fieldset>
  );
}
