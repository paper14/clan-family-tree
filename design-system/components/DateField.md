# DateField

The date pattern from the schema: an exact date (display and reference only — never used for ordering) plus what the record actually says, plus the place — one fieldset for Born, Died or Married.

- Consumer provides: `label` and the three value/change pairs (`date`, `text`, `place`); `placeLabel={false}` drops the place.
- The text field is the truth; the date is only for sorting. Either may be blank. Never reject "abt. 1892" or "before the war".
- Display elsewhere prefers the text: `b. abt. 1892`, falling back to the formatted date.
