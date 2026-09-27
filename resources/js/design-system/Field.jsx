import { useId } from 'react';
import { cx } from './cx';

/**
 * Labelled input or textarea. `required` only on Given name (the only required field).
 * `clanLine` marks the clan-line parent picker. `error` only for the hard stops
 * (blank given name, own-ancestor cycle, cross-clan parent link); everything else is a `hint`.
 */
export function Field({ label, hint, error, clanLine, multiline, id, className, required, ...rest }) {
  const auto = useId();
  const fid = id || auto;
  const hintId = hint || error ? `${fid}-h` : undefined;
  const Control = multiline ? 'textarea' : 'input';
  return (
    <div className={cx('cl-field', clanLine && 'cl-field-clan', error && 'cl-field-error', className)}>
      <label htmlFor={fid} className="cl-label">
        {label}{required && <span className="cl-req">required</span>}
      </label>
      <Control id={fid} aria-describedby={hintId} aria-invalid={error ? true : undefined} {...rest} className="cl-input" />
      {error ? <div id={hintId} className="cl-hint cl-hint-error" role="alert">{error}</div>
        : hint ? <div id={hintId} className="cl-hint">{hint}</div> : null}
    </div>
  );
}
