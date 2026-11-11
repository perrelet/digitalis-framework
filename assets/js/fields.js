class Digitalis_Fields {

    constructor () {

        this.scan(document);

        document.addEventListener('htmx:load', (e) => this.scan(e.detail.elt));

    }

    scan (root) {

        if (!root || !root.querySelectorAll) return;

        const fields = [...(root.matches && root.matches('[data-field-condition]') ? [root] : []), ...root.querySelectorAll('[data-field-condition]')];

        fields.forEach(field => {

            if (field.dataset.fieldBound) return;
            field.dataset.fieldBound = 'true';

            const condition = JSON.parse(field.getAttribute('data-field-condition'));
            if (!condition || !condition.length) return;

            this.set_condition(field, condition);

        });

    }

    set_condition (field, condition) {

        const [name, operator, value] = condition;

        const scope = field.form || field.closest('form') || document;
        const row   = document.getElementById(field.getAttribute('data-field-id') + '-row') || field;
        const bound = !!(row.closest && row.closest('form[data-lattice-form]'));

        const update = () => {

            const current = this.read(scope, name);

            // No field by that name: leave the row as it is, as before.
            if (current === null) return;

            this.check_field(row, current, value, operator, bound);

        };

        const listener = (e) => { if (e.target && [name, name + '[]'].includes(e.target.name)) update(); };

        scope.addEventListener('input', listener);
        scope.addEventListener('change', listener);

        update();

    }

    // The value a submit would send: ticked boxes only, a list when there are several.
    read (scope, name) {

        const elements = [...scope.querySelectorAll(`[name="${name}"], [name="${name}[]"]`)];
        if (!elements.length) return null;

        const values = [];

        elements.forEach(el => {

            if (el.type === 'hidden' && elements.length > 1) return;
            if ((el.type === 'checkbox' || el.type === 'radio') && !el.checked) return;

            if (el.multiple && el.options) [...el.selectedOptions].forEach(o => values.push(o.value));
            else values.push(el.value);

        });

        return values.length > 1 ? values : (values.length ? values[0] : '');

    }

    check_field (row, current, expected, operator, bound) {

        const active = this.compare(current, expected, operator);

        if (active) row.removeAttribute('data-field-inactive');
        else        row.setAttribute('data-field-inactive', 'true');

        // Only a form bound to a route stops hidden fields posting; other forms post them as they always have.
        if (bound) row.querySelectorAll('input, select, textarea').forEach(input => {

            if (!active && !input.disabled) { input.disabled = true; input.dataset.fieldDisabled = 'true'; }
            if (active && input.dataset.fieldDisabled) { input.disabled = false; delete input.dataset.fieldDisabled; }

        });

    }

    // The same operators the server judges a rule's `when` with.
    compare (current, expected, operator = '=') {

        const values = (Array.isArray(current) ? current : [current]).map(String);

        switch (String(operator)) {

            case '=':
            case '==':  return values.includes(String(expected));
            case '!=':
            case '!==': return !values.includes(String(expected));
            case '<':   return this.numeric(values, v => v <  parseFloat(expected));
            case '<=':  return this.numeric(values, v => v <= parseFloat(expected));
            case '>':   return this.numeric(values, v => v >  parseFloat(expected));
            case '>=':  return this.numeric(values, v => v >= parseFloat(expected));
            default:    return false;

        }

    }

    numeric (values, test) {

        return values.some(v => (v !== '') && !isNaN(parseFloat(v)) && test(parseFloat(v)));

    }

}

new Digitalis_Fields();
