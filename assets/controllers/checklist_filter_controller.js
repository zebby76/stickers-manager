import { Controller } from '@hotwired/stimulus';

/*
 * Client-side filtering of the album checklist: search by sticker number and
 * filter by status (all / missing / owned / duplicates / shiny). Empty team
 * sections are hidden. Re-applies automatically when Turbo replaces a cell.
 */
export default class extends Controller {
    static targets = ['search', 'cell', 'section', 'count'];

    connect() {
        this.mode = 'all';
        this.apply();
    }

    setMode(event) {
        this.mode = event.target.value;
        this.apply();
    }

    // Coalesce the many cellTargetConnected() calls (one per cell) into one apply.
    schedule() {
        clearTimeout(this._t);
        this._t = setTimeout(() => this.apply(), 16);
    }

    cellTargetConnected() {
        this.schedule();
    }

    apply() {
        const q = (this.hasSearchTarget ? this.searchTarget.value : '').trim().toLowerCase();
        let visible = 0;

        for (const cell of this.cellTargets) {
            const show = this.matches(cell, q);
            cell.classList.toggle('d-none', !show);
            if (show) visible++;
        }

        for (const section of this.sectionTargets) {
            const anyVisible = section.querySelector('[data-checklist-filter-target="cell"]:not(.d-none)') !== null;
            section.classList.toggle('d-none', !anyVisible);
        }

        if (this.hasCountTarget) {
            this.countTarget.textContent = `${visible} vignette${visible > 1 ? 's' : ''}`;
        }
    }

    matches(cell, q) {
        if (q && !cell.dataset.number.includes(q)) {
            return false;
        }
        switch (this.mode) {
            case 'missing': return cell.dataset.owned === '0';
            case 'owned': return cell.dataset.owned === '1';
            case 'duplicates': return cell.dataset.dup === '1';
            case 'shiny': return cell.dataset.rarity === 'shiny';
            default: return true;
        }
    }
}
