# Clan Family Tree — build brief for Claude Code

A single-user **web app, run locally on one computer and opened in the browser**. It
holds clan registries, shows each clan as a tree or an indented outline, and prints
them: the full clan, or one subclan's own sheet. The records are handwritten, so most
of the use is fast data entry. It needs no internet once installed.

**Stack (fixed):** Laravel 11+ (PHP 8.3+) · Inertia.js · React 18 + Vite · **MySQL 8** ·
served locally (`php artisan serve` or a local stack such as Laragon/XAMPP) on
`127.0.0.1` only. Tree layout with `d3-hierarchy`, drawn in SVG. **Not a desktop app:**
the NativePHP / Electron / SQLite plan from v0.16–v0.17 is dropped.

**Design rule:** simple database, simple app. Four tables, plain forms, a tree view,
and print. Anything else stays out unless it earns its way in.

## What's in this folder, and which one wins

Read in this order. **When two sources disagree, the higher one wins.**

1. `docs/planning.md` **v0.19** is the specification: schema, rules, screens, print,
   phases. (v0.19 corrects residual scoping wording in v0.18; no behaviour changed.)
2. `docs/data-model.md` is the migration-ready schema. It resolves the places where
   the plan and the implementation notes disagreed. Read it before writing migrations.
3. `docs/architecture-local.md` covers running it: local-only serving, MySQL
   specifics, safe migrations, backup and restore, printing, and offline constraints.
4. `docs/IMPLEMENTING.md` is the build method: what order to work in within each
   phase, what to prove before moving on, and the traps with the symptom each produces.
   Read it before starting a phase.
5. `docs/implementation-notes.md` covers decisions made while building the prototype
   that the plan leaves open: exact wording, edge cases, sample seed data, and an
   acceptance checklist for each phase.
6. `prototype/clan-family-tree-demo.html` is a complete working prototype in one file,
   matching the plan's behaviour (it runs in the browser on its own storage). **Use it as the behaviour reference:** open it in Chrome, press
   "Reset demo", and click through anything unclear. It stores data in the browser.
   Port its behaviour and wording, not its code structure.
7. `design-system/` controls the look: colour tokens, type, spacing, the brand book,
   and React components.
8. `screenshots/` shows every screen from the prototype at 1440 px wide.

Where any document still mentions SQLite, NativePHP, Electron, app-data or
`VACUUM INTO`, it predates the return to a local web app, and
`docs/architecture-local.md` wins.

## Design system: how to use it

- `design-system/react/` holds ready-to-use React 18 components (`Button`, `Field`,
  `DateField`, `Badge`, `PersonNode`, `CoupleNode`, `PersonRow`). Import
  `design-system/react/index.js` once at the app entry; it pulls in `clan.css` (tokens
  + component styles). Copy the folder into `resources/js/design-system/`.
- `design-system/README.md` is the brand book: tone, casing, the rule that blank is
  normal, colour roles, type, focus, print. **Read it before writing any UI copy.**
- Always use the tokens as CSS variables (`var(--lineage)`, `var(--space-4)`,
  `var(--font-serif)`). Never hard-code a hex value.
- There are three themes, switched with `data-theme` on `<html>`: `paper` (default),
  `lamplight` (dark), and `print`. The print route must set `data-theme="print"`.
- For fonts, self-host with `@fontsource/source-serif-4` and
  `@fontsource/source-sans-3`. **No Google Fonts, no CDN, no runtime network call
  anywhere** (see the offline rule below).
- Use the serif only for people's names and page titles; everything else is sans.
- `components/reference-bundle.js` and `components/index.d.ts` are the original
  browser-only versions of the components. They're kept for reference; the `.jsx`
  files replace them.
- There's no icon set. Use words and the genealogical marks `=`, `b.`, `d.` and `m.`.
  A few inline SVG arrows are fine.

## Settled: how React talks to Laravel

**Use Inertia.js with the React adapter.** The app is single-user and runs in its own
window against its own local Laravel process, so a JSON API buys nothing. It would add
a second layer of routing, validation-error handling and state. Inertia gives routing,
validation errors and flash messages for free, which suits a form-heavy registry.

The Tree / Outline page holds a lot of client state: view, start, depth, numbering,
dates, photos and collapsed branches. Keep that state in React, and fetch the
descendants payload once per root or depth change. Don't make each toggle a server
round trip.

## Non-negotiables (easy to get wrong)

- **Only `clan_id` and `given_name` are required**, at every layer. Blank is a
  complete, valid record. Never label a blank field "Unknown", and never show it as an
  error.
- **`clan_id` is membership, not visibility** (`planning.md` §2.1, §2.7). It decides
  which registry a person is a member of, and nothing else.
  - **Membership lists are scoped:** the people list, generation filters, subclan
    lists, member counts and the start-person dropdown. Add a global Eloquent scope on
    `clan_id` in Phase 1.
  - **Descent is not scoped:** a person's own page, the tree, the outline and every
    printout follow `father_id` / `mother_id` downward, whatever clan each descendant
    is a member of.
  - **The descendants query must explicitly disable the global clan scope.** A
    recursive CTE that silently inherits it brings back the old bug: it just draws a
    smaller tree and never raises an error. Write a test using the sample data (see
    `implementation-notes.md` §6.1).
  - **The tree never walks up.** A spouse from another clan is a single labelled box,
    with nothing above or beside them drawn.
  - The add-marriage search deliberately searches every clan.
- **Generation in a chart is depth along the path walked** from the starting person.
  The chart shows the start person's stored generation plus the depth (clan numbering),
  or just the depth (relative numbering, where the start is Gen 0). Stored `generation`
  stays absolute per the member's own clan, is nullable, and is used by the people
  list, the person page and the start dropdown through one helper.
- **Draw each person once.** When a child is reachable by two paths (two members of the
  same clan married), keep the first and shortest.
- **Counts on the Tree / Outline page mean "people in this chart"** at the current root
  and depth, which is the number of boxes that will print. They are not a count of clan
  members.
- **Siblings are ordered by `sibling_order` only** (NOT NULL, dense 1…n within a
  `(father_id, mother_id)` pair). The set doesn't depend on clan, so all of a couple's
  children sort together. Never order siblings by date, and never fall back to dates.
  Use one ordering scope everywhere.
- **Full renumbering happens only in "Set founding couple"** (and on restore). It runs
  in one transaction, with an automatic backup taken first. Saving a person recomputes
  only that person and their clan-line descendants.
- **One descendants query** feeds the tree, the outline and print. The controls are its
  parameters.
- **Hard stops (reject the save):**
  - a person can't be their own ancestor;
  - the **clan-line** parent must be in the same clan (the other parent may be in
    another clan; that's what a cross-clan marriage produces);
  - an other parent with no clan-line parent;
  - a blank given name.

  Everything else is a warning at most.
- **Living people print name and nickname only** by default. Hidden parent links print
  only when the archive toggle is on.
- **Every printout carries a caption** naming the clan, what Gen 0 or Gen 1 is, and
  the print date.
- **Photos are stored as web-sized copies only**, resized on upload, never camera
  originals.
- **Offline rule:** the app must work with no internet, ever. That means no CDN
  scripts, no remote fonts, no remote images, no telemetry, and no update check that
  blocks startup. Every asset ships in the bundle.

## Running locally (full detail in `docs/architecture-local.md`)

- **Local only, no login.** Serve on `127.0.0.1`; never bind to `0.0.0.0` or expose the
  app to the network. Everyday use runs with `APP_ENV=production`, `APP_DEBUG=false`
  and the built Vite assets.
- **MySQL 8, `utf8mb4_0900_ai_ci`**, so searches ignore accents ("Pena" finds
  "Peña"). Sibling sets use `<=>` so NULL parents match.
- **Migrations only through `php artisan app:migrate`**, which backs up first. Never
  `migrate:fresh`, `db:wipe` or `migrate:refresh` against the real database; seeders go
  to a separate demo database.
- **Backup is a timestamped zip of `mysqldump --single-transaction` plus the photos
  folder**, written to a folder outside the project (set in `.env`). It's taken on
  demand, automatically before Set founding couple, and automatically before
  migrations. Restore is an in-app action that takes a safety backup first.
- **Printing is the browser's print, from Chrome or Edge.** The print route sets
  `@page` to the chosen sheet size; "Save as PDF" then honours it, including the
  2438 × 1219 mm tarpaulin.

## Phases

| Phase | Deliverable |
|---|---|
| 0 | Laravel + Inertia + React scaffold, MySQL, local-only serving, `app:migrate` (backup then migrate), backup and restore, seeders into a demo database |
| 1 | Four tables, clan list and form, clan scoping, person form, people list |
| 2 | Parent links, inline add-children and add-marriage (search first, create second; nickname on both), auto `sibling_order`, ordering scope, up/down controls, cycle and cross-clan checks, Set founding couple |
| 3 | Person detail, search, unplaced filter, subclan-head marking, photo upload (portrait, family, subclan group, clan group) |
| 4 | Combined Tree / Outline page (one menu item, toggle), the unscoped descendants query with dedup, shared controls, Show in tree, generation display, chart counts |
| 5 | Print from either view: subclan Gen 0 numbering, caption, living-person suppression, hidden-link toggle, group photo at the head |

Phase 0 comes first. Getting the setup and backup right before there's
data to lose is much cheaper than fixing them later. Phases 1–2 are enough to start
transcribing. **Work phase by phase**: follow `docs/IMPLEMENTING.md` for the order
within each one, and check each phase against its checklist in
`docs/implementation-notes.md` §10 before moving on.

## Working conventions

- Use feature tests (Pest or PHPUnit) for every rule in "Non-negotiables". The most
  important are:
  - sibling renumbering;
  - the cycle check and the cross-clan check;
  - generation recompute;
  - husband/wife column assignment;
  - **the unscoped descendants query** (Paolo's three children appear under him in
    the Santos tree even though two are Menis members);
  - dedup;
  - backup and restore round-tripping.
- Seed the database with the prototype's **three** sample clans (Santos, Dulnuan,
  Menis) from `docs/implementation-notes.md` §9.
- Keep UI copy as in the prototype and brand book: sentence case, verb-first buttons,
  no exclamation marks, and the family's own words kept as written.
- Never write a migration that drops or rewrites `people` rows. This data is
  irreplaceable and hand-typed.
