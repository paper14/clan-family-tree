# Data model — migration-ready schema

Four tables. This document is the authority for writing migrations. It resolves the
places where `planning.md` and `implementation-notes.md` disagreed (each is flagged
below). The database is **MySQL 8**, `utf8mb4` with collation `utf8mb4_0900_ai_ci`.

Types are given as Laravel schema-builder calls. Everything is `nullable()` unless
marked **required**.

---

## 0. Reconciliations — read these first

| # | Issue | Resolution |
|---|---|---|
| 1 | `implementation-notes.md` §1 requires storing which parent is the clan line (`clan_parent`). `planning.md` §2.3 has no such column. | **Added `people.clan_parent`** (`father` / `mother`). Working it out from sex every time isn't safe: the clan-line parent's sex may be unknown, and the answer would then flip when the sex is filled in later, silently moving a person's line. Store it. |
| 2 | `implementation-notes.md` §7 requires storing photo width and height for print layout. `planning.md` §2.11 has neither. | **Added `photos.width` and `photos.height`**, written at upload after resizing. |
| 3 | `planning.md` calls `generation` a "cached integer"; `implementation-notes.md` §2 requires NULL for people married in and people from other clans. | **`generation` is nullable.** NULL means "not on this clan's line", which is a real and common state, not missing data. |
| 4 | Names carry `ñ` and accents, and searches must still match them. | **Use the accent-insensitive collation `utf8mb4_0900_ai_ci`**, so `LIKE` ignores case and accents. `people.search_text` (below) is **optional**; add it only if a search needs more than the collation gives. |
| 5 | Earlier versions scoped the descendants query by `clan_id`. `planning.md` v0.17 §2.7 reverses this. | **`clan_id` is membership only.** The descendants query walks parent links with the global scope disabled, deduplicates by person id, and never walks upward. No schema change is needed; see §5. |

One thing to confirm with the owner rather than assume: **soft deletes on `people`.**
`planning.md` §2.12 soft-deletes `clans`, but says nothing about `people` in the
current version, although an earlier draft called for it. The data is hand-typed from
paper and a mis-click is unrecoverable, so `deleted_at` on `people` is included here.
If it's adopted, the sibling-ordering scope and every count must exclude trashed rows,
or a deleted child leaves a gap in `1…n`.

---

## 1. `clans`

| Column | Type | Notes |
|---|---|---|
| `id` | `id()` | |
| `name` | `string` **required** | |
| `founder_id` | `foreignId->nullable` → `people.id` | Gen 1. Movable (plan §2.4) |
| `founder_spouse_id` | `foreignId->nullable` → `people.id` | |
| `origin_place` | `string` | |
| `notes` | `text` | Oral history; superseded founders are appended here |
| `deleted_at` | `softDeletes` | |
| timestamps | | |

The two founder columns point into `people`, and `people.clan_id` points back. Create
both tables before adding the foreign keys, or add the `clans` founder keys in a second
migration.

---

## 2. `people`

### Identity and names

| Column | Type | Notes |
|---|---|---|
| `id` | `id()` | |
| `clan_id` | `foreignId` **required** → `clans.id` | **Membership.** Global scope key for membership lists only |
| `given_name` | `string` **required** | The only required name field |
| `middle_name` | `string` | |
| `last_name` | `string` | |
| `maiden_last_name` | `string` | |
| `suffix` | `string` | Jr., III |
| `nickname` | `string` | |
| `search_text` | `string`, indexed — **optional** | Only if the collation proves insufficient (reconciliation 4) |
| `sex` | `string(10)`, default `unknown` | `male` / `female` / `unknown` |

There's no unique constraint on any name. Reused given names across branches are
normal and expected; the sample data reuses "Andres" on purpose.

### Life facts

| Column | Type | Notes |
|---|---|---|
| `is_living` | `boolean` nullable | `true` / `false` / NULL = not known |
| `birth_date` | `date` | For reference only — **never used to order anyone** |
| `birth_date_text` | `string` | As written: "abt. 1892", "before the war" |
| `birth_place` | `string` | |
| `death_date` | `date` | |
| `death_date_text` | `string` | |
| `death_place` | `string` | |
| `burial_place` | `string` | |
| `occupation` | `string` | |
| `residence` | `string` | |
| `phone` | `string` | |
| `email` | `string` | |
| `notes` | `text` | Includes the source a researched ancestor came from |

### Descent

| Column | Type | Notes |
|---|---|---|
| `father_id` | `foreignId->nullable` → `people.id` | |
| `mother_id` | `foreignId->nullable` → `people.id` | |
| `clan_parent` | `string(6)` nullable | `father` / `mother` — which one is the clan line (reconciliation 1) |
| `father_relation` | `string(12)`, default `biological` | `biological` / `adopted` / `step` / `foster` / `unknown` |
| `mother_relation` | `string(12)`, default `biological` | Same set |
| `parentage_note` | `text` | The family's own words, quoted as written |
| `hide_second_parent` | `boolean`, default `false` | Both parents are clan members and one is not to be shown |
| `sibling_order` | `unsignedSmallInteger` **NOT NULL** | Dense 1…n within a `(father_id, mother_id)` pair, whatever each child's clan. Assigned on create |
| `generation` | `unsignedSmallInteger` nullable | Absolute from this person's own clan's founders. NULL = married in, or another clan's line (reconciliation 3). **Not** the number a chart shows (see §5) |

`sibling_order` has no default. It's computed on every create as the next free
position in the sibling set, so there is never an unordered row. People with no
parents get `1`, which is never displayed.

### Subclan

| Column | Type | Notes |
|---|---|---|
| `is_subclan_head` | `boolean`, default `false` | |
| `subclan_name` | `string` | What that subclan calls itself |
| `deleted_at` | `softDeletes` | See the note in §0 |
| timestamps | | |

### Indexes

```php
$table->index('clan_id');
$table->index('father_id');
$table->index('mother_id');
$table->index(['father_id', 'mother_id', 'sibling_order']); // the sibling-set query
$table->index(['clan_id', 'generation']);                   // people list sort, generation filter
$table->index(['clan_id', 'is_subclan_head']);              // start dropdown, subclans list
// search_text index only if that optional column is added
```

The composite `(father_id, mother_id, sibling_order)` index matters most: every
children listing, every renumber and every tree level goes through it.

---

## 3. `marriages`

| Column | Type | Notes |
|---|---|---|
| `id` | `id()` | |
| `husband_id` | `foreignId->nullable` → `people.id` | Either may be NULL: "spouse not recorded" |
| `wife_id` | `foreignId->nullable` → `people.id` | |
| `date` | `date` | |
| `date_text` | `string` | "before 1860" |
| `place` | `string` | |
| `status` | `string(12)`, default `married` | `married` / `separated` / `widowed` / `unknown` |
| `notes` | `text` | |
| timestamps | | |

Which column each spouse goes in is decided by sex at save time. See
`implementation-notes.md` §5 for the exact table, including the deliberate default when
neither sex is known. It is not worked out at read time.

**Marriage order** (needed to group a person's children by other parent) is `date`
ascending with NULLs last, then `id`. No extra column.

There's no `clan_id`: a marriage may legitimately span two clans, so it can't be scoped
to one.

Indexes: `husband_id`, `wife_id`.

---

## 4. `photos`

| Column | Type | Notes |
|---|---|---|
| `id` | `id()` | |
| `clan_id` | `foreignId` **required** → `clans.id` | Scoping |
| `person_id` | `foreignId->nullable` → `people.id` | Portrait; subclan head; or a family with no marriage |
| `marriage_id` | `foreignId->nullable` → `marriages.id` | Family picture |
| `kind` | `string(16)` **required** | `portrait` / `family` / `subclan_group` / `clan_group` |
| `file_path` | `string` **required** | Relative to the `public` disk's `photos/` folder |
| `caption` | `text` | Who is who, in row order |
| `year` | `unsignedSmallInteger` | Four digits or NULL |
| `is_primary` | `boolean`, default `false` | The one used where only one fits |
| `width` | `unsignedSmallInteger` | After resizing, for print layout (reconciliation 2) |
| `height` | `unsignedSmallInteger` | |
| timestamps | | |

Attachment by kind:

| kind | set | left NULL |
|---|---|---|
| `portrait` | `person_id` | `marriage_id` |
| `family` | `marriage_id`, or `person_id` where a solo parent has no marriage | the other |
| `subclan_group` | `person_id` (the head) | `marriage_id` |
| `clan_group` | neither — `clan_id` alone | both |

`kind` is what separates a portrait of Pedro from a group photo of Pedro's subclan:
both hang off the same `person_id`.

Order within a kind: `is_primary` first, then `year` with NULLs last, then `id`.

Implement these cascades in the application, not as database triggers: deleting a
person deletes their photos and the photos of their marriages; removing a marriage
deletes its family pictures and leaves the people.

Indexes: `(person_id, kind)`, `(marriage_id, kind)`, `(clan_id, kind)`.

---

## 5. Queries worth writing once

- **Descendants (plan §2.7, §5).** Write this once, as one recursive CTE, and use it
  for the tree, the outline and print.
  - **Parameters:** root, depth, and the collapsed-branch set.
  - **It recurses on `father_id` / `mother_id` and never filters on `clan_id`.**
    Call it with the global clan scope removed (`withoutGlobalScope(...)`, or raw SQL
    that doesn't go through the scoped model). A CTE that inherits the scope silently
    draws a smaller tree.
  - **Never upward.** Spouses are joined onto the rows returned; their ancestors are
    not.
  - **Hidden link:** exclude a child from the hidden parent's branch when
    `hide_second_parent` applies.
  - **Deduplicate by person id**, keeping the minimum depth. A child of two members of
    the same clan has two paths; draw it once, on the shorter one.
  - **Order:** by depth, then by other parent in marriage order, then `sibling_order`.
  - **Chart generation** = the root's stored `generation` + depth (clan numbering), or
    depth alone (relative numbering, where the root is Gen 0).
  - **Counts** are the number of rows returned below each node at the current root and
    depth: "people in this chart", not clan members.
- **Ancestor walk** — for the cycle check, before any parent save. Walks both parent
  chains.
- **A person's children** — `WHERE father_id = ? OR mother_id = ?`, **unscoped**,
  grouped by other parent, ordered by `sibling_order`.
- **Sibling set** — `WHERE father_id <=> ? AND mother_id <=> ?`. In MySQL that's the
  NULL-safe `<=>` rather than `=`, so NULL parents match NULL. Getting this wrong makes
  every one-parent family its own sibling set, or none.
- **Stored-generation recompute** (Set founding couple, restore) — breadth-first from
  the founder and spouse along **clan-line** links only (`clan_parent`), within one
  clan. This is membership numbering, and deliberately different from the unscoped
  chart walk.
- **People list default sort** — "generation, then family order" is depth-first tree
  order from the founders, with siblings in `sibling_order`, among the clan's members.
  It is not `ORDER BY generation`.

---

## 6. What must not be in the schema

Each of these was considered and rejected:

- No `photo_path` on `people` — superseded by the `photos` table.
- No date-precision enum — the nullable date plus the as-written text covers it.
- No stored d'Aboville reference numbers — they can be worked out from `sibling_order`
  at any time, and they change whenever the root moves or a forgotten sibling is
  inserted.
- No `settings` table — clan-level values live on the `clans` row; app preferences
  in `.env` or a small settings file, not in the registry database.
- No unique constraint on names, and no MySQL `ENUM` columns. Use strings validated in
  the application, because changing an `ENUM`'s values later needs a table rewrite.
- No clan column on a descendant relationship, and no "visible in clan" flag. Who
  appears in a tree is decided by parent links alone.
