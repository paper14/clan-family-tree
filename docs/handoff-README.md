# Clan Family Tree — handoff package

Everything Claude Code needs to build the app. Nothing here is code to run yet; the
repository doesn't exist until Phase 0.

## What's inside

```
CLAUDE.md                     the build brief — read first; Claude Code reads it automatically
docs/planning.md              v0.19 — the specification: schema, rules, screens, print, phases
docs/data-model.md            migration-ready schema; the authority for writing migrations
docs/architecture-local.md    local web app: serving, MySQL, safe migrations, backup/restore, printing, offline
docs/IMPLEMENTING.md          how to build it: order within each phase, what to prove, the traps
docs/implementation-notes.md  prototype decisions, exact wording, edge cases, seed data, per-phase checklists
design-system/                tokens, brand book, React components — the look
prototype/                    a complete working prototype in one HTML file — the behaviour reference
screenshots/                  every screen at 1440 px, plus the dark theme
```

**When two documents disagree:** `data-model.md` and `architecture-local.md` win
over `planning.md` and `implementation-notes.md` on the schema and on anything
operational. `CLAUDE.md` says this too.

## Setting up the Claude Code session

The brief only works if Claude Code can see it, which means `CLAUDE.md` has to sit at
the repository root. It can't be in a subfolder, and it shouldn't be pasted into a
prompt.

1. Create the project directory and scaffold Laravel in it (Phase 0).
2. Copy this package in so the repository looks like this:

   ```
   <repo>/CLAUDE.md
   <repo>/docs/
   <repo>/design-system/          → later copied to resources/js/design-system/
   <repo>/prototype/
   <repo>/screenshots/
   ```

3. Start Claude Code from `<repo>`. It picks up `CLAUDE.md` on its own; the docs are
   there for it to read as it goes.
4. Open `prototype/clan-family-tree-demo.html` in Chrome, press **Reset demo**, and
   keep it open in a tab. It's faster to click through than to describe, and it's the
   tiebreaker for any question about behaviour or wording.

Commit the package with the repo. These documents explain decisions that aren't
obvious from the code, and they'll be worth more in six months than they are today.

## Phase 0, before any feature work

`CLAUDE.md` lists the phases and `docs/IMPLEMENTING.md` gives the working order for all
of them. This is the order for the first one, which is the only phase whose mistakes
are expensive to undo.

1. The Laravel project, then Inertia.js with the React adapter, then Vite and React 18.
   Check the current install steps for each at setup time rather than from memory.
2. MySQL 8 as the connection, database created with `utf8mb4` /
   `utf8mb4_0900_ai_ci`, and a second database (`clan_demo`) for seeders.
3. Local-only serving on `127.0.0.1`, with the start-up steps written in the repo README
   for a non-developer user (for example, a Laragon start button, or a
   `start.bat` that runs the server and opens the browser).
4. Self-hosted fonts and a production Vite build. Check that it works with the network
   off.
5. Backup and restore, working and tested, before any real data exists
   (`docs/architecture-local.md` §4), plus the `php artisan app:migrate` command that
   backs up before migrating. Restore is the half that gets skipped, and it's the half
   that matters.
6. Seeders for the three sample clans (`docs/implementation-notes.md` §9), into the
   demo database. Santos,
   Dulnuan and Menis between them exercise every rule, including the §2.7 cross-clan
   descent example.

Only then start Phase 1. Getting the setup and backup right while the database is
empty costs an afternoon; fixing them after a thousand hand-typed people costs the
records.

## A note on what this data is

The registry is transcribed by hand from handwritten records, and for the older
generations there's no other copy anywhere. Treat `people` rows as irreplaceable:
never write a migration that drops or rewrites them, and prefer a soft delete and a
warning over anything clever.
