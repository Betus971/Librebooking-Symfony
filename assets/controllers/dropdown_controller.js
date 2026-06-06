import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ["menu"];

    connect() {
        this.close = this.close.bind(this);
    }

    toggle(event) {
        event.stopPropagation();
        this.menuTarget.classList.toggle('hidden');
        
        if (!this.menuTarget.classList.contains('hidden')) {
            document.addEventListener('click', this.close);
        } else {
            document.removeEventListener('click', this.close);
        }
    }

    close(event) {
        if (!this.element.contains(event.target)) {
            this.menuTarget.classList.add('hidden');
            document.removeEventListener('click', this.close);
        }
    }
}
