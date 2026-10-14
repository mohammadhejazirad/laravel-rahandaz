import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';
import { fileURLToPath, URL } from 'node:url';

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');
    const runsInsideLerd = Boolean(process.env.LERD_SITE);
    const appUrl = new URL(env.APP_URL || 'http://127.0.0.1:8000');

    return {
        plugins: [
            laravel({
                input: ['resources/css/app.css', 'resources/js/app.tsx'],
                refresh: true,
            }),
            react(),
            tailwindcss(),
        ],
        resolve: {
            alias: {
                '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
            },
        },
        server: {
            ...(runsInsideLerd
                ? {
                      host: '0.0.0.0',
                      port: 5173,
                      strictPort: true,
                      origin: `${appUrl.protocol}//${appUrl.hostname}:5173`,
                      hmr: {
                          host: appUrl.hostname,
                          clientPort: 5173,
                      },
                  }
                : {}),
            watch: {
                ignored: ['**/storage/framework/views/**'],
            },
        },
    };
});
