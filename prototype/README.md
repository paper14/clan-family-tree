# Prototype

`clan-family-tree-demo.html` is the whole app in one file, matching `planning.md`
**v0.18** in behaviour. Open it in Chrome or Edge and press **Reset demo** in the sidebar to load
the three sample clans: Santos, Dulnuan and Menis.

It is the **behaviour reference** for the build. Every screen, rule, message and print
option in the plan works here. Some good things to click through:

- the Santos Tree / Outline at 8 generations: Paolo and Annie's three children at
  Gen 7, and Liza's Dulnuan children under her;
- the Menis tree, where the same three children show at Gen 3;
- Liza's and Paolo's person pages, where the children are listed with their clan;
- Set founding couple: add a person, link Isko to them as parent, then set them as
  founder;
- printing a subclan sheet from Andres "Ando" Santos.

The prototype is not the architecture to copy:

| Prototype | Real app |
|---|---|
| One HTML file in a browser tab | Laravel + Inertia + React, served on `127.0.0.1`, opened in the browser |
| Data in localStorage, images in IndexedDB | MySQL 8 and `storage/app/public/photos` |
| Backup = one JSON file downloaded to Downloads | Backup = a timestamped zip of `mysqldump` plus photos, in a folder outside the project |
| Auto-backup before upgrading old saved data | Auto-backup before migrations, via `php artisan app:migrate` |
| Hand-rolled tree layout | `d3-hierarchy` for positioning, SVG for drawing |
| Browser print dialog with `@page` | The same: `@page` at the sheet size, Chrome/Edge "Save as PDF" |
| Google Fonts link | Self-hosted `@fontsource` packages (offline) |

The prototype also has a one-off data upgrade: for data saved before `sibling_order`
existed, it set the order from birth years, once. **The real app has no equivalent and
must never order siblings by date.**
