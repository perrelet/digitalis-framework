// Root attributes, the tail task and its answer: docs/ADMIN.md, Logs Page.

const BOTTOM_SLACK_PX = 40;

class Logs {

    #root;
    #config;
    #labels;
    #els    = {};
    #state  = 'idle';
    #run    = null;
    #timer  = null;
    #unseen = 0;
    #size;
    #key;

    constructor (root) {

        this.#root   = root;
        this.#config = root.dataset;

        for (const el of root.querySelectorAll('[data-el]')) this.#els[el.dataset.el] = el;

        for (const name of ['url', 'action', 'nonce', 'log', 'size', 'key', 'bpp', 'plain', 'poll', 'labels']) if (!(name in this.#config)) throw new Error(`Lattice logs: data-${name} is missing on the root.`);
        for (const name of ['entries', 'partial', 'bottom', 'live', 'status', 'pill'])                           if (!this.#els[name])         throw new Error(`Lattice logs: the "${name}" element is missing in the root.`);

        this.#labels = JSON.parse(this.#config.labels);
        this.#size   = this.#config.size;
        this.#key    = this.#config.key;

        root.addEventListener('click', event => this.#click(event));
        root.addEventListener('submit', event => this.#submit(event));
        document.addEventListener('visibilitychange', () => this.#watchVisibility());
        window.addEventListener('scroll', () => this.#watchScroll(), { passive: true });

        this.#els.live.hidden = false;
        this.#scrollToBottom();

    }

    // Events

    #click (event) {

        const control = event.target.closest('[data-task], [data-action]');

        // The root matches too: its data-action is the ajax action.
        if (!control || control === this.#root || !this.#root.contains(control) || control.disabled) return;

        event.preventDefault();

        if (control.dataset.task === 'tail')     this.#refresh();
        if (control.dataset.action === 'live')   this.#setState(this.#state === 'idle' ? 'live' : 'idle');
        if (control.dataset.action === 'follow') this.#followNewLines();

    }

    #submit (event) {

        if (event.target !== this.#els.clear) return;
        if (!window.confirm(this.#labels.confirmClear)) event.preventDefault();

    }

    #watchVisibility () {

        if (document.hidden && this.#state === 'live')    this.#setState('paused');
        if (!document.hidden && this.#state === 'paused') this.#setState('live');

    }

    #watchScroll () {

        if (this.#unseen && this.#isAtBottom()) this.#forgetUnseen();

    }

    // Tasks

    #refresh () {

        if (this.#state === 'live') return;

        this.#run = new AbortController();
        this.#tail(this.#run);

    }

    async #poll (run) {

        await this.#tail(run);

        if (this.#run === run && this.#state === 'live') this.#timer = setTimeout(() => this.#poll(run), Number(this.#config.poll));

    }

    async #tail (run) {

        try {

            const answer = await this.#request('tail', { log: this.#config.log, size: this.#size, key: this.#key, bpp: this.#config.bpp, plain: this.#config.plain }, run.signal);

            if (this.#run === run) this.#paintTail(answer);

        } catch (error) {

            if (this.#run === run) this.#fail(error);

        }

    }

    #fail (error) {

        if (error.name === 'AbortError') return;

        this.#setState('idle');
        this.#els.status.textContent = error.message || this.#labels.fault;

    }

    // Requests

    async #request (task, data, signal) {

        const body = new FormData();

        for (const [key, value] of Object.entries({ action: this.#config.action, nonce: this.#config.nonce, task, ...data })) body.append(key, value);

        const response = await fetch(this.#config.url, { method: 'POST', body, credentials: 'same-origin', signal });
        const answer   = await response.json().catch(() => null);

        if (response.ok && answer) return answer;

        throw Object.assign(new Error(typeof answer?.data === 'string' ? answer.data : ''), { status: response.status });

    }

    // Painting

    #setState (state) {

        clearTimeout(this.#timer);
        this.#run?.abort();

        this.#state = state;
        this.#run   = (state === 'live') ? new AbortController() : null;

        for (const name of ['idle', 'live', 'paused']) this.#root.classList.toggle(`is-${name}`, name === state);

        this.#els.live.querySelector('[data-action="live"]').setAttribute('aria-pressed', String(state !== 'idle'));
        this.#els.status.textContent = this.#labels[state] ?? '';

        if (state === 'live') this.#poll(this.#run);

    }

    #paintTail (answer) {

        const follows = this.#isAtBottom();

        if (answer.reset) this.#els.entries.replaceChildren();

        this.#size = answer.size;
        this.#key  = answer.key;

        this.#els.entries.insertAdjacentHTML('beforeend', answer.html);
        this.#els.partial.replaceChildren();
        this.#els.partial.insertAdjacentHTML('beforeend', answer.partial);

        if (answer.reset) this.#forgetUnseen();
        if (answer.reset || follows) this.#els.bottom.scrollIntoView({ block: 'end' });
        if (!answer.count) return;

        this.#els.status.textContent = this.#countLines(answer.count);

        if (!follows) this.#paintPill(this.#unseen + answer.count);

    }

    #paintPill (unseen) {

        this.#unseen               = unseen;
        this.#els.pill.textContent = this.#countLines(unseen);
        this.#els.pill.hidden      = false;

    }

    #forgetUnseen () {

        this.#unseen          = 0;
        this.#els.pill.hidden = true;

    }

    #followNewLines () {

        this.#forgetUnseen();
        this.#els.bottom.scrollIntoView({ block: 'end' });

    }

    #countLines (count) {

        return (count === 1) ? this.#labels.newLine : this.#labels.newLines.replace('%d', count);

    }

    #isAtBottom () {

        return this.#els.bottom.getBoundingClientRect().top <= window.innerHeight + BOTTOM_SLACK_PX;

    }

    #scrollToBottom () {

        // A link to one entry (#b1024) outranks the newest lines.
        if (window.location.hash) return;

        this.#els.bottom.scrollIntoView({ block: 'end' });

    }

}

for (const root of document.querySelectorAll('[data-lattice="logs"]')) new Logs(root);
