import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ["iconLight", "iconDark"];

    connect() {
        if (localStorage.getItem('color-theme') === 'dark' || (!('color-theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
            this.iconLightTarget.classList.add('hidden');
            this.iconDarkTarget.classList.remove('hidden');
        } else {
            document.documentElement.classList.remove('dark');
            this.iconDarkTarget.classList.add('hidden');
            this.iconLightTarget.classList.remove('hidden');
        }
    }

    toggle() {
        if (document.documentElement.classList.contains('dark')) {
            document.documentElement.classList.remove('dark');
            localStorage.setItem('color-theme', 'light');
            this.iconDarkTarget.classList.add('hidden');
            this.iconLightTarget.classList.remove('hidden');
        } else {
            document.documentElement.classList.add('dark');
            localStorage.setItem('color-theme', 'dark');
            this.iconLightTarget.classList.add('hidden');
            this.iconDarkTarget.classList.remove('hidden');
        }
    }
}
