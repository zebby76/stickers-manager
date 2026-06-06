import { Controller } from '@hotwired/stimulus';

/*
 * Click a sticker cell to adjust its quantity:
 *   left click  → +1
 *   right click → -1
 * Submits the matching hidden form via Turbo (the server returns a Turbo Stream
 * that replaces the cell and the progress counters). A short pulse gives feedback.
 */
export default class extends Controller {
    static targets = ['inc', 'dec'];

    inc(event) {
        event.preventDefault();
        this.pulse('pulse-add');
        this.incTarget.requestSubmit();
    }

    dec(event) {
        event.preventDefault();
        this.pulse('pulse-remove');
        this.decTarget.requestSubmit();
    }

    pulse(cls) {
        this.element.classList.remove('pulse-add', 'pulse-remove');
        // force reflow so the animation restarts on rapid repeated clicks
        void this.element.offsetWidth;
        this.element.classList.add(cls);
    }
}
