import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    base: './',
    plugins: [vue()],
    server: {
        port: 5173,
        proxy: {
            '/TAIPO/api': {
                target: 'http://localhost:8000',
                changeOrigin: true,
                rewrite: (path) => path.replace(/^\/TAIPO\/api/, '')
            }
        }
    }
});
