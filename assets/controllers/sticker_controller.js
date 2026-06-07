import { Controller } from '@hotwired/stimulus';

/*
 * Adjust a sticker cell's quantity:
 *   tap / left click       → +1   (whole cell)
 *   the − button / right click → -1
 * The − button makes decrement work on touch (no right-click on mobile). Submits the
 * matching hidden form via Turbo (the server returns a Turbo Stream that replaces the
 * cell and the progress counters). A short pulse gives feedback.
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
        // When fired by the − button (a click inside the cell), don't let it bubble
        // up to the cell's own click handler and add +1 at the same time.
        event.stopPropagation();
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
