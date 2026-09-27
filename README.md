# Clan Family Tree

Clan registries typed in from handwritten records, shown as a tree or an outline, and
printed — the whole clan, or one subclan's own sheet. It runs on this computer only and
needs no internet.

The build brief is [CLAUDE.md](CLAUDE.md); the specification and decisions are in [docs/](docs/).

---

## Everyday use

**Start:** double-click **`start.bat`**. The app opens in the browser at
<http://127.0.0.1:8000>. A small minimised window called "Clan Family Tree" is the app
running — **close that window to stop it.**

MySQL must be running (the "MySQL80" Windows service starts with the computer).

The app is only reachable from this computer. There is no login, so don't change it to
listen on the network.

### Backups

- **Back up now** is in the sidebar; it shows when the last backup was taken.
- Each backup is one zip: the whole database (all clans) and the photos folder, named like
  `clan-backup-2026-09-27-1430.zip`.
- They go to the folder set as `CLAN_BACKUP_PATH` in the `.env` file (currently
  `C:\Users\Nicko\Documents\ClanFamilyTree-backups`). **Copy backups off this computer
  regularly** — another drive, a USB stick or a synced folder.
- Backups are also taken automatically before **Set founding couple**, before any
  database upgrade, and before a restore. The last 20 automatic ones are kept; backups you
  take yourself are never deleted.
- **Restore:** sidebar → *Backups and restore* → pick a backup → *Restore*. It asks first,
  takes a safety backup of the current state, then replaces everything with the backup.
  To restore a backup kept elsewhere, copy its zip into the backup folder first.

Photograph the handwritten pages too. They are the original, and the only other copy.

### Printing

Use **Chrome or Edge**. In *Print*, pick the start person, format and sheet, then
**Print or save as PDF**. In the print dialog choose **Save as PDF** and turn on
**Background graphics**. The tarpaulin size (2438 × 1219 mm) comes out as a PDF of that
exact size for the print shop; A4 and A3 print straight to a desk printer.

### Photos

Add photos on a person's page (portrait, family pictures, subclan group photo) and in
Clan settings (clan group photos). The app keeps a web-sized copy only — keep the camera
originals in your own photo folders.

### Trying things out: the demo

Double-click **`start-demo.bat`** to open the three sample clans (Santos, Dulnuan, Menis)
at <http://127.0.0.1:8001>. They live in a separate `clan_demo` database, marked "Demo data"
in the sidebar, and nothing you do there touches the family registry. To reset the samples:

```
php artisan app:demo
```

---

## For whoever maintains it

**Stack:** Laravel 13 (PHP 8.4) · Inertia.js v2 · React 18 + Vite · MySQL 8 · `d3-hierarchy`
for the tree. Fonts are self-hosted via `@fontsource`; nothing loads from the network.

### Setting up on a new computer

1. PHP 8.3+ with `pdo_mysql`, `gd`, `zip`, `exif`; Composer; Node 20+ (build only);
   MySQL 8.0+.
2. Create the databases and a user (as MySQL root):

   ```sql
   CREATE DATABASE clan_registry CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
   CREATE DATABASE clan_demo     CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
   CREATE DATABASE clan_test     CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
   CREATE USER 'clan_app'@'localhost' IDENTIFIED BY '…';
   CREATE USER 'clan_app'@'127.0.0.1' IDENTIFIED BY '…';
   GRANT ALL ON clan_registry.* TO 'clan_app'@'localhost', 'clan_app'@'127.0.0.1';
   GRANT ALL ON clan_demo.*     TO 'clan_app'@'localhost', 'clan_app'@'127.0.0.1';
   GRANT ALL ON clan_test.*     TO 'clan_app'@'localhost', 'clan_app'@'127.0.0.1';
   GRANT ALL ON `clan_scratch%`.* TO 'clan_app'@'localhost', 'clan_app'@'127.0.0.1';
   -- mysqldump 8.0.32 needs this for --single-transaction (fixed in 8.0.33):
   GRANT FLUSH_TABLES ON *.* TO 'clan_app'@'localhost', 'clan_app'@'127.0.0.1';
   ```
3. `copy .env.example .env`, fill in `DB_PASSWORD`, `CLAN_BACKUP_PATH` (outside the
   project), and the `mysqldump` / `mysql` paths; then `php artisan key:generate`.
4. `composer install --no-dev`, `npm ci`, `npm run build`, `php artisan storage:link`.
5. `php artisan app:migrate` — backs up, then creates the tables.
6. Optional: `php artisan app:demo` for the sample clans.

### Rules that protect the records

- **Migrate only with `php artisan app:migrate`** (backup, then migrate). Plain `migrate`,
  and `migrate:fresh` / `refresh` / `reset` / `rollback`, `db:wipe` and `db:seed`, are
  refused against the registry database.
- Never write a migration that drops or rewrites `people` rows. Test a migration on a copy
  first: restore a recent backup into a `clan_scratch_…` database and migrate that.
- Seeders only ever go to `clan_demo` (`php artisan app:demo`) or `clan_test`.
- Everyday use runs with `APP_ENV=production`, `APP_DEBUG=false` and the built assets
  (`npm run build`), not the Vite dev server.

### Tests

```
php artisan test
```

The suite runs against the `clan_test` MySQL database (MySQL is required: recursive CTEs,
`<=>`, the accent-insensitive collation) and refuses to run against any other. It covers
the non-negotiables in CLAUDE.md, including the unscoped descendants query (Paolo's three
children under him in the Santos tree, two of them Menis members), dedup, sibling
renumbering, the hard stops, generation recompute, husband/wife columns, and a
backup → drop a clan → restore round trip.

### Where things are

| | |
|---|---|
| `app/Services/Descendants.php` | the one descendants query (tree, outline, print) |
| `app/Models/Scopes/ClanScope.php` | the membership scope, and why descent is outside it |
| `app/Services/SiblingOrder.php`, `Generations.php`, `PersonWriter.php` | ordering, numbering, the hard stops |
| `app/Services/Backup/BackupService.php` | backup and restore |
| `app/Support/DatabaseGuard.php` | refuses destructive commands against the registry |
| `resources/js/Pages/` | the screens (Inertia + React) |
| `resources/js/design-system/` | copied from `design-system/react/` |
| `prototype/clan-family-tree-demo.html` | the behaviour reference |
