# PersonRow

A line in the people list: generation numeral, name, "child of" the clan-line parent, dates, and flags.

- Consumer provides: `name`, `generation` (null for unplaced), `parent`, `dates`, `unplaced`, `living`, `onOpen`.
- `unplaced` shows the Unplaced badge — the transcription to-do list. It is a to-do, not an error: warn tone, never danger.
- The printed indented outline uses the same content, indented by `space-5` per generation, one line per person.
