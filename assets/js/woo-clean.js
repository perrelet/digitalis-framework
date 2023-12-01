
class Woo_Clean {

    state = {
        account_nav: 1,
    };

    constructor () {

        document.addEventListener('DOMContentLoaded', this.load.bind(this));

    }

    load () {

        const getCookieValue = (name) => (document.cookie.match('(^|;)\\s*' + name + '\\s*=\\s*([^;]+)')?.pop() || '');

        let value = getCookieValue("woo_clean_ui");
        if (value) this.state = Object.assign({}, this.state, JSON.parse(value));

    }

    save () {

        let account_nav = document.querySelector('.woocommerce-MyAccount-navigation');

        let state = {};
        if (account_nav) state.account_nav = !account_nav.classList.contains(`collapse`);

        state = Object.assign({}, this.state, state);

        const expiration_days = 30;
        const date = new Date();
        date.setTime(date.getTime() + (expiration_days * 24 * 60 * 60 * 1000));

        document.cookie = 'woo_clean_ui=' + JSON.stringify(state) + ';expires=' + date.toUTCString() + '; domain=.' + window.location.host.toString() + ';path=/';

    }

}

const woo_clean = new Woo_Clean();
