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

        this.elements.archive = document.querySelector(this.options.selectors.archive);
        this.elements.posts = document.querySelector(this.options.selectors.posts);
        this.elements.controls = document.querySelector(this.options.selectors.controls);
        this.elements.form = document.querySelector(this.options.selectors.form);

        return this.elements.archive;

    }

    add_event_listeners () {

        let field_array = [...this.elements.form.elements];
        field_array.forEach(field => field.addEventListener('change', this.on_filter_change));

        document.querySelectorAll(`${this.options.selectors.form} .datepicker-input`).forEach(field => field.addEventListener('changeDate', this.on_filter_change));

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

    on_filter_change = (event) => {

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

        const form_data = new FormData(this.elements.form);
        const entries = Object.fromEntries(Array.from(form_data.keys(), key => {
            const val = form_data.getAll(key)
            return [ key, val.length > 1 ? val : val.pop() ]
        }))
        data = Object.assign(entries, data);
        //data = Object.assign(Object.fromEntries(form_data), data);
        this.state.form = Object.fromEntries(form_data);

        if (this.elements.controls) {

            const controls_data = new FormData(this.elements.controls);
            data = Object.assign(Object.fromEntries(controls_data), data);

        }

        // console.log(data);

        let url = new URL(new_url ? new_url : window.location.href);
        if (!data.hasOwnProperty('paged') && url.searchParams.has('paged')) url.searchParams.delete('paged');
        for (const [key, value] of Object.entries(data)) url.searchParams.set(key, value);

        this.request(this.options.action, data, url.href);
        this.loading();

    }

    request (action, data, new_url = false, success_callback = 'success', error_callback = 'error') {

        let url = new URL(this.options.ajax_url, this.options.base_url);
        url.searchParams.set('action', action);
        if (this.options.nonce) url.searchParams.set('nonce', this.options.nonce);
        
        const http = new XMLHttpRequest();
        http.open('POST', url.href, true);
        http.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
        http.send(new URLSearchParams(data).toString());
        
        http.onreadystatechange = function() {

            if (http.readyState == XMLHttpRequest.DONE) {

                this.loaded();

                let response = JSON.parse(http.responseText);

                switch (http.status) {

                    case 200:

                        if (response.success) {

                            this[success_callback](response.data, http);

                            let state = response.data;
                            state.form = this.state.form;

                            if (new_url) window.history.pushState(state, '', new_url);

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