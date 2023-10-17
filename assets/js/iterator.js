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

        init: function (params) {

            this.params = params;
            this.params.index = parseInt(this.params.index);

            //console.log("hello");
            //console.log(this.params);

            this.els.iterator = document.querySelector(".digitalis-iterator");
            this.els.start = document.querySelector(".iterator button[data-task='start']");
            this.els.stop = document.querySelector(".iterator button[data-task='stop']");
            this.els.reset = document.querySelector(".iterator button[data-task='reset']");
            this.els.index = document.querySelector(".iterator .index");
            this.els.total = document.querySelector(".iterator .total");
            this.els.percent = document.querySelector(".iterator .percent");
            this.els.time = document.querySelector(".iterator .time");
            this.els.progress = document.querySelector(".iterator .progress-bar");
            this.els.status = document.querySelector(".iterator .status");
            this.els.log = document.querySelector(".iterator .iterator-log");

            this.add_event_listeners();

        },

        add_event_listeners: function () {

            if (this.els.start) this.els.start.addEventListener('click', this.start.bind(this));
            if (this.els.stop) this.els.stop.addEventListener('click', this.maybe_stop.bind(this));
            if (this.els.reset) this.els.reset.addEventListener('click', this.maybe_reset.bind(this));

            console.log(this.els);

        },

        start: function () {

            this.last_time = new Date().getTime();
            this.els.status.innerHTML = 'Starting';
            this.batch_log(`Starting batch at index ${this.params.index}.`);
            this.request('total', this.start_total.bind(this));
            this.els.iterator.classList.add('running');
            this.els.iterator.classList.remove('err');

        },

        start_total: function (total) {

            if (this.set_total(total)) this.request('iterate', this.iterate.bind(this));

        },

        set_total: function (total) {

            this.els.total.innerHTML = total;

            if (this.total = total) {

                if (this.params.index < total) { 

                    this.els.status.innerHTML = 'Running';
                    return true;

                } else {

                    this.els.status.innerHTML = 'Already Complete!';
                    this.els.iterator.classList.remove('running');
                    return false;

                }

            } else {

                this.els.status.innerHTML = `No ${this.params.labels.plural}`;
                this.batch_log(`Batch could not run as there are no ${this.params.labels.plural} to process.`);
                this.els.iterator.classList.remove('running');
                return false;

            }

        },

        iterate: function (response) {

            console.log(response);

            if (response.hasOwnProperty('total')) this.set_total(response.total);

            this.update_progress(response.index);
            this.params.index = response.index;

            if (response.hasOwnProperty('log') && response.log) for (const line of response.log) this.log(line);
            if (response.hasOwnProperty('errors') && response.errors) for (const line of response.errors) this.error(line, false);

            if (this.params.print_results) {

                if (response.results.processed.length)  for (const id of response.results.processed)    this.batch_log(` - OK: #${id}`);
                if (response.results.skipped.length)    for (const id of response.results.skipped)      this.batch_log(` - Skipped: #${id}`);
                if (response.results.failed.length)     for (const id of response.results.failed)       this.error(` - Failed: #${id}`, false);

            }

            if (response.results.failed.length && this.params.halt_on_fail) {
                
                this.els.status.innerHTML = 'Failed';
                this.error(`Failed to process ${response.results.failed.length} ${this.params.labels.plural} - Halting batch process.`, true);
                
            }

            this.batch_log(`${response.count} ${this.params.labels.plural} processed (${response.results.skipped.length} skipped, ${response.results.failed.length} failed) in ${this.delta}ms:`);

            if (response.index >= this.total) {

                this.els.status.innerHTML = 'Finished!';
                this.batch_log(`Batch completed in: ${this.time}ms.`);
                this.els.iterator.classList.remove('running');

            } else if (this.flags.stop) {

                this.els.status.innerHTML = 'Stopped';
                this.batch_log(`Batch stopped after ${this.time}ms.`);
                this.flags.stop = false;

            } else {

                this.request('iterate', this.iterate.bind(this));

            }

        },

        maybe_stop: function () {

            if (this.params.doing_cron) {

                if (confirm("Are you sure you want to cancel the current cron task?")) {

                    this.els.status.innerHTML = 'Stopping Cron Task';
                    this.batch_log(`Stopping cron task.`);
                    this.request('stop_cron', this.stop_cron_complete.bind(this));

                }

            } else {

                this.stop();

            }

        },

        stop_cron_complete: function () {

            this.els.status.innerHTML = 'Cron Task Stopped';
            this.batch_log(`Cron task stopped.`);
            this.params.doing_cron = false;
            this.els.iterator.classList.remove('running');

        },

        stop: function () {

            if (this.els.iterator.classList.contains('running')) {

                this.els.status.innerHTML = 'Stopping';
                this.batch_log(`Stopping batch.`);
                this.flags.stop = true;
                this.els.iterator.classList.remove('running');

            }

        },

        maybe_reset: function () {

            if (confirm("Are you sure the want to reset this batch process?")) this.reset();

        },

        reset: function () {

            this.stop();
            this.els.status.innerHTML = 'Resetting';
            this.batch_log(`Resetting batch process...`);
            this.request('reset', this.reset_complete.bind(this));

        },

        reset_complete: function (response) {

            //console.log(response);

            this.params.index = 0;
            this.time = 0;

            this.update_progress(response.index);
            
            this.els.status.innerHTML = 'Reset';
            this.els.time.innerHTML = '00:00:00';

            this.els.log.innerHTML = '';

        },

        request: function (task, callback) {

            if (task == 'iterate') this.batch_log(`Requesting batch process at ${this.params.labels.single} ${this.params.index} of ${this.total}.`);

            var http = new XMLHttpRequest();

            http.onreadystatechange = function() {

                if (http.readyState == XMLHttpRequest.DONE) {

                    let error = null;

                    switch (http.status) {

                        case 200:

                            // console.log(http.responseText);
                            callback(JSON.parse(http.responseText));
                            return;

                        case 400:

                            this.error(`AJAX Error: ${http.status} - Bad request trying to reach ${http.responseURL}`, true);
                            return;

                        case 401:

                            this.error(`AJAX Error: ${http.status} - ${http.responseText}`, true);
                            return;

                        default:

                            this.error(`AJAX Error: ${http.status} - ${http.responseText}`, true);
                            return;

                    }

                }

            }.bind(this);

            let url = `${this.params.ajax_url}?action=iterator_${this.params.key}&task=${task}&nonce=${this.params.nonce}`;
            console.log(url);
            http.open('GET', url, true);
            http.send();

        },

        update_progress: function (index) {

            let time = new Date().getTime();
            this.delta = time - this.last_time;
            this.time += this.delta;
            this.last_time = time;
            let secs = Math.floor(this.time / 1000);
            let mins = Math.floor(secs / 60);
            let hours = Math.floor(mins / 60);
            this.els.time.innerHTML = String(hours).padStart(2, 0) + ":" + String(mins % 60).padStart(2, 0) + ":" + String(secs % 60).padStart(2, 0);

            this.els.index.innerHTML = index;

            let percent = index ? (100 * index / this.total) : 0;
            this.els.percent.innerHTML = Math.floor(percent) + "%";
            this.els.progress.style.width = percent + "%";

        },

        log: function (msg, type = 'item') {

            let item = document.createElement("div");
            item.classList.add('log-' + type);
            item.innerHTML = msg;

            this.els.log.prepend(item);

        },

        batch_log: function (msg) {

            this.log(msg, 'batch');

        },

        error: function (error, halt = false) {

            if (halt) {

                this.stop();
                this.els.iterator.classList.add('err');
                console.error(error)

            }

            //this.els.errors.value = error + '\n' + this.els.errors.value ;

            this.log(error, 'error');

        },

    }

    iterator.init(params);

})(iterator_params);