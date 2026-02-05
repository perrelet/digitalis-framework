export class Digitalis_Query {

    static default_options = {

        selectors: {
            archive:    '#archive',
            items:      '#archive .items',
            controls:   '#archive .archive-controls',
            form:       '#filters',
        },

        base_url:               window.location.origin,
        ajax_url:               '/wp-admin/admin-ajax.php',
        action:                 'digitalis_query',
        nonce:                  false,
        run_js:                 false,
        cache_initial_items:    true,
        auto_submit:            true,
        auto_submit_break:      false,
        auto_submit_controls:   true,
        autoscroll:             true,
        scroll_offset:          0,
        dynamic_filters:        false,
        dynamic_controls:       false,
        page_query_param:       'paged',
        page_url_param:         'page',
    };

    elements = {};
    state = {
        requests:  0,
        responses: 0,
    };

    constructor (options) {

        this.options = Object.assign({}, Digitalis_Query.default_options, options || {});
        this.options.selectors = Object.assign({}, Digitalis_Query.default_options.selectors, options.selectors || {});
        this.init();

        // console.log(this.options); 

    }

    init () {

        if (!this.find_elements()) return;
        this.add_event_listeners();
        this.setup_pagination();

        this.state.initial_items = this.options.cache_initial_items ? this.elements.items.innerHTML : false;

    }

    find_elements () {

        this.elements.archive  = document.querySelector(this.options.selectors.archive);
        this.elements.items    = document.querySelector(this.options.selectors.items);
        this.elements.controls = document.querySelector(this.options.selectors.controls);
        this.elements.form     = document.querySelector(this.options.selectors.form);

        return this.elements.archive;

    }

    add_event_listeners () {

        this.add_control_event_listeners();

        [...this.elements.form.elements].forEach(field => field.addEventListener('change', this.auto_submit));

        document.querySelectorAll(`${this.options.selectors.form} .datepicker-input`).forEach(field => field.addEventListener('changeDate', this.auto_submit));

        this.elements.form.addEventListener('submit', (e) => {

            e.preventDefault();
            this.submit();

        });

        document.addEventListener('Digitalis/Query/Control/Submit', (e) => {

            this.submit();

        });

        window.addEventListener("popstate", (event) => {

            if (event.state && event.state.hasOwnProperty('html')) {

                this.update_items(event.state.html);

            } else if (this.state.initial_items) {

                this.update_items(this.state.initial_items);

            }

            if (this.options.run_js && event.state && event.state.hasOwnProperty('js')) eval(event.state.js);

            //console.log(event.state.form);

            if (event.state && event.state.hasOwnProperty('form')) for (const field_name in event.state.form) {

                let field_selector = `${this.options.selectors.form} [name='${field_name}']:not([type='hidden'])`;
                let fields = document.querySelectorAll(field_selector);

                fields.forEach(field => {

                    let value = event.state.form[field_name];

                    switch (field.type) {

                        case 'radio':
                            document.querySelectorAll(field_selector).forEach(el => { if (el.value == value) el.checked = true; });
                            break;

                        case 'checkbox':
                            if (!Array.isArray(value)) value = [value];
                            const checked = value.includes(field.value);
                            if (field.checked != checked) field.checked = checked;
                            break;

                        default:
                            field.value = value;

                    }

                    if (field.classList.contains('field-nice-select')) nice_selects[field.getAttribute('data-js-var')].update();

                });

            }

        });
        
    }

    add_control_event_listeners () {

        if (this.elements.controls) [...this.elements.controls.elements].forEach(field => field.addEventListener('change', this.auto_submit));

    }

    setup_pagination () {

        this.elements.pages = document.querySelectorAll(`${this.options.selectors.archive} a.page-numbers`);
        this.elements.pages.forEach(page => page.addEventListener('click', this.on_page_change));

    }

    auto_submit = (e) => {

        const target     = e.target
        const is_control = this.elements.controls && [...this.elements.controls.elements].includes(target);

        let auto_submit       = is_control ? this.options.auto_submit_controls : this.options.auto_submit;
        let auto_submit_break = this.options.auto_submit_break;

        if (target.hasAttribute(`data-auto-submit`))       auto_submit       = (e.target.getAttribute(`data-auto-submit`) == 'true');
        if (target.hasAttribute(`data-auto-submit-break`)) auto_submit_break = intVal(target.getAttribute(`data-auto-submit-break`));

        if ((auto_submit_break > 0) && (window.innerWidth <= auto_submit_break)) auto_submit = false;

        let args = {
            auto_submit: auto_submit,
            is_control:  is_control,
            query:       this,
            event:       e,
        };

        document.dispatchEvent(new CustomEvent(`Digitalis/Query/Auto_Submit`, {detail: args}));

        if (args.auto_submit) this.submit();

    }

    submit = () => {

        this.request_items({
            paged: 1,
        });

    }

    on_page_change = (event) => {

        event.preventDefault();

        let page = this.get_page_from_url(event.target.href);

        let data = {};
        if (page) data.paged = page;

        this.request_items(data);

    }

    loading () {

        this.elements.archive.classList.add('loading');

    }

    loaded () {

        this.elements.archive.classList.remove('loading');

    }

    request_items (data = {}, new_url = false) {

        if (this.options.autoscroll) {
            setTimeout(() => {
                window.scrollTo({
                    top: this.elements.items.getBoundingClientRect().top + document.documentElement.scrollTop - this.options.scroll_offset,
                    behavior: 'smooth',
                });
            }, 250); 
        }

        let url = new URL(new_url ? new_url : window.location.href);

        const form_data = new FormData(this.elements.form);
        const filtered = Array.from(form_data.keys()).filter(key => {
            url.searchParams.delete(key);
            const field = this.elements.form.querySelector(`[name='${key}']`);
            return !field.closest(`[data-field-inactive]`);
        });

        const entries = Object.fromEntries(Array.from(filtered, key => {
            let val = form_data.getAll(key)
            if (val.length > 1) val = val.filter(v => v !== '0'); // remove dummy checkbox values
            return [key.replace(`[]`, ``), val.length > 1 ? val : val.pop()];
        }));
        
        data = Object.assign(entries, data);
        this.state.form = entries;

        if (this.elements.controls) {

            const controls_data = new FormData(this.elements.controls);
            data = Object.assign(Object.fromEntries(controls_data), data);

        }

        if (!data.hasOwnProperty('paged') && url.searchParams.has('paged')) url.searchParams.delete('paged');

        let args = {action: this.options.action, data: data, url: url};
        document.dispatchEvent(new CustomEvent('Digitalis/Query/Request_Items', {detail: args}));

        for (const [key, value] of Object.entries(data)) args.url.searchParams.set(key, value);

        this.request(this.options.action, args.data, args.url.href);

    }

    request (action, data, new_url = false, success_callback = 'success', error_callback = 'error') {

        this.state.requests++;
        this.loading();

        let url = new URL(this.options.ajax_url, this.options.base_url);
        url.searchParams.set('action', action);
        if (this.options.nonce) url.searchParams.set('nonce', this.options.nonce);

        const http = new XMLHttpRequest();

        let args = {action: action, data: data, url: url, http: http};
        document.dispatchEvent(new CustomEvent('Digitalis/Query/Request', {detail: args}));

        args.http.open('POST', args.url.href, true);
        args.http.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
        args.http.send(new URLSearchParams(args.data).toString());

        http.onreadystatechange = function() {

            if (http.readyState == XMLHttpRequest.DONE) {

                this.state.responses++;
                if (this.state.responses >= this.state.requests) this.loaded();

                let response;

                try {

                    response = JSON.parse(http.responseText);

                } catch (e) {

                    response = {
                        success: true,
                        data: {
                            html: http.responseText,
                        }
                    } // follows wp_send_json_success

                }

                document.dispatchEvent(new CustomEvent('Digitalis/Query/Response', {detail: {action: action, data: data, url: url, http: http, response: response, status: http.status}}));

                switch (http.status) {

                    case 200:

                        if (response.hasOwnProperty('success') && response.success) {

                            this[success_callback](response.data, http);

                            let state = response.data;
                            state.form = this.state.form;

                            if (new_url) window.history.pushState(state, '', new_url);

                        } else {

                            this[error_callback](response, http);

                        }

                        break;

                    default:

                        this[error_callback](response, http);

                }

                document.dispatchEvent(new CustomEvent(`Digitalis/Query/Response/${http.status}`, {detail: {action: action, data: data, url: url, http: http, response: response}}));

            }

        }.bind(this);

    }

    success (data, http = null) {

        if (this.options.run_js && data.hasOwnProperty('js')) eval(data.js);
        if (data.hasOwnProperty('html')) this.update_items(data.html);

    }

    error (response, http = null) {

        if (http) {
            console.error(`${http.status}:`, response);
        } else {
            console.error(response);
        }

    }

    update_items (html) {

        this.run_inner_html(this.elements.items, html);

        document.dispatchEvent(new CustomEvent('Digitalis/Query/Update_Items', {detail: {items: this.elements.items}}));
        if (htmx) htmx.process(this.elements.items);

        this.find_elements();
        this.setup_pagination();

        if (this.elements.items.querySelector(this.options.selectors.controls)) this.add_control_event_listeners();

    }

    // utils

    get_page_from_url (url) {

        url = new URL(url);

        let page = url.searchParams.get(this.options.page_query_param);
        if (page) return page;

        let url_parts = url.pathname.split('/');

        for (let i = 0; i < (url_parts.length - 1); i++) if (url_parts[i] == this.options.page_url_param) return url_parts[i + 1];

        return false;

    }

    run_inner_html (element, html) {

        element.innerHTML = html;

        Array.from(element.querySelectorAll("script")).forEach(html_script => {

            const script = document.createElement("script");
            
            Array.from(html_script.attributes).forEach(attr => {
                script.setAttribute(attr.name, attr.value) 
            });
            
            const scriptText = document.createTextNode(html_script.innerHTML);
            script.appendChild(scriptText);
            
            html_script.parentNode.replaceChild(script, html_script);

        });

    }

}