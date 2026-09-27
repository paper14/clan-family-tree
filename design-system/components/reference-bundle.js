/* @ds-bundle: {"format":4,"namespace":"Clan","components":[{"name":"Button"},{"name":"Field"},{"name":"DateField"},{"name":"Badge"},{"name":"PersonNode"},{"name":"CoupleNode"},{"name":"PersonRow"}]} */
(function () {
  var React = window.React, h = React.createElement;
  function cx() { return Array.prototype.filter.call(arguments, Boolean).join(' '); }
  var uid = 0;
  function useId(given) { var r = React.useRef(null); if (r.current === null) r.current = given || ('cl-f' + (++uid)); return r.current; }

  function Button(p) {
    var variant = p.variant || 'quiet', rest = Object.assign({}, p);
    delete rest.variant; delete rest.className;
    return h('button', Object.assign({ type: 'button' }, rest, { className: cx('cl-btn', 'cl-btn-' + variant, p.className) }), p.children);
  }

  function Field(p) {
    var id = useId(p.id), rest = Object.assign({}, p);
    ['label', 'hint', 'error', 'clanLine', 'id', 'className', 'multiline'].forEach(function (k) { delete rest[k]; });
    var hintId = p.hint || p.error ? id + '-h' : undefined;
    var control = h(p.multiline ? 'textarea' : 'input', Object.assign({ id: id, 'aria-describedby': hintId, 'aria-invalid': p.error ? true : undefined }, rest, { className: 'cl-input' }));
    return h('div', { className: cx('cl-field', p.clanLine && 'cl-field-clan', p.error && 'cl-field-error', p.className) },
      h('label', { htmlFor: id, className: 'cl-label' }, p.label, p.required ? h('span', { className: 'cl-req' }, 'required') : null),
      control,
      p.error ? h('div', { id: hintId, className: 'cl-hint cl-hint-error', role: 'alert' }, p.error)
        : p.hint ? h('div', { id: hintId, className: 'cl-hint' }, p.hint) : null);
  }

  function DateField(p) {
    var id = useId(p.id);
    return h('fieldset', { className: cx('cl-field', 'cl-datefield', p.className) },
      h('legend', { className: 'cl-label' }, p.label),
      h('div', { className: 'cl-date-row' },
        h('div', { className: 'cl-date-part' },
          h('label', { htmlFor: id + '-d', className: 'cl-sub' }, 'Date for sorting'),
          h('input', { id: id + '-d', type: 'date', className: 'cl-input', value: p.date, defaultValue: p.defaultDate, onChange: p.onDateChange })),
        h('div', { className: 'cl-date-part cl-date-text' },
          h('label', { htmlFor: id + '-t', className: 'cl-sub' }, 'As written in the record'),
          h('input', { id: id + '-t', type: 'text', className: 'cl-input', placeholder: 'abt. 1892', value: p.text, defaultValue: p.defaultText, onChange: p.onTextChange }))),
      p.placeLabel !== false ? h('div', { className: 'cl-date-part' },
        h('label', { htmlFor: id + '-p', className: 'cl-sub' }, p.placeLabel || 'Place'),
        h('input', { id: id + '-p', type: 'text', className: 'cl-input', value: p.place, defaultValue: p.defaultPlace, onChange: p.onPlaceChange })) : null);
  }

  function Badge(p) {
    return h('span', { className: cx('cl-badge', 'cl-badge-' + (p.tone || 'neutral'), p.className) }, p.children);
  }

  function initials(name) {
    return String(name || '').split(/\s+/).filter(Boolean).slice(0, 2).map(function (s) { return s.charAt(0); }).join('').toUpperCase();
  }

  function nodeBody(p) {
    var hideDates = p.living && p.redactLiving;
    return [
      p.showPhoto === false ? null : h('span', { key: 'ph', className: 'cl-photo', 'aria-hidden': true },
        p.photo ? h('img', { src: p.photo, alt: '' }) : initials(p.name)),
      h('span', { key: 'tx', className: 'cl-node-text' },
        p.generation != null ? h('span', { className: 'cl-gen' }, 'Gen ' + p.generation) : null,
        h('span', { className: 'cl-node-name' }, p.name, p.nickname ? h('span', { className: 'cl-nick' }, ' “' + p.nickname + '”') : null),
        !hideDates && p.dates ? h('span', { className: 'cl-node-dates' }, p.dates) : null,
        p.living ? h(Badge, { tone: 'living' }, 'Living') : null)
    ];
  }

  function PersonNode(p) {
    var cls = cx('cl-node', p.clanLine === false ? 'cl-node-spouse' : 'cl-node-clan', p.founder && 'cl-node-founder', p.selected && 'cl-node-selected', p.dense && 'cl-node-dense', p.className);
    if (p.onOpen) return h('button', { type: 'button', className: cls, onClick: p.onOpen, 'aria-current': p.selected ? 'true' : undefined }, nodeBody(p));
    return h('div', { className: cls }, nodeBody(p));
  }

  function CoupleNode(p) {
    var person = Object.assign({ clanLine: true }, p.person, { redactLiving: p.redactLiving, dense: p.dense, founder: p.founders });
    if (!p.spouse) return h('div', { className: 'cl-couple cl-couple-single' }, h(PersonNode, person));
    var spouse = Object.assign({ clanLine: !!p.founders }, p.spouse, { redactLiving: p.redactLiving, dense: p.dense, founder: p.founders });
    return h('div', { className: cx('cl-couple', p.founders && 'cl-couple-founders') },
      h(PersonNode, person),
      h('span', { className: 'cl-join', title: p.marriage || 'Married' }, h('span', { 'aria-hidden': true }, '='), p.marriage ? h('span', { className: 'cl-join-date' }, p.marriage) : h('span', { className: 'cl-sr' }, 'married')),
      h(PersonNode, spouse));
  }

  function PersonRow(p) {
    var hideDates = p.living && p.redactLiving;
    return h('button', { type: 'button', className: cx('cl-row', p.selected && 'cl-row-selected', p.className), onClick: p.onOpen },
      h('span', { className: 'cl-row-gen' }, p.generation != null ? p.generation : '–'),
      h('span', { className: 'cl-row-main' },
        h('span', { className: 'cl-row-name' }, p.name, p.nickname ? h('span', { className: 'cl-nick' }, ' “' + p.nickname + '”') : null),
        p.parent ? h('span', { className: 'cl-row-parent' }, 'child of ' + p.parent) : null),
      h('span', { className: 'cl-row-dates' }, hideDates ? '' : (p.dates || '')),
      h('span', { className: 'cl-row-flags' },
        p.unplaced ? h(Badge, { tone: 'warn' }, 'Unplaced') : null,
        p.living ? h(Badge, { tone: 'living' }, 'Living') : null));
  }

  var C = window.Clan || (window.Clan = {});
  Object.assign(C, { Button: Button, Field: Field, DateField: DateField, Badge: Badge, PersonNode: PersonNode, CoupleNode: CoupleNode, PersonRow: PersonRow });
})();
