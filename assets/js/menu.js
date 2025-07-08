class DigitalisNav extends HTMLElement {

    static tag = `digitalis-nav`;

    parsedCallback () {

        this.hamburger = this.querySelector(`hamburger`);
        this.items     = this.querySelectorAll(`.menu-item`);

        if (this.hamburger) this.hamburger.addEventListener(`click`, e => this.openMobile());

        this.addEventListener('focusout', e => {

            // Works well until you have nested fixed children

            /* const parentWrap = e.target.closest(`.child-wrap`);
            if (!parentWrap || (parentWrap.getAttribute(`data-fixed`) != `true`)) return;

            if (e.relatedTarget) {
                const relatedWrap = e.relatedTarget.closest(`.child-wrap`);
                if (relatedWrap == parentWrap) return;
            }

            if (!e.relatedTarget || (e.relatedTarget.compareDocumentPosition(e.target) == 2)) {
                parentWrap.previousElementSibling.focusFirstChild();
            } else {
                parentWrap.previousElementSibling.focusLastChild();
            } */

        });

        this.items.forEach((item) => {

            item.open = () => { if (item.hasChild()) {
                item.setAttribute(`aria-expanded`, `true`);
                item.child.setAttribute(`open`, ``);
                if (item.child.getAttribute(`data-fixed`) == `true`) document.body.setAttribute(`lock-scroll`, ``);
            }}; 

            item.close = () => { if (item.hasChild()) {
                item.setAttribute(`aria-expanded`, `false`);
                item.child.removeAttribute(`open`);
                if (item.child.getAttribute(`data-fixed`) == `true`) document.body.removeAttribute(`lock-scroll`);
            }}; 

            item.isOpen = ()            => item.getAttribute(`aria-expanded`) == `true`; 
            item.toggle = ()            => item.isOpen() ? item.close() : item.open();
            item.closeParent = ()       => item.hasParent() ? item.parentItem.close() : null;

            item.hasParent = ()         => item.parentItem !== null;
            item.hasChild = ()          => item.child !== null;
            item.getChildList = ()      => item.hasChild() && (item.child.children[0].tagName == `UL`) ? item.child.children[0] : null;
            item.hasChildList = ()      => item.getChildList() !== null;
            item.getChildItemWrap = (i) => item.hasChildList() ? item.getChildList().children[i] ?? null : null;
            item.getChildItem = (i)     => item.getChildItemWrap(i) ? item.getChildItemWrap(i).getItem() : null;
            item.getNext = ()           => item.wrap.nextElementSibling     ? item.wrap.nextElementSibling.getItem()     : item.list.firstElementChild.getItem();
            item.getPrev = ()           => item.wrap.previousElementSibling ? item.wrap.previousElementSibling.getItem() : item.list.lastElementChild.getItem();

            item.focusNext = ()         => item.getNext() ? item.getNext().focus() : null;
            item.focusPrev = ()         => item.getPrev() ? item.getPrev().focus() : null;
            item.focusFirst = ()        => item.list.firstElementChild.getItem().focus();
            item.focusLast = ()         => item.list.lastElementChild.getItem().focus();
            item.focusFirstChild = ()   => item.hasChildList() ? item.getChildList().firstElementChild.getItem().focus() : null;
            item.focusLastChild = ()    => item.hasChildList() ? item.getChildList().lastElementChild.getItem().focus() : null;
            
            item.getTriggers = ()       => item.getAttribute(`data-triggers`);
            item.hasTrigger = (trigger) => item.getTriggers() ? item.getTriggers().includes(trigger) : false;
            item.getInDelay = ()        => parseInt(item.getAttribute(`data-in-delay`));
            item.getOutDelay = ()       => parseInt(item.getAttribute(`data-out-delay`));

            item.childWrap  = item.parentElement.closest(`.child-wrap`);
            item.parentItem = item.childWrap ? item.childWrap.previousElementSibling : null;
            item.wrap       = item.parentElement;
            item.list       = item.wrap.parentElement;
            item.child      = item.nextElementSibling;

            item.wrap.getItem = () => item.wrap.firstElementChild;

            if (item.hasChild() && item.hasTrigger(`click`)) {

                item.addEventListener(`click`, e => item.open());

            }

            if (item.hasChild() && item.hasTrigger(`over`)) {

                item.wrap.addEventListener(`mouseenter`, e => {

                    item.timerIn = setTimeout(() => {
                        //this.closeAllItems();
                        this.closeAllSiblings(item);
                        item.open();
                    }, item.getInDelay());

                    if (item.timerOut) clearTimeout(item.timerOut);

                });

                item.wrap.addEventListener(`mouseleave`, e => {

                    item.timerOut = setTimeout(() => item.close(), item.getOutDelay());

                    if (item.timerIn) clearTimeout(item.timerIn);

                });

            }

            if (item.hasTrigger(`keys`)) {

                item.addEventListener(`keydown`, e => {

                    if (e.code == `ArrowRight`) {

                        item.focusNext();

                    } else if (e.code == `ArrowLeft`) {

                        item.focusPrev();

                    } else if (e.code == `Home`) {

                        item.focusFirst();

                    } else if (e.code == `End`) {

                        item.focusLast();

                    }

                    if (item.hasChild()) {


                        if ([`Space`, `Enter`, `NumpadEnter`, `ArrowDown`].includes(e.code)) {

                            item.open();
                            item.focusFirstChild();

                        } else if (e.code == `ArrowUp`) {

                            item.open();
                            item.focusLastChild();

                        }

                    }

                });

            }

            if (item.parentItem && item.parentItem.hasTrigger(`keys`)) {

                item.addEventListener(`keydown`, e => {

                    if (e.code == `Escape`) {

                        item.parentItem.close();
                        item.parentItem.focus();

                    }

                });

            }

            
        });

    }

    getMobile () {

        return this.id.includes(`-mobile`) ? this : document.getElementById(this.id + `-mobile`);

    }

    getDesktop () {

        return this.id.includes(`-mobile`) ? document.getElementById(this.id.replace(`-mobile`, ``)) : this;

    }

    open () {

        this.setAttribute(`aria-hidden`, `false`);

    }

    close () {

        this.setAttribute(`aria-hidden`, `true`);

    }

    openMobile () {

        const mobile = this.getMobile();
        if (!mobile) return;
        mobile.open();

    }

    closeMobile () {

        const mobile = this.getMobile();
        if (!mobile) return;
        mobile.close();

    }

    closeAllItems () {

        this.items.forEach((item) => { item.close() });

    }

    closeAllSiblings (item) {

        Array.from(item.list.children).forEach(sibling => sibling.getItem().close());

    }

}

// https://github.com/WICG/webcomponents/issues/551
// https://stackoverflow.com/questions/79060310/web-components-custom-parsedcallback-method-is-not-working
// https://dev.to/dannyengelman/web-component-developers-do-not-connect-with-the-connectedcallback-yet-4jo7

if(!customElements.get(`parsed-callback`)) customElements.define(`parsed-callback`, class extends HTMLElement { connectedCallback() {

    this.parentElement.parsedCallback();
    this.remove();

}});

customElements.define(DigitalisNav.tag, DigitalisNav);
