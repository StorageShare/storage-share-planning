import './bootstrap';

import Alpine from 'alpinejs';
import persist from '@alpinejs/persist';

window.Alpine = Alpine;

Alpine.plugin(persist);

Alpine.store('theme', {
    darkMode: Alpine.$persist(window.matchMedia('(prefers-color-scheme: dark)').matches).as('darkMode'),

    init() {
        this.toggleHtmlClass();
    },

    toggle() {
        this.darkMode = !this.darkMode;
        this.toggleHtmlClass();
    },

    toggleHtmlClass() {
        if (this.darkMode) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    }
});

Alpine.start();
