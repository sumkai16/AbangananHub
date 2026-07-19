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
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                brand: {
                    primary: '#156F8C',
                    secondary: '#2AA7A1',
                    cta: '#FF8A65',
                    dark: '#0F172A',
                    slate: '#1F2937',
                    muted: '#64748B',
                },
            },
        },
    },

    plugins: [forms],
};
