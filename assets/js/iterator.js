// Root attributes, elements, states, tasks and their answers: docs/ADMIN.md, Iterator Screen.

class Iterator {

    #root;
    #config;
    #labels;
    #busy;
    #els   = {};
    #state;
    #token = null;
    #chain = null; // the chain this page drives: null, 'start' or 'retry'

    constructor (root) {

        this.#root   = root;
        this.#config = root.dataset;

        for (const el of root.querySelectorAll('[data-el]')) this.#els[el.dataset.el] = el;

        for (const name of ['url', 'action', 'nonce', 'state', 'busy', 'labels']) if (!(name in this.#config)) throw new Error(`Lattice iterator: data-${name} is missing on the root.`);
        for (const name of ['options', 'batch_size', 'dry_run', 'index', 'total', 'percent', 'time', 'eta', 'track', 'bar', 'status', 'message', 'count-processed', 'count-skipped', 'count-failed', 'failed', 'failed-count', 'failed-list', 'log']) if (!this.#els[name]) throw new Error(`Lattice iterator: the "${name}" element is missing in the root.`);

        this.#labels = JSON.parse(this.#config.labels);
        this.#busy   = this.#config.busy.split(' ');
        this.#state  = this.#config.state;

        root.addEventListener('click', event => this.#click(event));

        for (const button of root.querySelectorAll('button[data-task]')) button.disabled = false;

    }

    // Events

    #click (event) {

        const button = event.target.closest('button[data-task]');

        if (!button || !this.#root.contains(button) || button.disabled || this.#state === 'working') return;

        event.preventDefault();

        const task = button.dataset.task;

        if (task === 'stop') return this.#stop();
        if (this.#chain)     return;

        if (task === 'start')      this.#start({ once: false });
        if (task === 'once')       this.#start({ once: true });
        if (task === 'retry')      this.#retry();
        if (task === 'background') this.#handOver();
        if (task === 'reset')      this.#reset();
        if (task === 'dismiss')    this.#dismiss();

    }

    // Tasks

    async #start ({ once }) {

        this.#chain = 'start';

        try {

            this.#setState('starting');

            const answer = await this.#request('total');

            this.#token = answer.token;
            this.#paintProgress(answer);

            if (answer.nothing) await this.#release({ label: this.#labels[answer.nothing === 'empty' ? 'noItems' : 'alreadyComplete'] });
            else                await this.#drive({ once });

        } catch (error) {

            await this.#fail(error);

        } finally {

            this.#chain = null;

        }

    }

    // One batch per request until the server names another state, the run stalls or Stop is pressed; a batch in flight still lands.
    async #drive ({ once }) {

        let answer = null;

        while (this.#state !== 'stopping') {

            this.#setState('running');

            answer = await this.#request('iterate', this.#readOptions());

            this.#paintBatch(answer);

            if (answer.stalled || answer.state !== 'running' || once) break;

        }

        // An answer that names no live lock was released by the server: the run completed or halted.
        if (answer && answer.state !== 'running') {

            this.#token = null;
            this.#setState(answer.state, { stalled: answer.stalled });

        } else {

            await this.#release();

        }

    }

    async #retry () {

        this.#chain = 'retry';

        try {

            this.#setState('retrying');

            let answer = await this.#request('retry');

            this.#token = answer.token;
            this.#paintFailed(answer);
            this.#paintCounts(answer.counts);

            while (answer.retry_remaining && this.#state !== 'stopping') {

                answer = await this.#request('iterate', this.#readOptions());

                this.#paintBatch(answer);

            }

            await this.#release();

        } catch (error) {

            await this.#fail(error);

        } finally {

            this.#chain = null;

        }

    }

    async #stop () {

        if (this.#chain) {

            if (this.#state !== 'stopping') this.#setState('stopping');

            return;

        }

        if (!this.#busy.includes(this.#state)) return;

        // Another page's run, or a chain this page lost to a reload: the server knows where it stands.
        if (this.#state === 'running') return this.#reload();

        if (window.confirm(this.#labels.confirmStopBackground)) await this.#leave('stop_cron');

    }

    async #reset () {

        if (window.confirm(this.#labels.confirmReset)) await this.#leave('reset');

    }

    async #handOver () {

        await this.#leave('background');

    }

    // The tasks whose answer the server has already painted: the page loads again.
    async #leave (task) {

        try {

            this.#setState('working');

            await this.#request(task);

            this.#reload();

        } catch (error) {

            await this.#fail(error);

        }

    }

    async #dismiss () {

        if (!window.confirm(this.#labels.confirmDismiss)) return;

        try {

            this.#setState('working');

            const answer = await this.#request('dismiss');

            this.#paintFailed(answer);
            this.#paintCounts(answer.counts);
            this.#setState(answer.state, { stalled: answer.stalled });

        } catch (error) {

            await this.#fail(error);

        }

    }

    #readOptions () {

        const options = { dry_run: this.#els.dry_run.checked ? '1' : '0' }; // the server clears a stored dry run on a literal 0 only

        if (this.#els.batch_size.value) options.batch_size = this.#els.batch_size.value;

        return options;

    }

    // Requests

    // Every refusal ends its task on this screen, so a 409 throws with the rest and #fail() reads it.
    async #request (task, data = {}) {

        const body = new FormData();

        for (const [key, value] of Object.entries({ action: this.#config.action, nonce: this.#config.nonce, task, ...(this.#token ? { token: this.#token } : {}), ...data })) body.append(key, value);

        const response = await fetch(this.#config.url, { method: 'POST', body, credentials: 'same-origin' });
        const answer   = await response.json().catch(() => null);

        if (response.ok && answer) return answer;

        throw Object.assign(new Error(typeof answer?.data === 'string' ? answer.data : ''), { status: response.status, answer });

    }

    async #fail (error) {

        // A refusal: the run is another driver's, whose stall flag is its own until its next batch. The token is dead, and a
        // kept one would have the next Reset refused.
        if (error.status === 409 && error.answer?.state) {

            this.#token = null;
            this.#setState(error.answer.state);
            this.#els.message.textContent = error.answer.message ?? '';

            return;

        }

        if (this.#token) await this.#dropLock();

        this.#setState('fault');
        this.#els.message.textContent = error.message || this.#labels.faultMessage;

    }

    // Hands the lock back, after the last batch has landed. Answers where the run rests, or null when that request failed too.
    async #dropLock () {

        const token = this.#token;

        this.#token = null;

        try {

            return await this.#request('stop', token ? { token } : {});

        } catch {

            return null;

        }

    }

    async #release ({ label } = {}) {

        const answer = await this.#dropLock();

        if (answer) return this.#setState(answer.state, { stalled: answer.stalled, label });

        this.#setState('fault');
        this.#els.message.textContent = this.#labels.faultMessage;

    }

    // A GET of this page: a reload would repeat the upload a CSV controller was the response to, and a URL that differs by its
    // fragment alone loads nothing.
    #reload () {

        const url = new URL(window.location.href);

        url.hash = '';

        window.location.replace(url.href);

    }

    // Painting

    #setState (state, { stalled = false, label } = {}) {

        if (!(state in this.#labels)) throw new Error(`Lattice iterator: the state "${state}" has no label.`);

        const busy = this.#busy.includes(state);

        this.#root.classList.remove(`is-${this.#state}`);
        this.#root.classList.add(`is-${state}`);
        this.#root.classList.toggle('is-busy', busy);
        this.#root.classList.toggle('is-stalled', Boolean(stalled));
        this.#root.setAttribute('aria-busy', String(busy));

        this.#state = this.#config.state = state;

        this.#els.status.textContent  = label ?? (stalled ? this.#labels.stalled : this.#labels[state]);
        this.#els.options.disabled    = busy;
        this.#els.message.textContent = '';

        if (!busy) this.#els.eta.textContent = '';

    }

    #paintBatch (answer) {

        this.#paintProgress(answer);
        this.#paintCounts(answer.counts);
        this.#paintFailed(answer);
        this.#paintLog(answer);
        this.#paintClock(answer);

    }

    // A null total is unknown: the bar runs indeterminate until the server completes the run.
    #paintProgress ({ index, total }) {

        const known    = Number.isInteger(total);
        const progress = (known && total > 0) ? Math.min(1, index / total) : 0;

        this.#els.index.textContent   = index;
        this.#els.total.textContent   = known ? total : this.#labels.unknownTotal;
        this.#els.percent.textContent = known ? `${Math.floor(100 * progress)}%` : '';
        this.#els.bar.style.width     = known ? `${100 * progress}%` : '';

        this.#root.classList.toggle('is-indeterminate', !known);

        if (known) {

            this.#els.track.removeAttribute('aria-valuetext');
            this.#els.track.setAttribute('aria-valuenow', index);
            this.#els.track.setAttribute('aria-valuemax', total);

        } else {

            this.#els.track.removeAttribute('aria-valuenow');
            this.#els.track.removeAttribute('aria-valuemax');
            this.#els.track.setAttribute('aria-valuetext', this.#labels.unknownProgress.replace('%d', index));

        }

    }

    #paintCounts (counts) {

        for (const name of ['processed', 'skipped', 'failed']) this.#els[`count-${name}`].textContent = counts[name];

        this.#els['count-failed'].parentElement.classList.toggle('is-nonzero', counts.failed > 0);

    }

    #paintFailed ({ failed, failed_html }) {

        this.#els['failed-list'].replaceChildren();
        this.#els['failed-list'].insertAdjacentHTML('beforeend', failed_html);
        this.#els['failed-count'].textContent = failed.count;
        this.#els.failed.classList.toggle('is-empty', !failed.count);

    }

    #paintLog ({ log_html }) {

        this.#els.log.insertAdjacentHTML('afterbegin', log_html);

    }

    #paintClock ({ time, eta }) {

        this.#els.time.textContent = time;
        this.#els.eta.textContent  = eta;

    }

}

for (const root of document.querySelectorAll('[data-lattice="iterator"]')) new Iterator(root);
