A registry, not an app store. Everything here serves one job: typing seven generations of handwritten records into a tree that reads well on screen and prints well on paper. Warm paper, dark ink, a serif for people's names, hairline rules, and one colour — `lineage` — for the line of descent.

## Content fundamentals

- **Names are written the way the family writes them.** Never force case, never "fix" spelling, never require more than the given name. A founder known by one name ("Isko") is a complete record.
- **Dates are shown as the record says them.** Prefer the free-text date: `b. abt. 1892 · d. 1960`, `before the war`. Fall back to the exact date only when there is no text. Dates are for display and reference only — never used to order anyone (siblings go by `sibling_order`). Use the genealogist's `b.` `d.` `m.` abbreviations and a middle dot `·` between facts.
- **Blank is normal.** An empty spouse, parent or date is shown as nothing — no "Unknown", no dashes in red, no warning icon. The one exception is the people list, where a person with no parent linked carries the word *Unplaced*.
- **The family's own words.** `parentage_note` is quoted as written: "raised by mother", "father not named at the family's request". Don't paraphrase it.
- **Voice:** plain, respectful, second person in hints ("Leave blank if the family didn't name them"). Sentence case for labels and buttons, verb first ("Add another child", "Save person"). No exclamation marks, no emoji.
- **Words for things:** *clan-line parent* (the one descended from the founders), *spouse* (married in), *unplaced* (typed in, no parent yet), *generation* ("Gen 3"; the founding couple are Gen 1, and a subclan sheet numbers its head as Gen 0), *subclan*, *married in*, *from the Dulnuan clan*, *living*.

## Visual foundations

**Colour.** Three themes: `paper` (working), `lamplight` (dark) and `print` (white sheet, black ink, no tints, no shadows — set `data-theme="print"` on the print route). Ground is `paper`; nodes, cards and the form sheet sit on `paper-raised`; inputs and placeholders on `paper-sunk`. Text is `ink`; dates, places and hints `ink-muted`.

- `lineage` means the clan line and nothing else: descent links, the clan-line node frame, the clan-line parent field, the primary button.
- `gilt` marks the founders and generation numerals. `spouse` draws the marriage join. These three are identity, not state.
- States always carry a word: `living` (Living), `warn` (Unplaced, soft warnings), `danger` (only the ancestor-cycle rule and destructive actions). A reused name is a `hint`, never red.
- Text on a `lineage` or `danger` fill is `on-lineage` / `on-danger`, never literal white.

**Type.** `serif` (Source Serif 4) for people's names and page titles only: `display`, `name-lg`, `name`, `name-sm`. `sans` (Source Sans 3) for everything else: `heading`, `body`, `label`, `caption`, `overline` (set in capitals). Dates use tabular numerals. The app runs locally, so self-host both faces (the `@fontsource/source-serif-4` and `@fontsource/source-sans-3` packages) rather than loading them from Google.

**Space and shape.** 4px base: `space-1` … `space-8`. Corners are nearly square: `radius-sm` (2px) for inputs, badges and photos, `radius-md` (4px) for buttons, nodes and cards, `radius-0` for printed charts. Shadows (`shadow-sm`, `shadow-lg`) are screen only.

**The tree.** Lay out with d3-hierarchy using `node-width`, `node-gap` and `generation-gap`; draw links in SVG at `link-width`. Descent links are solid `lineage` elbows leaving the clan-line person's box. The marriage join is a `spouse` rule with "=" between the couple (CoupleNode). A hidden second-parent link is not drawn; with the archive print toggle on, it is a dotted `link-hidden` line plus the "Link hidden" badge. A couple box where a spouse is recorded, a single box where none is.

**Print.** Browser print with CSS `@page` sized from the print options. On paper, keep every node at least `print-node-min` (45 mm) wide, use `name-sm` and `dense` nodes below the third generation, and print branches separately rather than shrinking type. Living people print name and nickname only (`redactLiving`, on by default).

**Focus and states.** Keyboard focus is the `focus-ring` shadow: a 2px paper gap, then a solid 2px `focus` ring, ≥3:1 on every paper. Hover darkens to `paper-sunk`; selection fills `lineage-soft`. Control borders are `rule-strong` (≥3:1); `rule` is for decorative hairlines only.

## Iconography

The registry has no icon set, on purpose: genealogical marks do the work — `=` for marriage, `b.` `d.` `m.` for events, `“nickname”` in quotes, a generation numeral in `gilt`. Where a control needs more, use a word. A person without a photo shows their initials in the serif on `paper-sunk`.

## Components

React 18 components in `react/`: `Button`, `Field`, `DateField`, `Badge`, `PersonNode`, `CoupleNode`, `PersonRow`. Import `react/index.js` once — it brings in `react/clan.css` (tokens + component styles, `cl-` prefix). A person without a portrait shows initials; with photos on, use their main portrait (the `is_primary` photo of kind `portrait`).
