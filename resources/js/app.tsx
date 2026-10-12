import { createInertiaApp } from "@inertiajs/react";
import { createRoot } from "react-dom/client";
import { StrictMode, type ComponentType } from "react";

const pages = import.meta.glob("./pages/**/*.tsx", { eager: true });

createInertiaApp({
    title: (title) => (title ? `${title} · راه‌انداز` : "راه‌انداز"),
    resolve: (name) => {
        const page = pages[`./pages/${name}.tsx`] as
            | { default: ComponentType }
            | undefined;

        if (!page) {
            throw new Error(`Missing page: ${name}`);
        }

        return page;
    },
    setup({ el, App, props }) {
        createRoot(el).render(
            <StrictMode>
                <App {...props} />
            </StrictMode>,
        );
    },
    progress: {
        color: "#4F46E5",
        showSpinner: false,
    },
});
