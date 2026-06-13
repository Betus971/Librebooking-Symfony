import { Controller } from '@hotwired/stimulus';

/**
 * Bascule de thème clair/sombre.
 *
 * Source de vérité unique : l'attribut data-theme sur <html>, lu par DaisyUI.
 * On synchronise aussi la classe .dark (pour les utilitaires Tailwind dark:)
 * et l'affichage des icônes soleil/lune.
 */
export default class extends Controller {
    static targets = ["iconLight", "iconDark"];

    connect() {
        const stored = localStorage.getItem('color-theme');
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        this.apply(stored ? stored === 'dark' : prefersDark);
    }

    toggle() {
        const dark = document.documentElement.getAttribute('data-theme') !== 'dark';
        localStorage.setItem('color-theme', dark ? 'dark' : 'light');
        this.apply(dark);
    }

    apply(dark) {
        const root = document.documentElement;
        root.setAttribute('data-theme', dark ? 'dark' : 'light');
        root.classList.toggle('dark', dark);

        if (this.hasIconLightTarget) {
            this.iconLightTarget.classList.toggle('hidden', dark);
        }
        if (this.hasIconDarkTarget) {
            this.iconDarkTarget.classList.toggle('hidden', !dark);
        }
    }
}
