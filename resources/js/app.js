import './bootstrap';

import Alpine from 'alpinejs';
import persist from '@alpinejs/persist';
import TomSelect from 'tom-select';
import Sortable from 'sortablejs';
import 'tom-select/dist/css/tom-select.default.css';
import flatpickr from "flatpickr";
import 'flatpickr/dist/flatpickr.min.css';
import '../css/flatpickr-custom.css';

window.Alpine = Alpine;
window.TomSelect = TomSelect;
window.Sortable = Sortable;
window.flatpickr = flatpickr;

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

document.addEventListener('DOMContentLoaded', function () {
    if (document.getElementById('user_select')) {
        new TomSelect('#user_select', {
            plugins: ['remove_button'],
            create: false,
            maxItems: 20,
        });
    }

    flatpickr(".datepicker", {
        altInput: true,
        altFormat: "d-m-Y",
        dateFormat: "Y-m-d",
        onReady: function(selectedDates, dateStr, instance) {
            const todayBtn = document.createElement('a');
            todayBtn.className = 'flatpickr-today-button';
            todayBtn.href = '#';
            todayBtn.textContent = 'Vandaag';
            todayBtn.addEventListener('click', (e) => {
                e.preventDefault();
                instance.setDate(new Date(), true);
            });
            instance.calendarContainer.appendChild(todayBtn);
        }
    });
});

Alpine.start();
