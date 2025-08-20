import { defineConfig } from "vite";
import { lingui } from "@lingui/vite-plugin";
import react from "@vitejs/plugin-react";
import { copy } from "vite-plugin-copy";

export default defineConfig(({ mode }) => {
    const isProd = mode === "production";

    return {
        optimizeDeps: {
            include: ["react-router"],
        },
        server: !isProd
            ? {
                  hmr: {
                      path: '/vite/ws',
                      protocol: 'wss',
                      host: 'yolo.ke',
                  },
                  origin: 'https://yolo.ke',
              }
            : undefined,
        plugins: [
            react({
                babel: {
                    plugins: ["macros"],
                },
            }),
            lingui(),
            copy({
                targets: [{ src: "src/embed/widget.js", dest: "public" }],
                hook: "writeBundle",
            }),
        ],
        define: {
            "process.env": process.env,
        },
        ssr: {
            noExternal: ["react-helmet-async"],
        },
        css: {
            preprocessorOptions: {
                scss: {
                    api: "modern-compiler",
                },
            },
        },
    };
});
