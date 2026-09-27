import { Badge } from './Badge';
import { cx } from './cx';

/** A line in the people list. `unplaced` = no parent linked yet (a to-do, warn tone — never danger). */
export function PersonRow({ name, nickname, dates, generation, parent, unplaced, living, redactLiving, selected, onOpen, onShowInTree }) {
  return (
    <div className={cx('cl-row', selected && 'cl-row-selected')}>
      <span className="cl-row-gen">{generation ?? '–'}</span>
      <span className="cl-row-main">
        <button type="button" className="cl-row-name" onClick={onOpen} style={{ background: 'none', border: 0, padding: 0, textAlign: 'left', color: 'inherit', cursor: 'pointer' }}>
          {name}{nickname && <span className="cl-nick"> “{nickname}”</span>}
        </button>
        {parent && <span className="cl-row-parent">child of {parent}</span>}
      </span>
      <span className="cl-row-dates">{living && redactLiving ? '' : dates}</span>
      <span className="cl-row-flags">
        {unplaced && <Badge tone="warn">Unplaced</Badge>}
        {living && <Badge tone="living">Living</Badge>}
        {onShowInTree && <button type="button" className="cl-btn cl-btn-link" onClick={onShowInTree}>Show in tree</button>}
      </span>
    </div>
  );
}
