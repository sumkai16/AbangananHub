import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    // Follows html.dark (set by partials/theme-init) rather than the OS setting, so stray dark: variants match the toggle.
    darkMode: 'class',

    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
                // Marketing / public-page headings (DESIGN.md §4). One name instead of an arbitrary family class on every heading.
                jakarta: ['"Plus Jakarta Sans"', 'Inter', ...defaultTheme.fontFamily.sans],
                // Navy/Terracotta identity (Sept 2026) — Montserrat replaces DM
                // Serif Display for headings/display titles: QA flagged the serif
                // as reading more editorial-magazine than apartment-listing, and
                // Montserrat's geometric sans matches the rest of the UI (Inter)
                // while still standing apart at heading weight. See DESIGN.md §4.
                heading: ['"DM Serif Display"', ...defaultTheme.fontFamily.serif],
                display: ['"DM Serif Display"', ...defaultTheme.fontFamily.serif],
            },
            colors: {
                brand: {
                    navy: '#060D26',
                    gold: '#FF8A66',
                    goldText: '#B35A3D',
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
