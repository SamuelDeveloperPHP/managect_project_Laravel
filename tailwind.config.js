import forms from '@tailwindcss/forms';
import { legacyPresets } from './resources/design/nexocore-preset.mjs';

/*
 * Trilha+ usa o tema `trilha` dos tokens NexoCore (ver resources/design/nexocore-preset.mjs,
 * copiado de nexocore-design/dist). `legacyPresets` mapeia indigo/blue -> brand e
 * slate/gray -> neutral, para que as classes já usadas no código sigam a marca.
 */

/** @type {import('tailwindcss').Config} */
export default {
    presets: [legacyPresets.trilha],

    theme: {
        extend: {
            // Direção visual v2: tipografia Inter/Manrope e cantos mais generosos que o preset base.
            fontFamily: {
                sans: ['Inter', 'system-ui', '-apple-system', 'Segoe UI', 'sans-serif'],
                display: ['Manrope', 'Inter', 'system-ui', 'sans-serif'],
            },
            borderRadius: { md: '8px', lg: '10px', xl: '14px', '2xl': '20px', '3xl': '28px' },
        },
    },

    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.tsx',
    ],

    plugins: [forms],
};
