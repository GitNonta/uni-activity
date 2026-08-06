import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import colors from 'tailwindcss/colors';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            colors: {
                primary: {
                    DEFAULT: '#ea580c', // Red-Orange
                    light: '#f97316', // Orange
                    dark: '#c2410c', // Darker Orange
                    bg: '#fff7ed', // Very light orange background
                },
                // Override default colors to apply the Orange theme globally
                indigo: colors.orange,
                blue: colors.orange,
                purple: colors.red,
            },
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
        },
    },

    plugins: [forms],
};
