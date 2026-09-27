import { cx } from './cx';

/** The one button. quiet (default) · primary (lineage, once per view) · danger · link. */
export function Button({ variant = 'quiet', className, type = 'button', ...rest }) {
  return <button type={type} {...rest} className={cx('cl-btn', `cl-btn-${variant}`, className)} />;
}
