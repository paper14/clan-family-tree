# CoupleNode

The tree's unit: the clan-line person, and their spouse when one is recorded, joined by the genealogist's "=" with the marriage date under it. No spouse → a single box, which is a normal, complete state.

- Consumer provides: `person`, optional `spouse` (PersonNode props), optional `marriage` text, `founders` for the founding couple (both clan-framed on gilt-soft).
- Descent links leave from the clan-line person's box, never from the join — the line follows the blood.
- A spouse hidden by `hide_second_parent` is simply not passed; include it only for the archive print toggle, with a neutral "Link hidden" badge.
