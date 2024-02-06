(function() {
    
    let counter = {

        counters: null,
        observer: null,

        init: function () {

            var url = new URL(window.location.href);
            if (url.searchParams.get("ct_builder")) return;

            this.counters = document.querySelectorAll('.digitalis-counter');

            let observer_options = { 
                root:       null,
                threshold:  0,
                rootMargin: "0px"
            };

            this.observer = new IntersectionObserver(function(entries, observer) { 
                
                entries.forEach(entry => {

                    if(!entry.isIntersecting) return;
                    
                    const delay = entry.target.getAttribute('data-delay') ? entry.target.getAttribute('data-delay') : 0;
                    setTimeout(() => this.start(entry.target), delay);

                });
                  
            }.bind(this), observer_options);

            this.counters.forEach(counter => {

                this.observer.observe(counter);

            });

        },

        start: function (counter) {

            if (counter.getAttribute('data-started')) return;

            counter.setAttribute('data-started', true);

            const start   = counter.getAttribute('data-start')   ? parseFloat(counter.getAttribute('data-start'))  : 0 ;
            const fps     = counter.getAttribute('data-fps')     ? parseFloat(counter.getAttribute('data-fps'))    : 50;
            const step    = counter.getAttribute('data-step')    ? parseFloat(counter.getAttribute('data-step'))   : 1 ;
            const prefix  = counter.getAttribute('data-prefix')  ? counter.getAttribute('data-prefix')             : "";
            const suffix  = counter.getAttribute('data-suffix')  ? counter.getAttribute('data-suffix')             : "";
            
            const end     = counter.getAttribute('data-end')     ? counter.getAttribute('data-end')                : counter.innerText;

            let value = start;

            counter.innerText = start;

            const interval = setInterval(function() {

                value += step;

                if (value >= end) {
                    clearInterval(interval);
                    value = end;
                }

                counter.innerText = prefix + value + suffix;

            }, 1000 / fps);

        }

    }

    counter.init();

})();