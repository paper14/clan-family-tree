# Implementation notes

These are the decisions and exact behaviours worked out while building the prototype
(`prototype/clan-family-tree-demo.html`, which matches `planning.md` v0.19 in behaviour —
v0.19 corrected wording only, and changed nothing the prototype does). They fill
gaps in the plan and never override it. Section numbers like §2.6 refer to the plan.

> **Database and platform.** The app is a local web app on MySQL 8, opened in the
> browser. See `docs/architecture-local.md`, which wins on anything to do with the
> database, serving, backup or printing. For the schema, `docs/data-model.md` is the authority.
> It also adds `clan_parent` and the photo `width`/`height` that this document requires
> but the plan's table leaves out.

---

## 1. Parents and clans

- **The clan-line parent must be in the same clan.** The parent picker lists only the
  current clan's people, and the server rejects anything else: *"Can't save — a parent
  link can't cross clans. X is in the Dulnuan clan."*
- **The other parent may be in another clan.** This happens after a cross-clan
  marriage (§2.7): Liza Santos is the mother of Mark Dulnuan. §2.12 forbids cross-clan
  parent links, but that rule would make §2.7 impossible, so it applies **only to the
  clan-line parent**. The other-parent picker lists every live clan, grouped, with the
  current clan first.
- **Saving with only an other parent is refused:** *"Can't save — pick the clan-line
  parent first. The other parent alone doesn't place anyone in the tree."*
- **Mapping to `father_id` / `mother_id`:** if the clan-line parent is female she goes
  in `mother_id` and the other parent in `father_id`; otherwise the reverse. Store
  which one is the clan line in `clan_parent` (`father` or `mother`).
- **A child's `clan_id` is set from the clan-line parent's clan** whenever the parents
  are saved.
- **Cycle check:** walk up both parent chains before saving. The message is: *"Can't
  save — Juan can't be their own ancestor — Teodoro Santos descends from them. Pick a
  different parent."*
- **A founder may have parents recorded.** This is the first step of moving the root.
  The person page then says: *"Founder with parents. To count generations from X
  instead, use Set founding couple."*

## 2. Generation

- **The founder and the founder's spouse are Gen 1.** Descendants through the
  clan-line parent get parent + 1. Spouses who married in, and people from other clans,
  get no stored generation (NULL).
- **Saving a person** recomputes that person and their clan-line descendants only.
  Their value is the clan-line parent's generation + 1, or NULL if that parent has
  none.
- **Only Set founding couple (and restoring a backup) renumber the whole clan.**
  Before anyone confirms, show a preview:
  - how many people get a new number, and how many will be numbered in total;
  - where the old founder moves to, e.g. *"Isko moves from Gen 1 to Gen 2"*;
  - how many would **lose** their number because their line doesn't reach the new
    couple;
  - whether the new founder still has parents recorded;
  - a reminder that subclan sheets are unaffected.

  **A backup of all clans is taken automatically just before renumbering.** There is
  no tick box. The button reads *"Back up, then set founding couple and renumber"*.
  Afterwards, append to the clan notes: *"Previous founding couple: Isko and Sela
  (until 27 September 2026)."*
- **The founder's-spouse dropdown lists only people married to the chosen founder.**
  If none are, it's "— None —". The previous founder's spouse then loses their number,
  and the preview says so.
- **Two generation displays, never mixed:**
  - **Stored / membership display:** `displayGen(person, rootId = null)`. It returns
    the stored generation, or stored minus the root's when a subclan root is chosen.
    It returns NULL across clans, and for people above the root. It's used by the
    people list, the person page, the start dropdown and other pickers.
  - **Chart display (Tree / Outline / print):** depth along the path walked from the
    starting person (§2.7). Clan numbering shows the start's stored generation plus the
    depth; relative numbering shows the depth alone, so the start is Gen 0. Example:
    Paolo's three children all show as **Gen 7** in the Santos chart, although Bea and
    Carlo are stored as Gen 3 in the Menis clan. A co-founder spouse box shows Gen 1;
    other spouse boxes show no generation.

## 3. Sibling order (§2.6)

- A **sibling set** is the people with the same (`father_id`, `mother_id`) pair. It
  doesn't depend on clan: Paolo and Annie's three children are one set, numbered 1–3,
  although one is Santos and two are Menis. People with no parents get
  `sibling_order = 1`, which is never shown.
- **On create:** the next number in the set. In add-children, rows take consecutive
  numbers after any children already recorded, so row order is sibling order.
- **Changing position** (the "Position among siblings" field on the person form):
  insert at that position and renumber the whole set 1…n. A blank field means "after
  the last child".
- **Changing parents** moves the person to the end of the new set, or to the requested
  position, and renumbers the old set.
- **Deleting a person** renumbers their own set. Their children lose that parent link,
  so the children's sets change and get renumbered too.
- **Up/down arrows** on the person page swap with a neighbour and renumber. Toast:
  *"Rosa Santos is now child 3 of 4."*
- **Listing a person's children:** group them by other parent in marriage order
  (children with no other parent recorded come last), then by `sibling_order`. Each
  group is its own numbered set.
- **A person's own children are never clan-scoped.** Every child appears on the
  parent's page, whichever clan the child is a member of.
  - A child in another clan is labelled with that clan. The generation shown there is
    the stored one from **its own** clan.
  - The family heading adds, for example, *"2 of 3 in another clan — Gen shown is from
    their own clan's founders"*.
  - Opening that child switches clan.
- **"Generation, then family order"** is the people list's default sort. It means
  depth-first tree order from the founders, siblings in `sibling_order`. It's never by
  date.
- **Twins** take consecutive positions, with a note saying which is which. The sample
  Dulnuan data has Ana and Joy.

## 4. Inline add children (screen 7)

- Opened from the Children card on the person page, and it stays on that page. Its
  columns are #, Given name, Nickname, Last name, Sex, Born as written, and a remove
  button. The separate Add children screen, with a parent picker and extra columns,
  also exists.
- **The Other parent dropdown** lists the parent's recorded spouses and defaults to
  the most recent one. "No other parent recorded" is always offered.
- **Last name** pre-fills from whichever parent is male. If neither is male, it uses
  the clan-line parent's last name.
- **A row needs a given name or a nickname.** Empty rows are skipped. A nickname-only
  child is saved as `given_name` = the nickname, `nickname` = NULL, with the note
  "Known only by this nickname." That avoids showing *"Bunso “Bunso”"*. Pressing Enter
  in the last row adds a new row.
- **The summary line** reads: *"Each child gets Andres Santos and Lucia Reyes as
  parents, joins the Santos clan at Gen 4, and follows the 3 already recorded (numbered
  from 4). Empty rows are skipped."*

## 5. Inline add marriage (screen 8)

- **Search first:** start at 2 characters, AND-match every word against name and
  nickname, across every live clan, showing up to 8 results. Each result shows its
  clan, generation (or "married in"), dates and current spouses. Anyone already married
  to this person is flagged, and saving that pair again asks for confirmation.
  Matching ignores accents through the MySQL collation (`architecture-local.md` §5).
- **Create second:** "No match — add “reyes” as a new person" pre-fills the given name.
  - Fields are given name, nickname, surname or maiden surname, and sex.
  - **No sex is pre-selected.** If none is picked, save `unknown`.
  - The new person joins the clan of the person whose page this is.
  - A nickname alone is enough, and follows the same rule as in §4.
- **Husband/wife column assignment**, where `a` is the person whose page it is and `b`
  is the spouse:

  ```
  a male    → husband=a, wife=b
  a female  → husband=b, wife=a
  b male    → husband=b, wife=a
  b female  → husband=a, wife=b
  neither   → husband=a, wife=b   (consistent default)
  ```
- **A spouse can be "not recorded"** (NULL). Leave the search empty to save it that
  way.
- **Saving a cross-clan marriage** shows the toast *"Marriage saved — it links the
  Santos clan and the Menis clan."*
- **Removing a marriage** deletes its family pictures (the confirmation says so). The
  people stay.

## 6. Tree / Outline (screen 9)

### 6.1 The descendants query (§2.7, §5)

- **It walks `father_id` / `mother_id` downward from the starting person and never
  filters on `clan_id`.** Explicitly disable the global clan scope for it.
- It never walks upward. Spouses are attached to the person they married as spouse
  boxes; their own parents and siblings are never drawn.
- A child belongs under a person if either parent is that person. A child hidden by
  `hide_second_parent` is excluded from the hidden parent's branch.
- **Dedup by person id, keeping the shortest path.** First find each person's minimum
  depth from the root (breadth-first), then build the tree depth-first, attaching a
  child only at its minimum depth and only once. For example, the child of Andres
  (b. 1956, Gen 5) and his cousin Josefa Cruz (Gen 4) appears once, as Gen 5 under
  Josefa.
- **Parameters:** root, depth (number of generations shown) and the collapsed set.
  Collapsed branches are still walked, so their counts are known, but aren't drawn.
- **Counts:** each node's count is the number of people the walk returns below it
  within the current depth, which is the boxes that will print. It is labelled
  *"Paolo Santos — 3 in this chart"*. Where the depth limit cuts a branch, the label is
  the number of descendants below the cut: *"N more below"*.
- **Test it with the sample data:**
  - the Santos tree from the founders at depth 8 shows Luis, Bea and Carlo under Paolo
    and Annie, all at Gen 7, with Bea and Carlo labelled "Menis clan";
  - Mark, Ana and Joy appear under Liza, labelled "Dulnuan clan";
  - the Santos people list still contains neither Bea nor Carlo.

### 6.2 The page

- **One route, one menu item** ("Tree / Outline"). The view (tree or outline), start,
  depth, numbering, dates, photos and collapsed branches all live in page state, and
  switching views keeps them.
- **Start dropdown** (membership, so scoped): Founding couple, then Subclan heads
  (showing *"Ando's line — Andres Santos · Gen 3"*), then every numbered member of this
  clan.
- **Picking a subclan head** switches numbering to relative; picking the founder
  switches it back to clan numbering. Depth choices are 2, 3, 4, 5, 6, 7, 8, 10.
- **Show in tree** (people-list row, person page) opens this page with that person as
  the starting person and selected. It keeps the view, depth and numbering, clears
  collapsed branches, and switches clan if needed.
- **Tree details:**
  - Siblings run left to right in `sibling_order`, whatever clan each child belongs to.
  - The spouse box follows the person's box, joined by `=` with the marriage year.
  - **Any box whose member clan differs from the clan being viewed is labelled with
    that clan.** That covers spouses and cross-clan descendants alike.
  - A − / + N pill under a box collapses or expands its branch.
  - Where the depth limit cuts a branch, it shows "N more below".
  - "Fit" zoom is the default on arrival.
- **Outline details:** each line shows, in order:
  - an expand/collapse toggle (▾ / ▸, or ⋯ where the depth limit cuts it);
  - the chart generation;
  - the position among siblings (`2.`);
  - the portrait thumbnail, when photos are on;
  - the name, with nickname in italics and quotes;
  - a clan badge if the person is a member of another clan;
  - the dates;
  - a Living badge where it applies;
  - spouses after `=`, with their clan if it's another one;
  - on the right, the subclan badge and the count.
- **Outline search:**
  - AND-match words against name, nickname and spouse names;
  - highlight matches in place with `<mark>`;
  - report *"3 matches shown · 1 more in collapsed or deeper branches"*.
- **An "Expand all (N collapsed)" button** appears when anything is collapsed.
- **The caption** under the page title is the print caption without the date.

## 7. Photos (§2.11)

- **Resize on upload:** portraits to at most 480 px on the long edge, family and group
  photos to at most 1400 px, saved as JPEG at quality 0.82. Store the width and height
  (print uses them to lay out the head photo).
- **The first photo of a kind becomes the main one** (`is_primary`). "Make main"
  switches it. Deleting the main photo promotes the next one.
- **Order within a kind:** main first, then by year (photos without a year last), then
  by id.
- **Year** is optional and must be four digits. The message is: *"Year should be four
  digits, like 1998 — or leave it blank."* After uploading without a year, the toast
  adds: *"Add the year if anyone remembers it."*
- **Where uploads happen:**
  - Person page: Portrait; a Family picture per marriage; "Family picture — X and
    children (no marriage recorded)" when the person has children with no other parent
    (`kind=family`, `person_id`); and Subclan group photo if they're a head.
  - Clan settings: Clan group photos. The main one also shows as a banner on the clan
    list card.
- **The group-photo caption placeholder** is *"Back row, L–R: … · Front row, L–R: …"*.
- **Deleting a person** deletes their photos and the photos of their marriages.

## 8. Print (screen 10)

- **Formats:** Tree chart (sheet presets: Tarpaulin 8 × 4 ft = 2438 × 1219 mm, A3
  landscape/portrait, A4 landscape/portrait, Custom), or Indented outline on A4
  portrait. Margin is 10 mm. The print route sets `@page` to the sheet size; Chrome
  or Edge "Save as PDF" honours it (`architecture-local.md` §6).
- **Print uses the same descendants query**, so cross-clan descendants print too,
  labelled with their clan: *"Bea Santos (Menis clan)"*.
- **"Scale to fill the sheet"** (default on) scales the chart to fit, up to 4×. The
  preview shows:
  - a badge: *Fits*, *Names below 45 mm*, or *Doesn't fit*;
  - how many boxes are in the widest row, and how many 45 mm names the sheet holds
    across;
  - the printed box width in mm.
- **Caption formats (§4.1):**
  - Clan numbering: *"Santos clan · Gen 1 = founding couple Isko and Sela · printed
    27 September 2026"*, with *"branch of X · "* inserted when the start isn't the
    founder.
  - Relative numbering: *"Santos clan · subclan of Andres Santos (Gen 0) · printed 27
    September 2026"* ("descendants of" when the start isn't a marked head).
  - Add *" · living people: name only"* when that option is on.
  - Add *" · ARCHIVE COPY, includes hidden links"* when that option is on.
- **Title:** the subclan name for a relative-numbered subclan sheet, "The Santos clan"
  when starting from the founder, otherwise "Descendants of X".
- **"Group photo at the head"** (default on): the main clan group photo when the start
  is the founder, the head's main subclan group photo when the start is a subclan
  head, and nothing otherwise. The photo's caption and year print under it.
- **"Show photos":** puts the main portrait on each box in the tree chart. In the
  outline, it places the main family picture (about 62 mm wide, with caption) under
  that family's line.
- **The outline printout** carries the sibling position on each line. Collapsed or
  depth-cut branches print *"[N more below, printed separately]"*.
- **"Print this view"** on Tree / Outline carries over the start, depth, numbering,
  dates, photos, collapsed branches and format.

## 9. Sample seed data (mirror this in a Laravel seeder)

**Santos clan** (origin San Roque). The founders are **Isko = Sela**, married "before
1860", both known by one name only. Their children, in order:

1. **Ambo** — died young, no dates. Note: "there was one before Juan".
2. **Juan Santos** (abt. 1862–1931) = Petra Lim, married in.
   1. **Maria Clara** (1898–1971) = Pedro Cruz. Their children, whose clan line is
      through their mother: Josefa Cruz, then Benito Cruz.
   2. **Andres "Ando"** (abt. 1901–1988), subclan head "Ando's line", = Lucia Reyes
      (married 1926). Their children:
      1. **Teodoro** (1928–2004) = Amparo Diaz. Their children:
         1. Andres (b. 1956, living) — a reused name, on purpose.
         2. **Liza** (b. 1958, living) — see Dulnuan.
         3. Marco (b. 1961, living), whose son is **Paolo** (b. 1990, living). Only
            Paolo's father is recorded; parentage note: "Mother not named at the
            family's request." Paolo married Annie Claire Menis (see Menis).
      2. **Elena "Lenny"** (1931–2019).
      3. **Ramon** (abt. 1934–1999).
3. **Tomas Santos** (1865–1940). Child: **Pilar**, recorded as adopted by father only;
   parentage note "Taken in by Tomas after the flood of 1910."
4. **Rosa Santos** (1869–1950), subclan head "Reyes branch", = Ignacio Reyes. Their
   children, whose clan line is through their mother: Carmen Reyes, Vicente Reyes.

Two Santos people are unplaced: **Tomasa "Masang"** ("before the war") and **Nicolas**
(abt. 1910).

**Dulnuan clan** (origin Kiangan). The founders are **Pablo Dulnuan = Ines**. Their
children:

1. **Pedro Dulnuan** (1910–1985), subclan head "Pedro's line", = Carmen Bautista.
   Their children:
   1. **Lito** (1938–2001). Child: **Grace**, with father only recorded.
   2. **Benjamin "Benjie"** (b. 1955, living), **married to Liza Santos** in a
      cross-clan marriage. Their children are Dulnuan members through their father:
      Mark, then the twins **Ana and Joy**. They also appear under Liza in the Santos
      tree.
2. **Maria Dulnuan** = Jose Aquino. Child: **Rosario Aquino**, whose clan line is
   through her mother.

One Dulnuan person is unplaced: **Tomas Dulnuan**.

**Menis clan** (origin Tagudin). This is the plan's §2.7 example. The founders are
**Ramon Menis = Clara**. Their children:

1. **Annie Claire Menis** (b. 1992), **married to Paolo Santos** (June 2015). They
   have three children, one sibling set numbered 1–3:
   1. **Luis Santos** (2016) — clan-line parent Paolo, so a **Santos** member.
   2. **Bea Santos** (2018) — clan-line parent Annie, so a **Menis** member.
   3. **Carlo Santos** (2021) — clan-line parent Annie, so a **Menis** member.
2. **Jun Menis** (b. 1995).

All three children appear in both the Santos and the Menis trees: Gen 7 in the
Santos chart and Gen 3 in the Menis chart.

---

## 10. Acceptance checklist, by phase

**Phase 0** (see `architecture-local.md`)
- [ ] Laravel + Inertia + React on MySQL 8 (`utf8mb4_0900_ai_ci`), served on
  `127.0.0.1` only, production Vite build, working with the network off.
- [ ] One-click backup: a timestamped zip of `mysqldump --single-transaction` plus the
  photos folder, written to a folder outside the project. In-app restore (with a
  safety backup first) works and renumbers every clan.
- [ ] `php artisan app:migrate` backs up, then migrates.
- [ ] Seeders for the three sample clans (§9), in a separate demo database.

**Phase 1**
- [ ] Migrations for `clans`, `people`, `marriages` and `photos` exactly as in
  `data-model.md`.
- [ ] A global clan scope on membership lists; a clan selector in the header.
- [ ] Clan list: name, founding couple, and counts (people, generations, subclans,
  unplaced). Add clan, optionally with the founding couple typed in.
- [ ] Clan delete is soft, shows the people count in the confirmation, and can be
  restored from the clan list.
- [ ] Person form: only given name required; dates as an exact date plus "as written";
  living as yes / no / not known.
- [ ] People list: search, generation filter, show filter (everyone, numbered,
  unplaced, married in, living, subclan heads), sort by generation then family order.
  Members only.

**Phase 2**
- [ ] Parent pickers: clan-line (same clan only) and other (any clan), with relations.
- [ ] Cycle check; cross-clan clan-line parent check; "other parent only" refused.
- [ ] `sibling_order` auto-assigned, dense, renumbered on insert, move, delete and
  parent change; a position field on the form; up/down arrows on the person page.
- [ ] Inline add children, with nickname, where row order is sibling order.
- [ ] Inline add marriage: search across clans first, create second, no sex
  pre-selected, and husband/wife assignment per §5.
- [ ] Set founding couple, with the preview, an automatic backup first, a single
  transaction, and the note appended to the clan notes.

**Phase 3**
- [ ] Person page: parents (clan-line marked), marriages (other-clan spouse labelled),
  and **all** of the person's children in sibling order, grouped by other parent, with
  cross-clan children labelled. Plus "Child 2 of 3 · Juan Santos and Petra Lim".
- [ ] Unplaced filter and a "To place" panel on the people list.
- [ ] Subclan head flag and subclan name on the form; a Subclans list in clan
  settings.
- [ ] Photos: all four kinds, with caption, year, main picture, resize on upload,
  delete.

**Phase 4**
- [ ] Tree / Outline on one page with one menu item and a toggle that keeps the state.
- [ ] One descendants query (§6.1) with the global scope disabled, dedup, and root,
  depth and collapsed set as parameters. Tests use the §9 sample data.
- [ ] Siblings left to right in the tree and in order in the outline, whatever clan
  each child belongs to; cross-clan boxes and lines labelled.
- [ ] Chart generation shown as depth along the path; relative numbering where the
  start is Gen 0.
- [ ] Collapse and expand; outline search with in-place highlight; "N in this chart"
  counts.
- [ ] Show in tree from the list and the person page, keeping the view, depth and
  numbering.

**Phase 5**
- [ ] Print from either view: `@page` at the chosen sheet size (tarpaulin included,
  via Chrome/Edge "Save as PDF"); `data-theme="print"`.
- [ ] Caption line on every chart and outline.
- [ ] Living people name only (default on); archive toggle for hidden links (default
  off).
- [ ] Group photo at the head; portraits on boxes; family pictures in the outline.
- [ ] Fit badge and the 45 mm check.
