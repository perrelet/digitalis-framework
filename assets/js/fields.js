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

            const current = this.read_value(scope, name);

            // A missing control leaves the row as it was.
            if (current === null) return;

            this.check_field(row, current, value, operator, bound);

        };

        const listener = (e) => { if (e.target && [name, name + '[]'].includes(e.target.name)) update(); };

        scope.addEventListener('input', listener);
        scope.addEventListener('change', listener);

        update();

    }

    read_value (scope, name) {

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

        // Unbound forms still post hidden fields.
        if (bound) row.querySelectorAll('input, select, textarea').forEach(input => {

            if (!active && !input.disabled) { input.disabled = true; input.dataset.fieldDisabled = 'true'; }
            if (active && input.dataset.fieldDisabled) { input.disabled = false; delete input.dataset.fieldDisabled; }

        });

    }

    // Same operators as Form_Rules::compare().
    compare (current, expected, operator = '=') {

        const values = (Array.isArray(current) ? current : [current]).map(String);

        switch (String(operator)) {

            case '=':
            case '==':  return values.includes(String(expected));
            case '!=':
            case '!==': return !values.includes(String(expected));
            case '<':   return this.any_numeric(values, v => v <  parseFloat(expected));
            case '<=':  return this.any_numeric(values, v => v <= parseFloat(expected));
            case '>':   return this.any_numeric(values, v => v >  parseFloat(expected));
            case '>=':  return this.any_numeric(values, v => v >= parseFloat(expected));
            default:    return false;

        }

    }

    any_numeric (values, test) {

        return values.some(v => (v !== '') && !isNaN(parseFloat(v)) && test(parseFloat(v)));

    }

}

new Digitalis_Fields();
