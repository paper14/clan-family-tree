# Architecture — local web app

How the app runs: a Laravel + Inertia + React web app with MySQL 8, on one computer,
opened in the browser at `localhost`. This replaces the earlier desktop plan
(NativePHP / Electron / SQLite, planning v0.16–v0.17), which is dropped. Where any
document still mentions SQLite, NativePHP, Electron, app-data or `VACUUM INTO`, this
document wins.

---

## 1. The decision

**A single-user web app, served locally, opened in the browser.** That means one
computer, one MySQL server and one browser. It's what the project began as, and it's
the simplest thing to build and keep running. The data model, rules and screens are
unchanged; only the plumbing differs from the desktop plan.

### Requirements

| | |
|---|---|
| PHP | 8.3+ |
| Laravel | 11 or higher |
| MySQL | 8.0+ (recursive CTEs are required) |
| Node | 20+ (build only) |
| Browser | Chrome or Edge (used for printing to PDF, §6) |
| Serving | `php artisan serve`, or a local stack such as Laragon or XAMPP |

On Windows, a stack like Laragon gives PHP, MySQL and a start button in one install,
which suits a single non-developer user. Pick one and document the start-up steps in
the repo README. Check the current install steps at setup time; don't rely on memory.

---

## 2. Local only, no login

- The app listens on **`127.0.0.1` only** (the `php artisan serve` default). It is never
  exposed to the LAN or the internet.
- Because of that, there are no user accounts and no login. The person at the computer
  is the user.
- Opening it to other devices would need authentication, HTTPS and hardening first.
  That's out of scope, and it must not happen by accident (for example, never
  `--host=0.0.0.0`).
- Set `APP_ENV=production` and `APP_DEBUG=false` for everyday use, even though it's
  local. That way a stack trace never shows data unexpectedly, and errors look like the
  rest of the app.

---

## 3. Data, migrations and the risk they carry

- **Database:** MySQL 8, `utf8mb4`, collation `utf8mb4_0900_ai_ci` (accent- and
  case-insensitive), InnoDB for foreign keys.
- **Photos:** `storage/app/public/photos`, web-sized copies only, served through
  `php artisan storage:link`. File paths in `photos.file_path` are relative to that
  disk.
- **Migrations are the main way this app could destroy data.** A migration that works
  on a fresh seed but fails halfway through a real database leaves a half-migrated
  registry. Rules:
  1. **Back up before migrating, every time.** Add one command, `php artisan app:migrate`,
     that takes a backup (§4) and then runs `migrate --force`. Document it as the only
     way to migrate. A plain `migrate` bypasses the safety net, so the README should
     say not to use it.
  2. **Test every migration against a copy of real data**, not only a fresh seed:
     restore a recent backup into a scratch database and migrate that.
  3. **Never drop or rewrite `people` rows in a migration.** Add columns, backfill, and
     leave the old column in place if in doubt. Extra columns cost nothing; a lost
     branch can't be recovered.
  4. **No `migrate:fresh`, `db:wipe` or `migrate:refresh` against the real database.**
     If the seeders are ever run, they go into a separate `clan_demo` database.

---

## 4. Backup and restore

**A backup is a timestamped zip containing:**

- `mysqldump --single-transaction --routines --default-character-set=utf8mb4` of the
  registry database. `--single-transaction` gives a consistent snapshot while the app
  is running.
- the photos folder.

Name it like `clan-backup-2026-09-27-1430.zip`. If `mysqldump` isn't on the PATH, the
path to it is set in `.env`; the Phase 0 check proves it works on this machine.

**When a backup is taken:**

- on demand, from the one-click action in the sidebar (it shows when the last backup
  was taken);
- **automatically before "Set founding couple"**, because it renumbers every person in
  the clan in one transaction. The button reads "Back up, then set founding couple and
  renumber";
- **automatically before migrations** (§3).

**Where backups go:** a folder outside the project directory, set in `.env` and
shown in the app. It's best on another drive or a synced folder. A backup that lives
only next to the thing it protects is not a backup. Keep the last N automatic backups
(for example 20); never delete manual ones.

**Restore** is an action in the app. It asks for confirmation (showing the backup's
date and its clan and people counts), takes a safety backup of the current state
first, then loads the dump and replaces the photos folder. Afterwards it recomputes
stored generations for every clan, because an older file can bring back a different
founder. Test restore in Phase 0 while there's nothing to lose. It's the half that
gets skipped, and it's the half that matters.

---

## 5. MySQL specifics that affect the code

- **Accent-insensitive search** comes from the collation, so `LIKE '%pena%'` finds
  "Peña". This covers the add-marriage search (two characters, AND-matching every word,
  across every clan), the people-list search and the outline search. `search_text` in
  `data-model.md` is therefore **optional**. Add it only if a search turns out to need
  more than the collation gives.
- **Recursive CTEs** carry the descendants query and the cycle check: one recursive
  query per tree render, not one per node. **The descendants CTE must not inherit the
  global clan scope** (`data-model.md` §5, plan §2.7). MySQL limits recursion depth
  with `cte_max_recursion_depth` (default 1000), which is plenty for a family tree.
- **Sibling sets use the NULL-safe equality operator:**
  `WHERE father_id <=> ? AND mother_id <=> ?`. With plain `=`, NULL parents never
  match. That would make every one-parent family its own set, or none.
- **Enumerations** (`kind`, `status`, `sex`, `clan_parent`, relation columns) are
  string columns validated in the application with Laravel enum casts. Avoid MySQL
  `ENUM`: changing its values later needs a table rewrite.
- **Booleans** are `tinyint(1)`; cast them in the model.
- **Set founding couple** runs in one transaction and should be a handful of set-based
  updates, not a loop with a query per person.

---

## 6. Printing

Browser printing, with CSS `@page` setting the sheet size, from **Chrome or Edge**:

- The print route sets `@page { size: <width>mm <height>mm; margin: 10mm }` from the
  print options, and `data-theme="print"`.
- **Chrome and Edge honour a custom `@page` size when saving as PDF.** That's how a
  2438 × 1219 mm tarpaulin PDF is produced for the print shop, and the prototype does
  exactly this. Tell the user in the print screen to choose **"Save as PDF"** and turn
  on **"Background graphics"**.
- For A4 and A3, print straight to a desk printer the same way.
- Check early that the largest sheet produces a usable PDF on this machine. If a size
  limit turns up, fall back to tiling A-series sheets with registration marks, rather
  than shrinking type below the 45 mm name rule.
- A server-side PDF renderer (headless Chrome via Browsershot, for example) is
  **deferred**. Add it only if browser printing proves unreliable.

---

## 7. Offline, enforced

Once installed, the app must work with the internet off:

- fonts self-hosted via `@fontsource/source-serif-4` and `@fontsource/source-sans-3`;
- `d3-hierarchy` and every other dependency bundled by Vite, with no CDN script tags;
- no remote images, telemetry or external API calls;
- serve the production Vite build (`npm run build`) for everyday use, not the dev
  server.

To check it, turn the network off and click through every screen, including print.

---

## 8. What stays exactly as planned

None of these are affected by the change of platform:

- the four tables and their rules;
- `clan_id` as membership, with membership lists scoped and descent unscoped (plan
  §2.7);
- `sibling_order`;
- absolute stored generation, with chart generation counted along the path;
- Set founding couple, with its automatic backup;
- the hidden-parent boolean;
- the photos model;
- the Tree / Outline page;
- subclan Gen 0 numbering;
- every caption.
