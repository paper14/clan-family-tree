import { PersonNode } from './PersonNode';
import { cx } from './cx';

/**
 * The tree's unit: clan-line person + spouse(s) joined by "=". No spouse → single box (a normal,
 * complete state). Descent links leave from the clan-line person's box, never from the join.
 */
export function CoupleNode({ person, spouse, marriage, founders, redactLiving, dense }) {
  const p = { clanLine: true, ...person, redactLiving, dense, founder: founders };
  if (!spouse) return <div className="cl-couple cl-couple-single"><PersonNode {...p} /></div>;
  const s = { clanLine: !!founders, ...spouse, redactLiving, dense, founder: founders };
  return (
    <div className={cx('cl-couple', founders && 'cl-couple-founders')}>
      <PersonNode {...p} />
      <span className="cl-join" title={marriage || 'Married'}>
        <span aria-hidden="true">=</span>
        {marriage ? <span className="cl-join-date">{marriage}</span> : <span className="cl-sr">married</span>}
      </span>
      <PersonNode {...s} />
    </div>
  );
}
