// Root attributes and markup: docs/ADMIN.md, Logs Page.

class Logs {

    #root;
    #labels;
    #els = {};

    constructor (root) {

        this.#root = root;

        for (const el of root.querySelectorAll('[data-el]')) this.#els[el.dataset.el] = el;

        for (const name of ['log', 'labels']) if (!(name in root.dataset)) throw new Error(`Lattice logs: data-${name} is missing on the root.`);
        for (const name of ['bottom'])        if (!this.#els[name])        throw new Error(`Lattice logs: the "${name}" element is missing in the root.`);

        this.#labels = JSON.parse(root.dataset.labels);

        root.addEventListener('submit', event => this.#submit(event));

        this.#scrollToBottom();

    }

    #submit (event) {

        if (event.target !== this.#els.clear) return;
        if (!window.confirm(this.#labels.confirmClear)) event.preventDefault();

    }

    #scrollToBottom () {

        // A link to one entry (#b1024) outranks the newest lines.
        if (window.location.hash) return;

        this.#els.bottom.scrollIntoView({ block: 'end' });

    }

}

for (const root of document.querySelectorAll('[data-lattice="logs"]')) new Logs(root);
