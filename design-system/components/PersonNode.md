# PersonNode

One person's box in the tree: photo or initials, generation, name in the serif, dates as written, and a Living badge.

- Consumer provides: `name` (already joined from given/middle/last/suffix — only the given name is guaranteed), optional `nickname`, pre-formatted `dates`, `generation`, `photo`, and `onOpen` to make it clickable.
- `clanLine` (default true) draws the 2px lineage frame; set `clanLine={false}` for a spouse who married in (1px rule-strong frame).
- `redactLiving` hides dates for anyone `living` — on by default when printing (§4.1). Name and nickname always show.
- `dense` for deep generations and printed branches; keep ≥ `print-node-min` (45 mm) wide on paper.
- A person with no parent linked never appears as a node — list them with PersonRow's Unplaced badge instead.
