(function() {
    
    let counter = {

        counters: null,
        observer: null,

        init: function () {

            var url = new URL(window.location.href);
            var ct_builder = url.searchParams.get("ct_builder");

            if (ct_builder) return;

            this.counters = document.querySelectorAll('.digitalis-counter');

            let observer_options = { 
                root:       null,
                threshold:  0,
                rootMargin: "0px"
            };

            this.observer = new IntersectionObserver(function(entries, observer) { 
                
                entries.forEach( entry => {

                    if(entry.isIntersecting) this.start(entry.target);

                });
                  
            }.bind(this), observer_options);

            this.counters.forEach(section => {

                this.observer.observe(section); 

            });

        },

        start: function (number) {

            if (number.getAttribute('data-started')) return;

            number.setAttribute('data-started', true);

            let start   = !number.getAttribute('data-start')    ? 0     : parseFloat(number.getAttribute('data-start'));
            let fps     = !number.getAttribute('data-fps')      ? 50    : parseFloat(number.getAttribute('data-fps'));
            let step    = !number.getAttribute('data-step')     ? 1     : parseFloat(number.getAttribute('data-step'));
            let prefix  = !number.getAttribute('data-prefix')   ? ""    : number.getAttribute('data-prefix');
            let suffix  = !number.getAttribute('data-suffix')   ? ""    : number.getAttribute('data-suffix');

            let end = number.innerText;
            let value = start;

            number.innerText = start;

            let interval = setInterval(function() {

                value += step;

                if (value >= end) {
                    clearInterval(interval);
                    value = end;
                }

                number.innerText = prefix + value + suffix;

            }, 1000 / fps);

        }

    }

    counter.init();

})();