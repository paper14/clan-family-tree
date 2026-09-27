# How to implement this

The order to build in, and the traps that cost the most to fix later. `CLAUDE.md`
states the rules; `implementation-notes.md` §10 lists what "done" means per phase.
**This document is the method: what to do first, and what to prove before moving on.**

---

## 1. How to run the sessions

- **One phase per session.** Start each with: *"Read CLAUDE.md and docs/. We are doing
  Phase N. Show me the plan before writing code."* Long sessions that span phases lose
  the earlier rules first, and the rules are the whole point of this package.
- **Finish a phase against its checklist** (`implementation-notes.md` §10) before
  starting the next. A half-finished phase is where contradictions get built in.
- **Keep the prototype open** in a tab, reset to the sample data. When behaviour or
  wording is unclear, click it rather than guess. It is the tiebreaker.
- **When documents disagree:** `data-model.md` and `architecture-local.md` win on schema
  and on anything operational; `planning.md` wins on rules and screens;
  `implementation-notes.md` fills gaps and never overrides. If the disagreement is real
  rather than a wording slip, stop and ask — don't pick one silently.

## 2. Build these three once, before anything uses them

Three pieces are used by nearly every screen. Built twice, they drift, and the
symptom appears somewhere far away — two screens disagreeing about the same family.

| Piece | Where | What it is |
|---|---|---|
| Descendants query | Phase 4, before the tree UI | One recursive CTE: root, depth, collapsed set. Scope disabled, dedup by person id, never walks up |
| Generation display helper | Phase 4 | Takes a person and an optional root; returns the number to show. Every view and template calls it |
| Sibling ordering scope | Phase 2, before any children are listed | `ORDER BY sibling_order`, used by every children listing |

Write each with its tests in the same commit as its first caller. None of them is
worth "getting back to".

## 3. Phase order, and why each step comes where it does

### Phase 0 — the only phase whose mistakes are expensive
Scaffold, then MySQL with `utf8mb4_0900_ai_ci`, then local-only serving, then **backup
and restore working and tested**, then `php artisan app:migrate`, then seeders into the
demo database.

Do backup and restore **while the database is empty**. Restore is the half that gets
skipped, and testing it costs an afternoon now versus the records later. Prove it: seed
the demo data, back up, drop a clan, restore, confirm the clan and people counts match
and generations recomputed.

Check the tarpaulin PDF early too — one `@page` at 2438 × 1219 mm, Save as PDF in
Chrome, confirm it opens at the right size. If that fails, you want to know in Phase 0,
not in Phase 5 with a reunion booked.

### Phase 1 — tables and the two plainest screens
Migrations exactly as `data-model.md`. Then the global clan scope **on membership views
only**, then clan list and form, person form, people list.

Get the scope's boundary right here: it applies to membership lists. It must be
trivially removable, because Phase 4 depends on removing it.

### Phase 2 — descent, and the rules that protect it
Parent pickers first (clan-line: same clan; other: any clan), then the hard stops, then
`sibling_order` assignment and renumbering, then the two inline forms, then Set founding
couple.

`sibling_order` before the inline forms, because add-children depends on consecutive
numbering being correct. Set founding couple last, because it renumbers everything and
you want the rest stable before you build something that rewrites a whole clan in one
transaction.

### Phase 3 — the person page and photos
Person page (parents, marriages, **all** children grouped by other parent, cross-clan
ones labelled), unplaced filter, subclan-head marking, then photos.

The children list here is your first unscoped query. If Liza's Dulnuan children don't
appear on her page, the scope boundary from Phase 1 is wrong — fix it before Phase 4
builds on it.

### Phase 4 — the descendants query, then the UI
Query first with its tests, then the toggle page, then Show in tree.

Test with the sample data before any UI exists: the Santos tree at depth 8 returns
Luis, Bea and Carlo under Paolo and Annie, all at chart Gen 7, with Bea and Carlo marked
Menis. Get that green, then draw it.

### Phase 5 — print
Print reuses the Phase 4 query. If you find yourself writing a second query for print,
stop — that is the bug §2.7 describes, arriving by another door.

## 4. The traps, and what each one looks like when you hit it

- **The descendants CTE inherits the global scope.** Symptom: the tree looks fine, just
  smaller — cross-clan children silently missing. No error, ever. This is the bug that
  produced two wrong charts before it was found. Disable the scope explicitly and test
  for the Menis children by name.
- **Sibling sets compared with `=` instead of `<=>`.** Symptom: every one-parent family
  becomes its own sibling set, or none match. NULL parents are common here.
- **Ordering siblings by date "just as a fallback".** Most older people have no date;
  the result is arbitrary and nobody can see why. `sibling_order` is NOT NULL precisely
  so there is never a fallback to reach for.
- **Generation recomputed on every person save.** Symptom: one mistyped parent link
  renumbers a whole clan quietly. Only Set founding couple and restore renumber.
- **Chart generation confused with stored generation.** They are different numbers:
  stored is membership in that person's own clan; chart is depth along the path walked.
  Bea is Gen 3 stored and Gen 7 in the Santos chart, and both are right.
- **A required field creeping in.** Only `clan_id` and `given_name`. A founder with one
  name and nothing else is a complete record. Never render a blank as "Unknown".
- **`migrate` instead of `app:migrate`.** The plain command skips the backup. Document
  it, and consider making the plain one fail in this project.

## 5. What to verify at the end, with real data

Before the first reunion printout, transcribe one real branch and check:

1. A family typed straight off a page comes out in the right order, first child to last.
2. A forgotten child inserted mid-set renumbers the siblings and nothing else.
3. A subclan sheet from a marked head numbers that head as Gen 0.
4. The caption on every sheet names the clan, what Gen 0 or Gen 1 is, and the date.
5. Living people print name and nickname only.
6. Back up, restore into a scratch database, and confirm the counts match.

Then photograph the handwritten pages, if that hasn't happened yet. They are still the
only copy of the source.
