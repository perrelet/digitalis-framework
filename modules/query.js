export class Digitalis_Query {

    static default_options = {

        selectors: {
            archive:    '#archive',
            posts:      '#archive .posts',
            controls:   '#archive .archive-controls',
            form:       '#filters',
        },

        base_url:               window.location.origin,
        ajax_url:               '/wp-admin/admin-ajax.php',
        action:                 'digitalis_query',
        nonce:                  false,
        cache_initial_posts:    true,
        auto_submit:            true,
        auto_submit_break:      false,
        autoscroll:             true,
        scroll_offset:          0,

        page_query_param:       'paged',
        page_url_param:         'page',
        
    };

    elements = {};
    state = {};

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

        this.state.initial_posts = this.options.cache_initial_posts ? this.elements.posts.innerHTML : false;

    }

    find_elements () {

        this.elements.archive  = document.querySelector(this.options.selectors.archive);
        this.elements.posts    = document.querySelector(this.options.selectors.posts);
        this.elements.controls = document.querySelector(this.options.selectors.controls);
        this.elements.form     = document.querySelector(this.options.selectors.form);

        return this.elements.archive;

    }

    add_event_listeners () {

        let field_array = [...this.elements.form.elements];
        field_array.forEach(field => field.addEventListener('change', this.auto_submit));

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

                this.update_posts(event.state.html);

            } else if (this.state.initial_posts) {

                this.update_posts(this.state.initial_posts);

            }

            if (event.state && event.state.hasOwnProperty('js')) eval(event.state.js);

            //console.log(event.state.form);

            if (event.state && event.state.hasOwnProperty('form')) for (const field_name in event.state.form) {

                let field_selector = `${this.options.selectors.form} [name='${field_name}']:not([type='hidden'])`;
                let field = document.querySelector(field_selector);

                if (field) {

                    let value = event.state.form[field_name];

                    switch (field.type) {

                        case 'radio':
                            document.querySelectorAll(field_selector).forEach(el => { if (el.value == value) el.checked = true; });
                            break;

                        case 'checkbox':
                            field.checked = Boolean(parseInt(value));
                            break;

                        default:
                            field.value = value;

                    }

                    if (field.classList.contains('field-nice-select')) nice_selects[field.getAttribute('data-js-var')].update();

                }

            }

        });
        
    }

    setup_pagination () {

        this.elements.pages = document.querySelectorAll(`${this.options.selectors.archive} a.page-numbers`);
        this.elements.pages.forEach(page => page.addEventListener('click', this.on_page_change));

    }

    auto_submit = () => {

        let args = {
            auto_submit: this.options.auto_submit_break ? (this.options.auto_submit_break <= window.innerWidth) : this.options.auto_submit,
            query:       this,
        };

        document.dispatchEvent(new CustomEvent('Digitalis/Query/Auto_Submit', {detail: args}));

        if (args.auto_submit) this.submit();

    }

    submit = () => {

        this.request_posts({
            paged: 1,
        });

    }

    on_page_change = (event) => {

        event.preventDefault();

        let page = this.get_page_from_url(event.target.href);

        let data = {};
        if (page) data.paged = page;

        this.request_posts(data);

    }

    loading () {

        this.elements.archive.classList.add('loading');

    }

    loaded () {

        this.elements.archive.classList.remove('loading');

    }

    request_posts (data = {}, new_url = false) {

        if (this.options.autoscroll) {
            setTimeout(() => {
                window.scrollTo({
                    top: this.elements.posts.getBoundingClientRect().top + document.documentElement.scrollTop - this.options.scroll_offset,
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
            return [key, val.length > 1 ? val : val.pop()];
        }));
        
        data = Object.assign(entries, data);
        this.state.form = entries;

        if (this.elements.controls) {

            const controls_data = new FormData(this.elements.controls);
            data = Object.assign(Object.fromEntries(controls_data), data);

        }

        // console.log(data);

        if (!data.hasOwnProperty('paged') && url.searchParams.has('paged')) url.searchParams.delete('paged');

        let args = {action: this.options.action, data: data, url: url};
        document.dispatchEvent(new CustomEvent('Digitalis/Query/Request_Posts', {detail: args}));

        for (const [key, value] of Object.entries(data)) args.url.searchParams.set(key, value);

        this.request(this.options.action, args.data, args.url.href);

    }

    request (action, data, new_url = false, success_callback = 'success', error_callback = 'error') {

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

                this.loaded();

                let response = JSON.parse(http.responseText);

                document.dispatchEvent(new CustomEvent('Digitalis/Query/Response', {detail: {action: action, data: data, url: url, http: http, response: response, status: http.status}}));

                switch (http.status) {

                    case 200:

                        if (response.success) {

                            this[success_callback](response.data, http);

                            let state = response.data;
                            state.form = this.state.form;

                            if (new_url) window.history.pushState(state, '', new_url);

                            document.dispatchEvent(new CustomEvent('Digitalis/Query/Response/200', {detail: {action: action, data: data, url: url, http: http, response: response}}));

                        } else {

                            this[error_callback](response.data, http);

                        }

                        break;

                    default:

                        this[error_callback](response.data, http);

                }

            }

        }.bind(this);

    }

    success (data, http = null) {

        if (data.hasOwnProperty('html')) this.update_posts(data.html);
        if (data.hasOwnProperty('js'))   eval(data.js);

    }

    error (data, http = null) {

        if (http) {
            console.error(`${http.status}:`, data);
        } else {
            console.error(data);
        }

    }

    update_posts (html) {

        this.elements.posts.innerHTML = html;
        this.setup_pagination();

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

}