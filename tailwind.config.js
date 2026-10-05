import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/*
 * Trilha+ visual system: petrol ink, cool grey-green neutrals, one saffron signal.
 * `indigo` and `blue` are remapped to the petrol scale so existing utilities follow the brand.
 */
const petrol = {
    50: '#EEF5F5', 100: '#D8E8E9', 200: '#B3D0D3', 300: '#86B1B6', 400: '#4F8A91',
    500: '#2A6670', 600: '#14505A', 700: '#0F3D44', 800: '#0B2E34', 900: '#082126', 950: '#05161A',
};
const ink = {
    50: '#F4F6F6', 100: '#E9EDED', 200: '#D5DCDC', 300: '#B7C2C2', 400: '#8A9898',
    500: '#647474', 600: '#4A5959', 700: '#364343', 800: '#222D2D', 900: '#141D1E', 950: '#0B1213',
};

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
            colors: {
                indigo: petrol,
                blue: petrol,
                slate: ink,
                gray: ink,
                signal: { 300: '#E5B454', 400: '#D89E2B', 500: '#C98A12', 600: '#A8700C' },
            },
            fontFamily: {
                sans: ['"IBM Plex Sans"', ...defaultTheme.fontFamily.sans],
                display: ['"IBM Plex Sans Condensed"', '"IBM Plex Sans"', ...defaultTheme.fontFamily.sans],
            },
            borderRadius: {
                md: '3px',
                lg: '4px',
                xl: '6px',
                '2xl': '6px',
                '3xl': '8px',
            },
            boxShadow: {
                sm: '0 1px 0 rgba(20, 29, 30, 0.05)',
                md: '0 2px 6px rgba(20, 29, 30, 0.08)',
                lg: '0 8px 24px rgba(20, 29, 30, 0.12)',
                xl: '0 12px 32px rgba(20, 29, 30, 0.14)',
            },
        },
    },

    plugins: [forms],
};
