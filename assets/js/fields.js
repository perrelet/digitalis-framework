class Digitalis_Fields {

    constructor () {

        let fields = {};

        document.querySelectorAll(`[data-field-condition]`).forEach(field => {

            const condition = JSON.parse(field.getAttribute('data-field-condition'));

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
                    
                    change_field.addEventListener(`input`, function (e) {
                        
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

        });

    }

    check_field (el, v1, v2, operator) {

        el.style.display = this.compare(v1, v2, operator) ? `flex` : `none`

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