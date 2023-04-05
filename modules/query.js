export class Digitalis_Query {

    static default_options = {

        selectors: {
            archive:    '#archive',
            posts:      '#archive .posts',
            form:       '#filters',
        },

        base_url: window.location.origin,
        ajax_url: '/wp-admin/admin-ajax.php',
        action: 'digitalis_query',
        nonce: false,

        page_query_param: 'paged',
        page_url_param: 'page',
        
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

        this.find_elements();
        this.add_event_listeners();
        this.setup_pagination();

        this.state.original_html = this.elements.posts.innerHTML;

    }

    find_elements () {

        this.elements.archive = document.querySelector(this.options.selectors.archive);
        this.elements.posts = document.querySelector(this.options.selectors.posts);
        this.elements.form = document.querySelector(this.options.selectors.form);

    }

    add_event_listeners () {

        let field_array = [...this.elements.form.elements];
        field_array.forEach(field => field.addEventListener('change', this.on_filter_change));

        document.querySelectorAll(`${this.options.selectors.form} .datepicker-input`).forEach(field => field.addEventListener('changeDate', this.on_filter_change));

        window.addEventListener("popstate", (event) => {

            this.update_posts(event.state && event.state.hasOwnProperty('html') ? event.state.html : this.state.original_html);

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

        console.log();

    }

    on_page_change = (event) => {

        event.preventDefault();

        let page = this.get_page_from_url(event.target.href);

        this.request_posts({
            paged: page,
        });

    }

    loading () {

        this.elements.archive.classList.add('loading');

    }

    loaded () {

        this.elements.archive.classList.remove('loading');

    }

    request_posts (data = {}, new_url = false) {

        const form_data = new FormData(this.elements.form);

        data = Object.assign(Object.fromEntries(form_data), data);

        let url = new URL(new_url ? new_url : window.location.href);
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
                            if (new_url) window.history.pushState(response.data, '', new_url);

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

        this.update_posts(data.html);

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