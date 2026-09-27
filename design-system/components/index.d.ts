import type * as React from 'react';

export interface ButtonProps extends React.ButtonHTMLAttributes<HTMLButtonElement> {
  /** quiet (default) · primary (lineage, once per view) · danger (destructive) · link */
  variant?: 'quiet' | 'primary' | 'danger' | 'link';
}
export declare function Button(props: ButtonProps): React.ReactElement;

export interface FieldProps extends React.InputHTMLAttributes<HTMLInputElement> {
  label: React.ReactNode;
  /** Shown under the input in ink-muted. */
  hint?: React.ReactNode;
  /** Hard error; prefixed "Can't save —". Only for the ancestor-cycle rule and a blank given name. */
  error?: React.ReactNode;
  /** Marks the clan-line parent field: lineage label and 2px lineage border. */
  clanLine?: boolean;
  /** Renders a textarea (notes, parentage note). */
  multiline?: boolean;
}
export declare function Field(props: FieldProps): React.ReactElement;

export interface DateFieldProps {
  /** "Born", "Died", "Married". */
  label: React.ReactNode;
  id?: string;
  /** ISO date for sorting (the nullable DATE column). */
  date?: string; defaultDate?: string; onDateChange?: React.ChangeEventHandler<HTMLInputElement>;
  /** What the source actually says: "abt. 1892", "before the war". */
  text?: string; defaultText?: string; onTextChange?: React.ChangeEventHandler<HTMLInputElement>;
  place?: string; defaultPlace?: string; onPlaceChange?: React.ChangeEventHandler<HTMLInputElement>;
  /** Label for the place input, or false to omit it. */
  placeLabel?: string | false;
}
export declare function DateField(props: DateFieldProps): React.ReactElement;

export interface BadgeProps {
  tone?: 'neutral' | 'lineage' | 'gilt' | 'living' | 'warn' | 'danger';
  children?: React.ReactNode;
}
export declare function Badge(props: BadgeProps): React.ReactElement;

export interface PersonNodeProps {
  /** Display name, already joined: given middle last suffix. Only the given name is guaranteed. */
  name: string;
  nickname?: string;
  /** Pre-formatted: "b. abt. 1892 · d. 1960". */
  dates?: string;
  generation?: number;
  living?: boolean;
  /** Hide dates for a living person (print default). */
  redactLiving?: boolean;
  photo?: string;
  showPhoto?: boolean;
  /** true (default): clan-line frame. false: a spouse who married in. */
  clanLine?: boolean;
  founder?: boolean;
  selected?: boolean;
  dense?: boolean;
  /** Makes the node a button that opens the person. */
  onOpen?: React.MouseEventHandler<HTMLButtonElement>;
}
export declare function PersonNode(props: PersonNodeProps): React.ReactElement;

export interface CoupleNodeProps {
  /** The clan-line person. */
  person: PersonNodeProps;
  /** Optional — blank is a normal, valid state and renders a single box. */
  spouse?: PersonNodeProps | null;
  /** Marriage date text shown under the "=" join. */
  marriage?: string;
  /** The founding couple: both framed as clan, gilt ground. */
  founders?: boolean;
  redactLiving?: boolean;
  dense?: boolean;
}
export declare function CoupleNode(props: CoupleNodeProps): React.ReactElement;

export interface PersonRowProps {
  name: string;
  nickname?: string;
  dates?: string;
  generation?: number | null;
  /** Clan-line parent's name: "child of Andres Santos". */
  parent?: string;
  /** No parent linked yet — shows the Unplaced badge. */
  unplaced?: boolean;
  living?: boolean;
  redactLiving?: boolean;
  selected?: boolean;
  onOpen?: React.MouseEventHandler<HTMLButtonElement>;
}
export declare function PersonRow(props: PersonRowProps): React.ReactElement;

declare global {
  interface Window {
    Clan: { Button: typeof Button; Field: typeof Field; DateField: typeof DateField; Badge: typeof Badge; PersonNode: typeof PersonNode; CoupleNode: typeof CoupleNode; PersonRow: typeof PersonRow };
  }
}
