class Digitalis_Fields {

    constructor () {

        document.querySelectorAll(`[data-field-condition]`).forEach(field => {

            const condition = JSON.parse(field.getAttribute('data-field-condition'));
            if (!condition || !condition.length) return;

            this.set_condition(field, condition);

        });

    }

    set_condition (field, condition) {

        const id         = field.getAttribute('data-field-id') + '-row';
        const row        = document.getElementById(id);

        const delta_name = condition[0];
        const operator   = condition[1];
        const value      = condition[2];

        const deltas = document.querySelectorAll(`[name='${delta_name}']`);
        const form   = document.createElement("form");

        if (deltas) {

            deltas.forEach(change_field => {

                form.appendChild(change_field.cloneNode(true));

                const on_event = change_field.classList.contains(`field-nice-select`) ? `change` : `input`;
                
                change_field.addEventListener(on_event, function (e) {
                    
                    this.check_field(row, change_field.value, value, operator);
                
                }.bind(this));
            
            });

            const form_data = new FormData(form);
            const entries = Object.fromEntries(Array.from(form_data.keys(), key => {
                let val = form_data.getAll(key)
                if (val.length > 1) val = val.filter(v => v !== '0'); // remove dummy checkbox values
                return [key, val.length > 1 ? val : val.pop()]
            }));

            const v1 = entries.hasOwnProperty(deltas[0].name) ? entries[deltas[0].name] : null;
            if (v1 != null) this.check_field(row, v1, value, operator);

        }

    }

    check_field (el, v1, v2, operator) {

        if (this.compare(v1, v2, operator)) {

            el.removeAttribute('data-field-inactive');

        } else {

            el.setAttribute('data-field-inactive', 'true');

        }

    }

    compare (v1, v2, operator = '=') {

        operator = operator.toUpperCase();

        switch (operator) {

            case '=':    return v1 == v2;
            case '!=':   return v1 != v2;
            case '==':   return v1 === v2;
            case '!==':  return v1 !== v2;
            case '<':    return parseFloat(v1) < parseFloat(v2);
            case '<=':   return parseFloat(v1) <= parseFloat(v2);
            case '>':    return parseFloat(v1) > parseFloat(v2);
            case '>=':   return parseFloat(v1) >= parseFloat(v2);
            //case 'IN':   return is_array(v2) ? in_array(v1, v2) : v1 == v2;
            //case '!IN':  return !this.compare(v1, v2, 'IN');
            //case 'IN=':  return is_array(v2) ? in_array(v1, v2, true) : v1 == v2;
            //case '!IN=': return !this.compare(v1, v2, 'IN=');
            default:     return false;

        }

    }

}
new Digitalis_Fields();