import { Badge } from './Badge';
import { cx } from './cx';

const initials = (name = '') => name.split(/\s+/).filter(Boolean).slice(0, 2).map((s) => s[0]).join('').toUpperCase();

/**
 * One person's box. `generation` is the number to SHOW — pass it through the single
 * generation-display helper (absolute, or relative to a chosen root; planning §2.5, §5).
 * clanLine=false → married in / from another clan (pass `otherClan` to label it).
 */
export function PersonNode({ name, nickname, dates, generation, living, redactLiving, photo, showPhoto = true,
  clanLine = true, founder, selected, dense, otherClan, onOpen, className }) {
  const hideDates = living && redactLiving;
  const cls = cx('cl-node', clanLine ? 'cl-node-clan' : 'cl-node-spouse', founder && 'cl-node-founder',
    selected && 'cl-node-selected', dense && 'cl-node-dense', className);
  const body = (
    <>
      {showPhoto && <span className="cl-photo" aria-hidden="true">{photo ? <img src={photo} alt="" /> : initials(name)}</span>}
      <span className="cl-node-text">
        {generation != null && <span className="cl-gen">Gen {generation}</span>}
        <span className="cl-node-name">{name}{nickname && <span className="cl-nick"> “{nickname}”</span>}</span>
        {!hideDates && dates && <span className="cl-node-dates">{dates}</span>}
        {otherClan && <span className="cl-node-dates" style={{ color: 'var(--spouse)', fontWeight: 600 }}>{otherClan}</span>}
        {living && <Badge tone="living">Living</Badge>}
      </span>
    </>
  );
  return onOpen
    ? <button type="button" className={cls} onClick={onOpen} aria-current={selected ? 'true' : undefined}>{body}</button>
    : <div className={cls}>{body}</div>;
}
