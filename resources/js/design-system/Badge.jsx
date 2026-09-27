import { cx } from './cx';

/** One or two words of status — always a word, never colour alone.
 *  tone: neutral · lineage · gilt · living · warn · danger */
export function Badge({ tone = 'neutral', className, children }) {
  return <span className={cx('cl-badge', `cl-badge-${tone}`, className)}>{children}</span>;
}
