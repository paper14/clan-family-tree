# Field

A labelled text input (or `multiline` textarea) with an optional hint and an error line; the building block of the person form.

- Consumer provides: `label`, value props, and `required` only on **Given name** — nothing else in the registry is required, so nothing else is marked.
- Set `clanLine` on the clan-line parent picker so it reads as the anchor (lineage label, 2px lineage border); the spouse/other parent stays a plain Field labelled "Spouse (optional)".
- `error` is for the two hard stops only: a blank given name and "can't be their own ancestor". Everything else — reused names, missing dates — is a `hint`, never red.
- Hints speak like the family would: "Leave blank if the family didn't name them."
