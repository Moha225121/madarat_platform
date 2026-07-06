import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.tsx',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Tajawal', 'Cairo', 'Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                madarat: {
                    blue: '#0B4F7A',
                    navy: '#073B5F',
                    cyan: '#18B7C8',
                    sky: '#EAF8FB',
                    gray: '#F4F7FA',
                    dark: '#102A43',
                },
            },
        },
    },

    plugins: [forms],
};
