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
                // Navy/Gold identity (Sept 2026) — DM Serif Display replaces Poppins
                // for headings and Source Serif 4 for display titles; see DESIGN.md §4.
                heading: ['"DM Serif Display"', 'Georgia', ...defaultTheme.fontFamily.serif],
                display: ['"DM Serif Display"', 'Georgia', ...defaultTheme.fontFamily.serif],
            },
            colors: {
                brand: {
                    navy: '#060D26',
                    gold: '#C9A84C',
                    goldText: '#8a6e1e',
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
