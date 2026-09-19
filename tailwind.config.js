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
<<<<<<< HEAD
                // Navy/Gold identity (Sept 2026) — Montserrat replaces DM Serif
                // Display for headings/display titles: QA flagged the serif as
                // reading more editorial-magazine than apartment-listing, and
                // Montserrat's geometric sans matches the rest of the UI (Inter)
                // while still standing apart at heading weight. See DESIGN.md §4.
                heading: ['Montserrat', ...defaultTheme.fontFamily.sans],
                display: ['Montserrat', ...defaultTheme.fontFamily.sans],
=======
                // Navy/Coral identity (Sept 2026) — DM Serif Display replaces Poppins
                // for headings and Source Serif 4 for display titles; see DESIGN.md §4.
                heading: ['"DM Serif Display"', 'Georgia', ...defaultTheme.fontFamily.serif],
                display: ['"DM Serif Display"', 'Georgia', ...defaultTheme.fontFamily.serif],
>>>>>>> 092fb1454a20ae889717d4d8b1bee67f9c0c8eaa
            },
            colors: {
                brand: {
                    navy: '#060D26',
<<<<<<< HEAD
                    gold: '#DA8E77',
                    goldText: '#A8573F',
=======
                    coral: '#FF8A66',
                    coralText: '#B35A3D',
>>>>>>> 092fb1454a20ae889717d4d8b1bee67f9c0c8eaa
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
