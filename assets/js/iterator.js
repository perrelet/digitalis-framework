(function(params) {

    let iterator = {

        params: null,
        total: null,
        time: 0,
        last_time: null,
        delta: null,
        els: {},
        flags: {
            stop: false,
        },
        retrying: false,
        token: null,

        init: function (params) {

            this.params = params;
            this.params.index = parseInt(this.params.index);
            this.time = Math.round(parseFloat(this.params.elapsed || 0) * 1000);

            this.els.iterator = document.querySelector(".digitalis-iterator");
            this.els.start = document.querySelector(".iterator button[data-task='start']");
            this.els.stop = document.querySelector(".iterator button[data-task='stop']");
            this.els.reset = document.querySelector(".iterator button[data-task='reset']");
            this.els.retry = document.querySelector(".iterator button[data-task='retry']");
            this.els.dismiss = document.querySelector(".iterator button[data-task='dismiss']");
            this.els.once = document.querySelector(".iterator button[data-task='once']");
            this.els.background = document.querySelector(".iterator button[data-task='background']");
            this.els.options = document.querySelector(".iterator .run-options");
            this.els.batch_size = document.querySelector(".iterator .run-options [data-param='batch_size']");
            this.els.dry_run = document.querySelector(".iterator .run-options [data-param='dry_run']");
            this.els.failed = document.querySelector(".iterator .failed-wrap");
            this.els.failed_count = document.querySelector(".iterator .failed-count");
            this.els.failed_list = document.querySelector(".iterator .failed-list");
            this.els.index = document.querySelector(".iterator .index");
            this.els.total = document.querySelector(".iterator .total");
            this.els.percent = document.querySelector(".iterator .percent");
            this.els.time = document.querySelector(".iterator .time");
            this.els.eta = document.querySelector(".iterator .eta");
            this.els.counts = {
                processed: document.querySelector(".iterator .count-processed"),
                skipped:   document.querySelector(".iterator .count-skipped"),
                failed:    document.querySelector(".iterator .count-failed"),
            };
            this.els.progress = document.querySelector(".iterator .progress-bar");
            this.els.track = document.querySelector(".iterator .progress-track");
            this.els.status = document.querySelector(".iterator .status");
            this.els.log = document.querySelector(".iterator .iterator-log");

            this.add_event_listeners();

        },

        add_event_listeners: function () {

            if (this.els.start) this.els.start.addEventListener('click', this.start.bind(this));
            if (this.els.stop) this.els.stop.addEventListener('click', this.maybe_stop.bind(this));
            if (this.els.reset) this.els.reset.addEventListener('click', this.maybe_reset.bind(this));
            if (this.els.retry) this.els.retry.addEventListener('click', this.maybe_retry.bind(this));
            if (this.els.dismiss) this.els.dismiss.addEventListener('click', this.maybe_dismiss.bind(this));
            if (this.els.once) this.els.once.addEventListener('click', this.start.bind(this, true));
            if (this.els.background) this.els.background.addEventListener('click', this.background.bind(this));

        },

        // The running class and the run options move together; every chain ending clears the one-batch flag.
        set_running: function (on) {

            this.els.iterator.classList.toggle('running', on);
            if (this.els.options) this.els.options.disabled = on;
            if (!on) this.flags.once = false;

        },

        // A stale chain's next iterate is refused by the server, so the stop flag is cleared here rather than left for it.
        start: function (once) {

            this.flags.once = once === true;
            this.flags.stop = false;
            this.last_time = new Date().getTime();
            this.els.status.textContent = 'Starting';
            this.batch_log(`Starting batch at index ${this.params.index}.`);
            this.request('total', this.start_total.bind(this));
            this.set_running(true);
            this.els.iterator.classList.remove('err', 'is-stopped', 'is-halted', 'is-stalled', 'is-complete');

        },

        // The total answer opens the run: the token drives every iterate until the chain ends.
        start_total: function (response) {

            this.token = response.token;

            if (this.set_total(response.total)) this.request('iterate', this.iterate.bind(this)); else this.release();

        },

        // A chain that ends with the run stopped hands the lock back, after the last response has landed: the server then knows the
        // run stopped, a reload says so, and the next Start is not refused. A finished or halted run was released by the server.
        release: function () {

            if (!this.token) return;

            this.request('stop', function () {});
            this.token = null;

        },

        set_total: function (total) {

            this.apply_total(total);

            if (total === null) {

                this.els.status.textContent = 'Running';
                return true;

            }

            if (total) {

                if (this.params.index < total) { 

                    this.els.status.textContent = 'Running';
                    return true;

                } else {

                    this.els.status.textContent = 'Already Complete!';
                    this.els.iterator.classList.add('is-complete');
                    this.set_running(false);
                    return false;

                }

            } else {

                this.els.status.textContent = `No ${this.params.labels.plural}`;
                this.batch_log(`Batch could not run as there are no ${this.params.labels.plural} to process.`);
                this.set_running(false);
                return false;

            }

        },

        // A null total is unknown: the bar runs indeterminate until the server completes the run.
        apply_total: function (total) {

            this.total = total;
            this.els.total.textContent = total === null ? '?' : total;
            this.els.iterator.classList.toggle('is-indeterminate', total === null);
            this.update_aria(this.params.index);

        },

        iterate: function (response) {

            this.apply_total(response.total);

            this.update_progress(response.index);
            this.params.index = response.index;
            this.update_counts(response.counts);
            this.render_failed(response.failed);
            this.update_eta(response.status == 'running' && !response.stalled ? response.index : null);

            if (response.hasOwnProperty('log') && response.log) for (const line of response.log) this.log(line);
            if (response.hasOwnProperty('errors') && response.errors) for (const line of response.errors) this.error(line, false);

            if (this.params.print_results) {

                if (response.results.processed.length)  for (const id of response.results.processed)    this.batch_log(` - OK: #${id}`);
                if (response.results.skipped.length)    for (const id of response.results.skipped)      this.batch_log(` - Skipped: #${id}`);
                if (response.results.failed.length)     for (const id of response.results.failed)       this.error(` - Failed: #${id}`, false, true);

            }

            this.batch_log(`${response.count} ${this.params.labels.plural} processed (${response.results.skipped.length} skipped, ${response.results.failed.length} failed) in ${this.delta}ms:`);

            if (response.status == 'complete') {

                this.els.status.textContent = 'Finished!';
                this.batch_log(`Batch completed in: ${this.time}ms.`);
                this.els.iterator.classList.add('is-complete');
                this.set_running(false);
                this.flags.stop = false; // a Stop that landed during this batch has nothing left to stop
                this.token = null;

            } else if (response.status == 'halted') {

                this.els.status.textContent = 'Halted';
                this.update_eta(null);
                this.batch_log(`Batch halted after ${this.time}ms - press Start to resume.`);
                this.els.iterator.classList.add('err', 'is-halted');
                this.set_running(false);
                this.flags.stop = false;
                this.token = null;

            } else if (response.stalled) {

                this.els.status.textContent = 'Stalled';
                this.update_eta(null);
                this.batch_log(`Batch stalled after ${this.time}ms - press Start to retry.`);
                this.els.iterator.classList.add('is-stalled');
                this.set_running(false);
                this.flags.stop = false;
                this.release();

            } else if (this.flags.stop) {

                this.els.status.textContent = 'Stopped';
                this.update_eta(null);
                this.batch_log(`Batch stopped after ${this.time}ms.`);
                this.els.iterator.classList.add('is-stopped');
                this.flags.stop = false;
                this.release();

            } else if (this.flags.once) {

                this.els.status.textContent = 'Stopped';
                this.update_eta(null);
                this.batch_log(`One batch run - press Start to continue.`);
                this.els.iterator.classList.add('is-stopped');
                this.set_running(false);
                this.release();

            } else {

                this.request('iterate', this.iterate.bind(this));

            }

        },

        maybe_stop: function () {

            if (this.params.doing_cron) {

                if (confirm("Stop the background run?")) {

                    this.els.status.textContent = 'Stopping background run';
                    this.batch_log(`Stopping the background run.`);
                    this.request('stop_cron', this.stop_cron_complete.bind(this));

                }

            } else {

                this.stop();

            }

        },

        // Hands the run to a background job; the page then reads the job from the server, so it reloads.
        background: function () {

            this.els.status.textContent = 'Handing over';
            this.batch_log(`Handing the run to the background.`);
            this.request('background', function () { window.location.reload(); });

        },

        // `cancelled`: the job had not started. `requested`: it is mid-batch and ends before its next one; the page reads that on a reload.
        stop_cron_complete: function (response) {

            const outcome = response && response.cancelled;

            this.els.status.textContent = outcome === 'requested' ? 'Background run stopping' : (outcome ? 'Background run stopped' : 'No background run');
            this.batch_log(outcome === 'requested' ? `Background run stopping: it ends before its next batch.` : (outcome ? `Background run stopped.` : `No background run to stop.`));
            this.params.doing_cron = false;
            this.set_running(false);

        },

        stop: function () {

            if (this.els.iterator.classList.contains('running')) {

                this.els.status.textContent = 'Stopping';
                this.batch_log(`Stopping batch.`);
                this.flags.stop = true;
                this.set_running(false);

            }

        },

        // A retry runs its own chain: no total step, no "Already Complete" gate, the index and the run's status untouched.
        maybe_retry: function () {

            if (!this.els.iterator.classList.contains('running')) this.retry();

        },

        retry: function () {

            this.retrying = true;
            this.last_time = new Date().getTime();
            this.els.status.textContent = 'Retrying';
            this.batch_log(`Retrying the failed ${this.params.labels.plural}.`);
            this.set_running(true);
            this.request('retry', this.retry_start.bind(this));

        },

        retry_start: function (response) {

            this.token = response.token;
            this.render_failed(response.failed);

            if (response.retry_remaining) this.request('iterate', this.retry_tick.bind(this)); else this.retry_end(null);

        },

        retry_tick: function (response) {

            this.update_progress(response.index);
            this.update_counts(response.counts);
            this.render_failed(response.failed);

            if (response.hasOwnProperty('log') && response.log) for (const line of response.log) this.log(line);
            if (response.hasOwnProperty('errors') && response.errors) for (const line of response.errors) this.error(line, false);

            this.batch_log(`${response.count} ${this.params.labels.plural} retried (${response.results.failed.length} failed again) in ${this.delta}ms.`);

            if (response.retry_remaining && !this.flags.stop) this.request('iterate', this.retry_tick.bind(this)); else this.retry_end(response);

        },

        retry_end: function (response) {

            const labels = { complete: 'Finished!', halted: 'Halted' };

            this.retrying = false;
            this.flags.stop = false;
            this.set_running(false);
            this.els.status.textContent = response && labels[response.status] ? labels[response.status] : 'Retried';
            this.batch_log(`Retry finished after ${this.time}ms.`);
            this.release();

        },

        maybe_dismiss: function () {

            if (confirm(`Dismiss the failed ${this.params.labels.plural}? They stay out of the run.`)) this.request('dismiss', this.dismiss_complete.bind(this));

        },

        dismiss_complete: function (response) {

            this.render_failed(response.failed);
            this.update_counts(response.counts);
            this.batch_log(`Failed ${this.params.labels.plural} dismissed.`);

        },

        render_failed: function (failed) {

            if (!this.els.failed || !failed) return;

            this.els.failed.classList.toggle('is-empty', !failed.count);
            this.els.failed_count.textContent = failed.count;
            this.els.failed_list.textContent = '';

            for (const item of failed.items) {

                const li = document.createElement('li');

                if (item.url) {

                    const a = document.createElement('a');
                    a.href = item.url;
                    a.textContent = `#${item.id}`;
                    li.appendChild(a);

                } else {

                    li.textContent = `#${item.id}`;

                }

                this.els.failed_list.appendChild(li);

            }

            if (failed.count > failed.items.length) {

                const li = document.createElement('li');
                li.classList.add('failed-more');
                li.textContent = `and ${failed.count - failed.items.length} more`;
                this.els.failed_list.appendChild(li);

            }

        },

        maybe_reset: function () {

            if (confirm("Are you sure the want to reset this batch process?")) this.reset();

        },

        reset: function () {

            this.stop(); // the token goes with the request: the server releases the lock with the record, and refuses without it
            this.els.status.textContent = 'Resetting';
            this.batch_log(`Resetting batch process...`);
            this.request('reset', this.reset_complete.bind(this));

        },

        reset_complete: function (response) {

            if (response.hasOwnProperty(`reload`)) window.location.reload();

            this.params.index = 0;
            this.time = 0;

            this.apply_total(null);
            this.update_progress(response.index);

            this.els.iterator.classList.remove('err', 'is-stopped', 'is-halted', 'is-stalled', 'is-complete');

            this.token = null;
            this.els.status.textContent = 'Reset';
            this.els.time.textContent = '00:00:00';
            this.update_counts({ processed: 0, skipped: 0, failed: 0 });
            this.render_failed({ count: 0, items: [] });
            this.update_eta(null);

            this.els.log.innerHTML = '';

        },

        request: function (task, callback) {

            if (task == 'iterate') this.batch_log(this.retrying ? `Requesting a retry batch.` : `Requesting batch process at ${this.params.labels.single} ${this.params.index} of ${this.total === null ? '?' : this.total}.`);

            var http = new XMLHttpRequest();

            http.onreadystatechange = function() {

                if (http.readyState == XMLHttpRequest.DONE) {

                    let error = null;

                    switch (http.status) {

                        case 200:

                            callback(JSON.parse(http.responseText));
                            return;

                        case 400:

                            this.error(`AJAX Error: ${http.status} - Bad request trying to reach ${http.responseURL}`, true, true);
                            return;

                        case 401:

                            this.error(`AJAX Error: ${http.status} - ${http.responseText}`, true, true);
                            return;

                        case 409:

                            this.refused(JSON.parse(http.responseText));
                            return;

                        default:

                            this.error(`AJAX Error: ${http.status} - ${http.responseText}`, true, true);
                            return;

                    }

                }

            }.bind(this);

            let url = `${this.params.ajax_url}?action=iterator_${this.params.key}&task=${task}&nonce=${this.params.nonce}`;

            if (this.token) url += `&token=${encodeURIComponent(this.token)}`;

            if (task == 'iterate') {

                if (this.els.batch_size && this.els.batch_size.value !== '') url += `&batch_size=${encodeURIComponent(this.els.batch_size.value)}`;
                if (this.els.dry_run) url += `&dry_run=${this.els.dry_run.checked ? 1 : 0}`;

            }
            http.open('GET', url, true);
            http.send();

        },

        update_progress: function (index) {

            let time = new Date().getTime();
            if (this.last_time !== null) {
                this.delta = time - this.last_time;
                this.time += this.delta;
            }
            this.last_time = time;
            let secs = Math.floor(this.time / 1000);
            let mins = Math.floor(secs / 60);
            let hours = Math.floor(mins / 60);
            this.els.time.textContent = String(hours).padStart(2, 0) + ":" + String(mins % 60).padStart(2, 0) + ":" + String(secs % 60).padStart(2, 0);

            this.els.index.textContent = index;
            this.update_aria(index);

            if (this.total === null) {

                this.els.percent.textContent = '';
                this.els.progress.style.width = '';
                return;

            }

            let percent = (index && this.total) ? Math.min(100, 100 * index / this.total) : 0;
            this.els.percent.textContent = Math.floor(percent) + "%";
            this.els.progress.style.width = percent + "%";

        },

        update_counts: function (counts) {

            if (!counts) return;

            for (const key of ['processed', 'skipped', 'failed']) {

                const el = this.els.counts[key];
                if (!el) continue;
                el.querySelector('b').textContent = counts[key];
                if (key == 'failed') el.classList.toggle('is-nonzero', counts[key] > 0);

            }

        },

        // The pace is the page's own wall clock across the run, so the estimate counts the network the user is waiting on.
        update_eta: function (index) {

            if (!this.els.eta) return;

            if (index === null || this.total === null || index <= 0 || index >= this.total || this.time <= 0) {

                this.els.eta.textContent = '';
                return;

            }

            const s = Math.round((this.total - index) * (this.time / 1000) / index);
            const about = s < 60 ? `${s}s` : (s < 3600 ? `${Math.floor(s / 60)}m` : `${Math.floor(s / 3600)}h ${Math.floor(s / 60) % 60}m`);

            this.els.eta.textContent = `about ${about} left`;

        },

        update_aria: function (index) {

            if (this.total === null) {

                this.els.track.removeAttribute('aria-valuenow');
                this.els.track.removeAttribute('aria-valuemax');
                this.els.track.setAttribute('aria-valuetext', `${index} of unknown`);

            } else {

                this.els.track.removeAttribute('aria-valuetext');
                this.els.track.setAttribute('aria-valuenow', index);
                this.els.track.setAttribute('aria-valuemax', this.total);

            }

        },

        log: function (msg, type = 'item', text = false) {

            let item = document.createElement("div");
            item.classList.add('log-' + type);
            if (text) item.textContent = msg; else item.innerHTML = msg;

            this.els.log.prepend(item);

        },

        batch_log: function (msg) {

            this.log(msg, 'batch', true);

        },

        // The server refused this chain: another driver holds the run, or none does. The chain ends with the message; no halt, no stop flag.
        refused: function (response) {

            this.token = null;
            this.retrying = false;
            this.flags.stop = false;
            this.set_running(false);
            this.els.status.textContent = response.run_by ? 'Locked' : 'Stopped';
            this.error(response.message, false, true);

        },

        error: function (error, halt = false, text = false) {

            if (halt) {

                this.stop();
                this.flags.stop = false;
                this.retrying = false;
                this.els.iterator.classList.add('err');
                this.release();

            }


            this.log(error, 'error', text);

        },

    }

    iterator.init(params);

})(iterator_params);