(function() {

    class Woo_Clean {

        state = {
            account_nav: 1,
        };

        constructor () {

            document.addEventListener('DOMContentLoaded', this.load_ui_options.bind(this));
            document.addEventListener('DOMContentLoaded', this.add_event_listeners.bind(this));

        }

        add_event_listeners () {

            document.querySelectorAll(`.woocommerce-MyAccount-navigation .nav-controls .control`).forEach(
                control => control.addEventListener('click', this.on_nav_control.bind(this))
            );

        }

        on_nav_control (e) {

            switch (e.target.getAttribute('data-action')) {

                case 'collapse-menu':
                    this.nav_collapse();
                    break;

                case 'expand-menu':
                    this.nav_expand();
                    break;

            }

            this.save_ui_options();

        }

        nav_collapse () {

            document.querySelector('.woocommerce-MyAccount-navigation').classList.add('collapse');
            this.state.account_nav = false;

        }

        nav_expand () {

            document.querySelector('.woocommerce-MyAccount-navigation').classList.remove('collapse');
            this.state.account_nav = true;


        }

        load_ui_options () {

            const getCookieValue = (name) => (
                document.cookie.match('(^|;)\\s*' + name + '\\s*=\\s*([^;]+)')?.pop() || ''
            )

            let value = getCookieValue("woo_clean_ui");

            if (value) this.state = JSON.parse(value);

            this.parse_state();


        }

        parse_state () {

            //this.state.account_nav ? this.nav_expand() : this.nav_collapse();

        }

        save_ui_options () {

            const expiration_days = 100;
            const date = new Date();
            date.setTime(date.getTime() + (expiration_days * 24 * 60 * 60 * 1000));

            document.cookie = 'woo_clean_ui=' + JSON.stringify(this.state) + ';expires=' + date.toUTCString() + '; domain=.' + window.location.host.toString() + ';path=/';

        }

    }

    new Woo_Clean();

})();