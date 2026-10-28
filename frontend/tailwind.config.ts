import type { Config } from 'tailwindcss';
import typography from '@tailwindcss/typography';
// @ts-ignore
import daisyui from 'daisyui';

export default {
    content: [
        "./index.html",
        "./src/**/*.{vue,js,ts,jsx,tsx}",
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: ['var(--font-sans)', 'Inter', 'system-ui', '-apple-system', 'BlinkMacSystemFont', 'Segoe UI', 'Roboto', 'sans-serif'],
                display: ['var(--font-display)', 'Outfit', 'Inter', 'sans-serif'],
                mono: ['var(--font-mono)', 'JetBrains Mono', 'Fira Code', 'Courier New', 'monospace'],
                condensed: ['var(--font-condensed)', 'Oswald', 'sans-serif'],
                serif: ['var(--font-serif)', 'Roboto Serif', 'Georgia', 'serif'],
                serifDisplay: ['var(--font-serif-display)', 'Playfair Display', 'Georgia', 'serif'],
            },
            colors: {
                taipo: {
                    dark: '#0b0f19',
                    column: '#131b2e',
                    card: '#1b243b',
                    border: '#243456',
                    accent: '#8b5cf6',
                    cyan: '#06b6d4',
                    blue: '#3b82f6'
                }
            }
        },
    },
    plugins: [
        typography,
        daisyui,
    ],
    daisyui: {
        themes: [
            {
                dark: {
                    "primary": "#3b82f6",
                    "primary-content": "#ffffff",
                    "secondary": "#8b5cf6",
                    "secondary-content": "#ffffff",
                    "accent": "#06b6d4",
                    "accent-content": "#ffffff",
                    "neutral": "#1e293b",
                    "neutral-content": "#f8fafc",
                    "base-100": "#0b0f19",
                    "base-200": "#111827",
                    "base-300": "#1e293b",
                    "base-content": "#f1f5f9",
                    "info": "#38bdf8",
                    "info-content": "#082f49",
                    "success": "#22c55e",
                    "success-content": "#052e16",
                    "warning": "#f59e0b",
                    "warning-content": "#451a03",
                    "error": "#ef4444",
                    "error-content": "#450a0a",
                },
            },
            "light",
            "cupcake"
        ],
    },
} satisfies Config;
