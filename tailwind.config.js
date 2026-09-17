import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
                // Navy/Gold identity (Sept 2026) — Montserrat replaces DM Serif
                // Display for headings/display titles: QA flagged the serif as
                // reading more editorial-magazine than apartment-listing, and
                // Montserrat's geometric sans matches the rest of the UI (Inter)
                // while still standing apart at heading weight. See DESIGN.md §4.
                heading: ['Montserrat', ...defaultTheme.fontFamily.sans],
                display: ['Montserrat', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                brand: {
                    navy: '#060D26',
                    gold: '#DA8E77',
                    goldText: '#A8573F',
                    cream: '#F7F4ED',
                    slate: '#5B6A8E',
                    border: '#E2E4EC',
                    bg: '#F7F8FC',
                    mist: '#ECEEF6',
                    footer: '#060D26',
                },
            },
        },
    },

    plugins: [forms],
};
