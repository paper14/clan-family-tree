# Clan Family Tree — Planning Document

Version 0.19 · 2026-09-27 · residual scoping contradictions corrected (local web app on MySQL, per v0.18)

## 1. Purpose

A single-user **web app, run locally on one computer and opened in the browser**, that
holds clan registries, shows each as a tree, and prints it — for the whole clan, or for
a subclan that wants its own sheet for its own reunion. It needs no internet once
installed (§5). The first clan runs to a 7th generation from its
founding couple. Source records are handwritten, so most of the work is typing
them in.

Design rule for this project: **simple database, simple app.** Four tables,
plain forms, a tree view, and print. Anything that isn't one of those four things
is out unless it earns its way back in later.

## 2. Database

### 2.1 Decision — `clan_id` is membership, not visibility

The app holds several clans that are unrelated to each other. Each has its own
founding couple, its own member list, its own subclans and its own printouts.

**Decision: a `clans` table, and every person carries a `clan_id`.** A person belongs
to exactly one clan.

`clan_id` answers exactly one question: **which registry is this person a member of** —
which reunion they attend, which member list and subclan they belong to, and whose
founder their own generation is counted from.

**It does not decide who may appear in a tree.** Earlier versions of this plan used
`clan_id` as a filter on the descendant query, which made it do a second job it has no
business doing, and the result was wrong charts (§2.7). Descent is carried by
`father_id` and `mother_id`. Those links exist whatever clan anyone was assigned to,
and they are what the tree follows.

So the rule is:

- **Membership lists are scoped** — the people list, generation filters, subclan lists,
  member counts.
- **Descent is not scoped** — a person's own page, the tree, the outline and the
  printouts all follow parent links (§2.7).
- The add-marriage search deliberately searches every clan, so an existing person is
  linked rather than duplicated (§2.7).

This also replaces what would otherwise have been a settings table: clan name,
founding couple and notes live on the clan row.

### 2.2 Decision — parent columns, not a union table

Each person carries `father_id` and `mother_id`. Marriages get their own small
table for dates and places.

Reasoning:

- A person's parents are one lookup instead of a join through link tables.
- Children of a person are `WHERE father_id = X OR mother_id = X`. Siblings are
  the same parent pair. Both are one-line queries.
- Half-siblings and second marriages are just a different parent pair.
- A child with only one recorded parent needs no special case, which matters here
  more than usual (§2.8).

What this gives up: exporting to GEDCOM becomes a small conversion script rather
than a direct mapping. Fair trade for a small schema.

### 2.3 Tables

**clans**

| Column | Notes |
|---|---|
| `id` | |
| `name` | e.g. the founding surname or the name the family uses |
| `founder_id`, `founder_spouse_id` | nullable, point at `people` — generation 1, and movable (§2.4) |
| `origin_place` | optional |
| `notes` | free text — oral history, where the records came from, previous founders (§2.4) |

**people**

| Column | Notes |
|---|---|
| `id` | |
| `clan_id` | **required** — the clan this person belongs to |
| `given_name` | **the only required name field** |
| `middle_name`, `last_name`, `suffix`, `nickname` | all optional — a founder with one name is a valid record |
| `sex` | male / female / unknown |
| `is_living` | nullable |
| `birth_date` | nullable DATE — for display and reference only, never for ordering (§2.6) |
| `birth_date_text` | free text — "abt. 1892", "before the war" |
| `birth_place` | |
| `death_date`, `death_date_text`, `death_place` | same pattern |
| `sibling_order` | small integer, **NOT NULL** — position among siblings, first to last. The only sequence authority (§2.6) |
| `occupation`, `residence`, `phone`, `email` | all optional |
| `notes` | free text — including the source a researched ancestor came from |
| `father_id`, `mother_id` | nullable — the clan-line parent is the one that matters, the spouse is optional (§2.8) |
| `father_relation`, `mother_relation` | biological / adopted / step / foster — default biological |
| `parentage_note` | free text, in the family's own words |
| `hide_second_parent` | boolean, default false — the rare case in §2.8 |
| `generation` | cached integer, absolute — distance from this clan's current founding couple |
| `is_subclan_head` | boolean, default false — marks a recurring subclan root (§2.5) |
| `subclan_name` | nullable — what that subclan calls itself |

**marriages**

| Column | Notes |
|---|---|
| `id` | |
| `husband_id`, `wife_id` | either may be null; the two may belong to different clans (§2.7) |
| `date`, `date_text`, `place` | |
| `status` | married / separated / widowed / unknown |
| `notes` | |

**photos**

| Column | Notes |
|---|---|
| `id` | |
| `clan_id` | NOT NULL — scoping |
| `person_id` | nullable — a person (portrait) or a subclan head (subclan group photo) |
| `marriage_id` | nullable — a family picture |
| `kind` | `portrait` / `family` / `subclan_group` / `clan_group` (§2.11) |
| `file_path` | |
| `caption` | including who is who, in row order |
| `year` | nullable — the field most worth capturing and most easily lost |
| `is_primary` | which image represents that person, family, subclan or clan |

That is the whole database.

### 2.4 Decision — the root moves when earlier ancestors are identified

Research will sometimes turn up the founders' own parents. When it does, **the
newly identified ancestor becomes the clan's founding couple**, and generation
numbering starts again from them.

The schema needs no change: `father_id` and `mother_id` are already nullable
self-references, and a person with no parents is already legal. Moving the root is
re-pointing `clans.founder_id` and recomputing `generation`.

Three things follow:

**1. Moving the root is an explicit clan-level action, not a side effect.**
A "Set founding couple" action re-points the founder and recomputes every
`generation` in that clan in one transaction. Generation must *not* be silently
recalculated whenever a person is saved — a mistyped parent link would then
renumber the whole clan without anyone noticing.

**2. Every printout states what its generations were counted from** (§4.1).

**3. Superseded founders are recorded in the clan notes.** A line of text with the
previous founding couple and the date. No history table.

**Consequence worth expecting:** identifying the founders' parents usually also
identifies the founders' *siblings*, whose descendants are a collateral branch that
can be larger than the clan as currently recorded. A recording decision to make at
the time, not something the app should prevent.

### 2.5 Decision — generation numbering is relative at display time; Gen 0 is the chosen root

A subclan will sometimes want its own printed list for its own reunion, numbered
from its own head rather than from the clan founder.

**Decision: `generation` stays absolute in the database, and any view or printout
can renumber relative to a chosen person.** The displayed number is
`person.generation − root.generation`, so the chosen root prints as **Gen 0**, its
children as Gen 1. Nothing is stored, nothing is recomputed, and switching roots is
a different subtraction on the same data.

This has a property worth noticing: **subclan sheets are stable across a root
move.** When the clan root moves (§2.4) every absolute generation shifts by one,
but the *difference* between two people does not. A sheet numbered from a subclan
head stays correct forever, while the main clan chart is renumbered.

Numbering from the clan founder remains the default, with Gen 1 the founding
couple; Gen 0 appears only when a subclan root is deliberately chosen.

**Marking subclan heads.** The same branches will ask year after year, so a person
can be flagged `is_subclan_head` with an optional `subclan_name`. The print and
list screens then offer those heads in a dropdown. Two nullable columns, no new
table. Subclans may nest, since numbering is only ever relative to the chosen root.

### 2.6 Decision — siblings are sequenced by position only, never by date

The order of children matters — it is how families recite their own lines, and it
is what a printed chart is read against. But birth dates are mostly unavailable:
in the older generations there is often no date at all, or only an approximate
year.

So dates are not used for sequencing at any point. **`people.sibling_order` is a
NOT NULL small integer giving the person's position among their siblings — first
child to last child — and it is the only thing that determines order anywhere in
the app.** `birth_date` is recorded where known and displayed, but never sorted on.

**Why NOT NULL rather than a fallback chain.** An earlier draft sorted by order
where present, then by date, then by entry order. That is worse than it sounds: a
family where three children have a recorded order and two do not gets sorted by
mixed authority, and the result is arbitrary in a way nobody can see or correct. A
column that is always populated has one rule, one behaviour, and nothing to reason
about.

Every child therefore gets a `sibling_order` the moment they are added — the next
available number in that sibling set — so there is never an unordered state to
resolve. The value means *"this is the sequence recorded"*, not *"this is a
verified birth sequence"*, which is the honest claim given the sources.

Scope: position within the sibling set, meaning children of the same parent pair.
Where a parent had children by two spouses, each set is numbered from 1; the true
combined sequence across both, where it is known, belongs in `notes`.

**Numbering is dense (1…n) and renumbered within the sibling set on insert or
reorder.** Inserting a sibling is normal rather than exceptional: a child who died
young is very often remembered later — *"there was one before Juan"* — and has to
go between two existing children. Renumbering a handful of siblings is cheap, and
dense numbers read correctly on screen and in print.

A child remembered only as having existed is enterable as a person with a name and
a position and nothing else, which the name-only rule (§2.9) already allows. That
is a large part of why that rule matters.

**Entry and correction.** The add-children screen enters siblings in order — row
order *is* sibling order, so a family typed straight off the page is already
correct. Order is fixed afterwards with up/down controls on the family view;
simpler to build than drag-and-drop and more precise to use.

Because the order is just a position, correcting it is a two-click operation with
no data consequences. That matters at a reunion, where elders reliably correct the
sequence of families they remember — and where sequence is genuinely uncertain, say
so in `notes` rather than letting a printed chart imply more certainty than the
source has.

Twins take consecutive positions with a note; no separate column.

### 2.7 Decision — the tree follows parent links, not clan membership

Two recorded clans in the same province will eventually intermarry. When someone from
clan B marries into clan A, the temptation is to type them in again as a clan A person.

**Decision: one person record, and the marriage spans the two clans.** The `marriages`
table already just links two person ids, so this costs nothing. Duplicating the person
instead would give two records that drift apart, and the same human appearing twice in
the registry with different dates.

A child of that marriage takes the `clan_id` of its clan-line parent (§2.8). **That
assignment decides which registry the child is a member of. It does not decide which
trees the child may appear in.**

#### The failure this replaces

Filtering the descendant query by `clan_id` produced charts that were simply wrong, in
two ways that look different but share one cause.

Paolo Santos and Annie Claire Menis have three children. One was recorded with Paolo as
the clan-line parent, two with Annie. The Santos tree drew one child and hid the other
two behind a "2 children in another clan" pill — showing a couple only part of their own
family. Separately, Liza Santos's own page showed her as childless, because her children
are Dulnuan through their father.

Both come from `clan_id` being used to decide visibility. The children are linked to
both parents already; nothing was missing from the data.

#### The rule

**The descendants query walks `father_id` / `mother_id`. It does not filter on
`clan_id`.**

Walking *down* from a clan's founder can only ever reach that founder's blood
descendants. Paolo's children are his children; their children are his grandchildren.
The chart never arrives at an unrelated family, because it only ever goes downward.

The danger that earlier versions were guarding against is real, but it lies in the
other direction: walking *up* from Annie would reach her parents, siblings and cousins,
and splice a whole unrelated clan into the Santos chart. **That boundary stays exactly
where it was — the tree never walks upward into another clan.** Annie appears as a
spouse box labelled with her clan, and nothing above or beside her is drawn.

#### What follows from it

- **Generation displayed in a tree is depth along the path walked**, from the root being
  viewed. Paolo's three children all display as Gen 7 in the Santos tree, regardless of
  which registry each belongs to. Their stored `generation` — depth from their own
  clan's founder — is unchanged and is what their own clan's sheets use.
- **Cross-clan nodes are labelled** with the clan they are members of, as a cross-clan
  spouse already is. Opening one switches clan.
- **A person may appear in two clans' trees.** This is not duplication; they descend
  from both families, and every person belongs to more than one family tree. It means
  the same grandchild prints on two reunions' sheets, which is correct.
- **Counts mean "people in this chart"**, not "members of this clan" — the count of what
  the walk returns at the current root and depth. That is also the more useful number,
  because it is the number of boxes that will be printed (§4.4).
- **Dedup by person id.** When two members of the same clan marry each other, a child
  has two paths to the founder. Keep the first and shortest; draw each person once.
- **Sibling sets are unaffected.** A set is the `(father_id, mother_id)` pair, which is
  clan-independent, so all of a couple's children sort together by `sibling_order`
  whatever their `clan_id` (§2.6).

#### The size consequence, stated plainly

Clan charts get bigger under this rule — because they were under-reporting before.
Every child previously assigned to a spouse's clan line was being pruned from a chart
they belong on. Where two clans have intermarried repeatedly, each clan's tree will
include a substantial part of the other's descendants. That is accurate, and depth
limits, branch collapsing and subclan sheets (§4.4) are how it is made printable.

### 2.8 Decision — the clan-line parent anchors the person; the spouse is optional

For everyone below a founding couple, one parent is the clan-line parent — the one
descended from that clan's founders. That link places the person in the tree, and
in practice it is always known, because it is how the family remembers them.

**The other parent — the spouse who married in — is an optional field, and blank
is a normal, valid state.** This covers separated parents and solo parents, and
covers a family who does not want the other parent named, without special
machinery.

Why blank is enough: **a parent who married in carries no clan descent.** Nothing
about the registry's purpose is lost by leaving them out.

`parentage_note` holds the family's own wording where it matters — "raised by
mother", "father not named at the family's request".

**The one residual case**, kept as a single boolean: both parents are clan members
and one is not to be shown. Blanking that link *would* destroy descent, so the link
is recorded and `hide_second_parent` suppresses it in views and print. Suppression
works in both directions — the child is hidden from that parent's descendant view
as well, or it leaks on the other side.

### 2.9 Decision — only the name is required

Nothing but `clan_id` and `given_name` is validated as required, on any form, at
any layer. A founding couple known by a single name each with no other data is a
complete record, not a broken one. So is a child remembered only as having been
born between two others (§2.6). Dates are a nullable DATE plus a free-text field for
what the source actually said — recorded for reference, and **never used to order
anyone** (§2.6).

### 2.10 Decision — no parent link is ever required

A person with no parents recorded is legal and expected: the current founding
couple have none, spouses who married in have none, and during transcription a name
read off a page before its parents are entered has none yet.

Such a person does not appear in the descendant tree until a parent is linked. A
display consequence, not a validation error — which gives the "typed in but not yet
placed" case for free, since handwritten records rarely read in tree order.

### 2.11 Decision — one photos table for every kind of image

Four kinds of image matter: a **portrait** of a person, a **family** picture of a
couple with their children, a **subclan group** photo, and a **clan group** photo.
All four are the same thing — a file with a caption and a year — and all four
accumulate, because every reunion produces new ones.

**Decision: a fourth table, `photos`, and `people.photo_path` is dropped.** A column
per kind would cap each at one image and break at the second reunion. A table costs
one join and holds a series.

Attachment is two nullable foreign keys plus `kind`:

| kind | attaches to |
|---|---|
| `portrait` | `person_id` |
| `family` | `marriage_id` — or `person_id`, where a solo parent has no marriage row (§2.8) |
| `subclan_group` | `person_id`, the subclan head — already how a subclan is identified (§2.5) |
| `clan_group` | `clan_id` alone |

`kind` is what separates a portrait of Pedro from a group photo of Pedro's subclan:
both hang off the same person. `is_primary` picks the one to use where only one image
fits — a chart node, an outline row, the head of a printed sheet.

`year` matters more than it looks. A 1998 reunion photo with its year is far more use
to a grandchild than the same photo without it, and the year is the first thing lost
once the people who were there are gone.

**Naming people in a group photo** goes in `caption`, in row order — *"Back row,
L–R: …"* — the way family albums do it. Coordinate tagging has real genealogical
value here, but it is a larger build than everything in Phases 1–5 combined, so it
is deferred.

**Keep the camera originals outside the app**, alongside the photographs of the
handwritten pages, and let the app hold web-sized copies. Group photos are large and
the one-click backup zips the photo folder; originals would push it into hundreds of
megabytes, you would stop running it, and the backup would stop being a backup.

### 2.12 Rules worth enforcing

- A person cannot be their own ancestor. Walk up the parent chain before saving a
  parent link and reject a cycle.
- **The clan-line parent must be in the same clan as the child** — that link is what
  clan membership is derived from, so a cross-clan clan-line parent is a data-entry
  mistake.

  **The other parent may be in another clan**, and routinely is: that is exactly what a
  cross-clan marriage produces (§2.7). An earlier version of this rule said no parent
  link may cross clans, which would have made §2.7 impossible to record. Only the
  clan-line link is constrained.
- **An other parent with no clan-line parent is refused.** The other parent alone
  places nobody in a tree, and leaves the child's clan undecidable.
- Deleting a clan is guarded — it would take its people with it. Soft delete, with
  the person count shown in the confirmation.

Everything else is a warning at most.

## 3. Screens

1. **Clan list** — the landing screen. Each clan with its name, founding couple
   and person count. Add a clan here.
2. **Clan form** — name, founding couple, origin place, notes. Includes the
   "Set founding couple" action (§2.4), which warns that generation numbers across
   the clan will change.
3. **Clan selector** — persistent in the header once inside a clan. It scopes the
   **membership** views below it — the people list, filters, subclan lists and counts.
   It does not filter descent: the tree, the outline, print and a person's own children
   follow parent links across clans (§2.1, §2.7).
4. **People list** — search by name, filter by generation, sort. A filter for
   people with no parent linked yet, to work through during transcription. When a
   subclan root is selected, the generation column and filter switch to relative
   numbering with that head as Gen 0. Each row has a **Show in tree** action (§3.1).
5. **Person form** — one page, name at the top, everything else optional below.
   Includes the position among siblings (`sibling_order`, §2.6) and the subclan-head
   flag.
6. **Person detail** — the person, their parents, their marriages, their children
   **in sibling order** with up/down controls, each clickable. A cross-clan spouse is
   labelled with their clan. A **Show in tree** action (§3.1). Photo upload here for
   a portrait, for a **family picture** against each marriage, and for a subclan group
   photo where the person is a subclan head (§2.11).
7. **Add children (inline)** — from a clan-line parent, spouse optional, a
   repeating row of fields so a family can be typed in one pass; row order is
   sibling order. Each row takes given name and **nickname**, since children in
   handwritten records and in living memory are frequently known by nickname alone.
   The screen that gets the most use during transcription.
8. **Add marriage (inline)** — from a person's detail page, without leaving it.
   - **Search first, create second.** The spouse field searches by name and
     nickname across *all* clans, so an existing person is linked rather than
     retyped. This is the affordance that actually enforces §2.7 — a stated rule
     against duplicating people does nothing if the only convenient path is to type
     a new one.
   - Only when there is no match does the form create a new person inline, taking
     given name, **nickname**, surname or maiden surname, and **sex**.
   - Marriage fields: date, date text, place, status.
   - **Sex is functional on this form, not just descriptive.** `marriages` has
     `husband_id` and `wife_id`, so the spouse's sex is what decides which column
     they are written to. The rule: place the person whose sex is known in their
     matching column and the other in the remaining one; where neither sex is
     recorded, the assignment is arbitrary but must be consistent, and the pair still
     reads correctly everywhere because no view depends on which column is which.
     No sex is pre-selected from the other partner's — an assumed opposite is a guess
     the record may contradict.

   **Nickname belongs in this form specifically** because a spouse who married in is
   very often known to the family by nickname alone — it is how relatives will
   identify them at a reunion, and often the only name the handwritten record gives.
   Leaving it out would mean saving, navigating to the new person, and editing, on
   the one form most likely to be used at speed.
9. **Tree / Outline** — **one page and one menu item**, with a Tree | Outline
   toggle. Both are renderings of one query over one result set, so they cannot
   disagree, and switching preserves the chosen root, depth and numbering mode.

   Shared controls: starting person or marked subclan head, generation depth,
   numbering mode (from the clan founder, or relative with the starting person as
   Gen 0), show or hide dates and photos, collapse and expand of any branch.

   **Tree rendering** — siblings left to right in sibling order, first child
   leftmost, **all of a couple's children drawn whatever clan each belongs to**
   (§2.7). A couple box where a spouse is recorded, a single box where none is.
   A node whose member clan differs from the one being viewed is labelled with it.
   Primary portraits shown on nodes when photos are enabled.

   **Outline rendering** — indented by generation, one line per person: position,
   name with nickname in quotes, dates where known, spouse inline; generation number
   on each line; siblings in sibling order within each family. Plus:
   - Text search within the outline, highlighting matches in place rather than
     navigating away — the outline is searchable in a way the chart is not.
   - A descendant count per branch (*"Pedro Dulnuan — 42 descendants"*). Cheap from
     the same query, and directly useful: it is how you decide which subclans need
     their own sheet and how wide a tarpaulin has to be (§4.4). It counts everyone the
     walk returns below that branch, which is the number of boxes that will print, not
     the number who are members of this clan (§2.7).

   **Which rendering for what.** At seven generations the chart is width-constrained
   and must be split, while the outline scales down a page without limit. The tree is
   the better view for seeing shape and for the wall; the outline is the better one
   for finding people, checking work during transcription, and printing the full
   record. Keeping them on one page means the controls are learned once.
10. **Print** — the tree or the outline, with print options.

### 3.1 Show in tree

**Show in tree** appears on each people-list row and on the person detail page.
It opens the Tree / Outline page with **that person as the starting person**, keeping
the current view mode, depth and numbering mode.

Two deliberate choices:

- **Numbering mode is not changed.** Landing on a person does not switch them to
  Gen 0 — they are still being read in the context of the whole clan, and Gen 0
  belongs to the deliberate act of producing a subclan sheet (§2.5). The toggle is
  right there if that is what you want.
- **It honours the active rendering.** If you were last in Outline, Show in tree
  opens the outline rooted at that person. Forcing the chart would throw away the
  view you had chosen, and for a person deep in generation 6 the outline is usually
  the more readable landing place anyway.

This is the main way of moving between searching and browsing: find a name in the
list, jump to their position, read outward from there.

## 4. Print

Browser print (Ctrl+P → Save as PDF) with CSS `@page` setting a custom size. One
renderer for screen and paper; no PDF library. Siblings appear in sibling order
throughout.

A printout is **rooted** in one clan — it starts from that clan's founder or one of its
subclan heads — but it is not filtered to that clan's members. Descendants who are
members of another clan print too, labelled with it, because they are descendants of the
person the sheet is about (§2.7).

Options: starting person (or a marked subclan head from the dropdown), generation
numbering (from the clan founder, or relative with the starting person as Gen 0),
number of generations, custom width and height, show or hide dates and photos.

Group photos print where they belong: the clan group photo at the head of a full-clan
printout, the subclan group photo at the head of that subclan's sheet, and family
pictures alongside their family in the expanded booklet sections (§2.11). A subclan
handout headed by that subclan's own photo reads as theirs rather than as an extract
from something larger.

### 4.1 Decision — every printout captions its own numbering

Each chart and outline carries a caption naming the clan, who Gen 0 or Gen 1 is,
and the print date — for example *"Dulnuan clan · subclan of Pedro Dulnuan
(Gen 0) · printed 27 September 2026"*.

This does two jobs: it keeps an older printed chart intelligible after the clan
root has moved (§2.4), and it stops a subclan sheet numbered from its own head
being mistaken for a full-clan chart.

### 4.2 Decision — living people print name only

Dates, contact details, and residence are suppressed for anyone marked living.
Name and nickname print. A toggle, defaulting to hidden.

### 4.3 Hidden parent links

Where `hide_second_parent` is set, the link is suppressed by default. One
clearly-labelled toggle, off by default, includes hidden links for the single copy
kept as the family archive.

### 4.4 Practical note on seven generations

A readable name needs roughly 45 mm of width, so a 4 × 8 ft tarpaulin holds about
48 people across at its widest row. Seven full generations will not fit on one
sheet. Handled at print time: print the founding couple down three or four
generations for the main chart, then print each branch separately by choosing that
branch's head as the starting person.

Subclan sheets solve most of this in practice — a subclan printed from its own head
is both smaller and more useful to the people at that reunion than a slice of the
full chart. Moving the root adds a generation to every branch, so a chart that
fitted one sheet before may need splitting afterwards; subclan sheets are
unaffected (§2.5).

The outline rendering (screen 9) prints as a plain indented outline — name, dates, one
line per person, in sibling order — which fits a whole clan in twenty-odd A4 pages
and is the practical form for the full record. Its per-branch descendant counts are
also what tell you, before you pay a printer, whether a branch fits one sheet.

## 5. Build

- Laravel 11+ (PHP 8.3+), React 18 + Vite via Inertia.js, **MySQL 8**, run locally
  (`php artisan serve`, or a local stack such as Laragon or XAMPP) and opened in the
  browser at `localhost`.
- **Decision — a local web app, not a desktop app.** Version 0.16 planned a desktop
  package (NativePHP / Electron with SQLite) so the app could run on any machine. That
  is dropped: one computer, one browser, one MySQL server is the simpler thing to build
  and to keep running, and it is what the project started as. The data model, rules and
  screens are unchanged by this; only the plumbing differs.
- **Local only.** The server listens on `127.0.0.1` and is never exposed to the network,
  so there is no login. Opening it to other devices would need authentication and HTTPS
  first, and is out of scope.
- **Offline once installed.** Every asset is served by the app itself — self-hosted
  fonts, no CDN scripts, no remote images — so it works with the internet off.
- Photos in `storage/app/public/photos`, served through `php artisan storage:link`.
- MySQL: `utf8mb4` with an accent-insensitive collation (`utf8mb4_0900_ai_ci`), so a
  search for "Pena" finds "Peña". Recursive CTEs (MySQL 8) carry the descendants query
  and the cycle check. Full detail in `architecture-local.md`.
- Tree layout: `d3-hierarchy` for positioning, SVG for drawing.
- Scoping: a global query scope on `clan_id` for **membership views** — the people
  list, generation filters, subclan lists, member counts, the start dropdown. Worth
  setting up in Phase 1 rather than retrofitting. **Descent is deliberately outside it**
  (§2.1, §2.7): a person's own children, the tree, the outline and print all run with
  the scope disabled.
- One descendants query serving tree, outline and print, with the controls as its
  parameters, and one controls component shared by both renderings. Building the
  outline as a second, independent query is how the two views end up disagreeing about
  the same family.
- **That query recurses on `father_id` / `mother_id` and never filters on `clan_id`**
  (§2.7), deduplicating by person id. The global clan scope must be disabled for it
  explicitly — a recursive CTE that silently inherits the scope reintroduces exactly the
  bug §2.7 describes, and it fails quietly by drawing a smaller tree rather than by
  raising anything.
- Images: resize on upload to a web-sized copy; the app never stores camera originals
  (§2.11).
- Generation display: one helper taking a person and an optional root, returning the
  number to show. Every view and template goes through it, so absolute and relative
  numbering can never diverge.
- Sibling ordering: one scope, `ORDER BY sibling_order`. Every place children are
  listed — detail page, tree, outline, print — uses it, so no screen can show a
  family in a different order from another, and no query ever falls back to dates.

### 5.1 Backup

A one-click backup producing a timestamped zip of a **`mysqldump`** of the database
(`--single-transaction`, so it is consistent while the app is running) plus the photos
folder — all clans in one file. This is the one non-negotiable extra: the data is many
hours of transcription from paper, on a single machine, with no other copy.

Web-sized images only, so the zip stays small enough that running it stays habitual
(§2.11).

A backup is taken automatically in two places, not merely prompted for: **before
moving a clan's root**, since that renumbers every person in the clan in one
transaction, and **before database migrations run** — migrations go through one
command that backs up first. Backups default to a folder outside the project directory,
ideally on another drive — a backup stored only beside the thing it protects is not a
backup. Restore is a single action too, and is tested in Phase 0.

Photograph the handwritten pages before starting. They are the original and
currently the only copy.

## 6. Phases

| Phase | Deliverable |
|---|---|
| 0 | Laravel + Inertia + React scaffold, MySQL, local-only serving, safe-migrate command, backup and restore, seeders |
| 1 | Four tables, clan list and form, clan scoping, person form, people list |
| 2 | Parent links, inline add-children and add-marriage forms (both with nickname; marriage searches across clans before creating), auto-assigned sibling_order, ordering scope and up/down controls, cycle and cross-clan checks, set-founding-couple action |
| 3 | Person detail with navigation, search, unplaced-people filter, subclan-head marking; photo upload for portraits, family pictures, subclan and clan group photos |
| 4 | Combined Tree / Outline page with the view toggle, shared query and controls, Show in tree from list and detail, generation-display helper with relative numbering, per-branch descendant counts |
| 5 | Print — from either view, subclan selection and Gen 0 numbering, caption line, living-person suppression, hidden-link toggle |

Phase 0 comes first: getting the setup and backup right before there is
data to lose is far cheaper than retrofitting them. Phases 1–2 are then enough to start
transcribing, which is the long part.

## 7. Deferred

Not now, but noted so the reasoning isn't lost:

- **Reference numbering** (d'Aboville: `1`, `1.1`, `1.1.2`) — useful once branch
  sheets need to cross-reference each other and a clan has several people with the
  same name. Computable from the parent links at any time; no schema change. Note
  that it is derived directly from `sibling_order`, so the sequence needs to be right
  before these numbers are printed anywhere — `1.3` meaning "the third child"
  becomes wrong if a forgotten sibling is inserted afterwards. It also changes when
  the root moves, and can be rebased on a subclan head the same way generations are.
- **GEDCOM export** — a conversion script, per clan, worth writing once a clan's
  data is substantially complete.
- **Duplicate-name detection on save**, life events beyond birth/death/marriage,
  multiple photos per person.

## 8. Open items

None.
