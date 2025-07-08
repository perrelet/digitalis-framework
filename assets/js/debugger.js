class DigitalisDebuggerChip extends HTMLElement {

    static tag = `digitalis-debugger-chip`;

    connectedCallback() {

        this.tabIndex = 0;
        this.addEventListener("click", this.open);
        this.addEventListener("keydown", (e) => { if (e.code == `Enter`) this.click(); });

    }

    open() {

        DigitalisDebugger.closeAll();

        const debuggeer = document.querySelector(DigitalisDebugger.tag + `[data-index="${this.getAttribute(`data-index`)}"]`);

        debuggeer.expand();
        debuggeer.open();

    }

}

class DigitalisDebugger extends HTMLElement {

    static observedAttributes = [`open`];
    static tag = `digitalis-debugger`;
    static count = 0;

    static closeAll() {

        document.querySelectorAll(`digitalis-debugger`).forEach(item => item.close());

    }

    static find(i = false) {

        if (i === false) i = this.count;

        return document.querySelector(`digitalis-debugger[data-index='${i}']`)

    }

    constructor() {

        super();

    }

    connectedCallback() {

        DigitalisDebugger.count = this.getAttribute(`data-index`);
        this.tabIndex = 0;

        if (this.hasAttribute(`open`)) this.focus();

        this.addEventListener("keydown", (e) => { if (e.code == `Escape`)    this.close();        });
        this.addEventListener("keydown", (e) => { if (e.code == `ArrowUp`)   this.scroll(-250);   });
        this.addEventListener("keydown", (e) => { if (e.code == `ArrowDown`) this.scroll(250);    });
        this.addEventListener("keydown", (e) => { if (e.code == `PageUp`)    this.scrollPrev();   });
        this.addEventListener("keydown", (e) => { if (e.code == `PageDown`)  this.scrollNext();   });
        this.addEventListener("keydown", (e) => { if (e.code == `Home`)      this.scrollTop();    });
        this.addEventListener("keydown", (e) => { if (e.code == `End`)       this.scrollBottom(); });

        this.querySelectorAll(`button`).forEach(button => button.addEventListener("keydown", (e) => { if (e.code == `Enter`) button.click(); }))

        // https://github.com/WICG/webcomponents/issues/551
        // https://stackoverflow.com/questions/79060310/web-components-custom-parsedcallback-method-is-not-working
        // https://dev.to/dannyengelman/web-component-developers-do-not-connect-with-the-connectedcallback-yet-4jo7

        setTimeout(() => {

            this.main = this.querySelector(`main`);
            
        }, 1);

    }

    attributeChangedCallback(name, old_value, new_value) {

        if (name == `open`) {

            if (typeof new_value === "string") {
                this.onOpen();
            } else {
                this.onClose();
            }

        }

    }

    onOpen()  { this.focus(); }
    onClose() { this.blur();  }

    toggle() { this.toggleAttribute(`open`);  }
    open ()  { this.setAttribute(`open`, ``); }
    close () { this.removeAttribute(`open`);  }

    append (html) {

        this.querySelector(`main`).insertAdjacentHTML(`beforeend`, html);

    }

    getCurrent() {

        console.log(this.main);

        let current = this.main.children[0];

        for (const child of this.main.children) {
            if (child.getBoundingClientRect().top >= 0) {
                current = child;
                break;
            }
        }

        return current;

    }

    scroll(y) {

        this.main.scrollTo({
            top:      this.main.scrollTop + y,
            left:     0,
            behavior: "smooth",
        });

    }

    scrollTop() {

        this.main.scrollTo({
            top:      0,
            left:     0,
            behavior: "smooth",
        });

    }

    scrollBottom() {

        this.main.scrollTo({
            top:      this.main.scrollHeight,
            left:     0,
            behavior: "smooth",
        });

    }

    scrollPrev() {

        const current = this.getCurrent();

        if (current.previousElementSibling) current.previousElementSibling.scrollIntoView({
            behavior: 'smooth',
        });

    }

    scrollNext() {

        const current = this.getCurrent();

        if (current.nextElementSibling) current.nextElementSibling.scrollIntoView({
            behavior: 'smooth',
        });

    }

    collapseToggle() { this.toggleAttribute(`collapse`);  }
    collapse()       { this.setAttribute(`collapse`, ``); }
    expand()         { this.removeAttribute(`collapse`);  }

}

customElements.define(DigitalisDebuggerChip.tag, DigitalisDebuggerChip);
customElements.define(DigitalisDebugger.tag,     DigitalisDebugger);