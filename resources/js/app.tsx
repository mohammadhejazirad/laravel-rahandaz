import { createInertiaApp, type ResolvedComponent } from "@inertiajs/react";
import { createRoot } from "react-dom/client";
import { StrictMode } from "react";
import { TooltipProvider } from "@/components/ui/tooltip";

const pages = import.meta.glob("./pages/**/*.tsx");

createInertiaApp({
    title: (title) => (title ? `${title} · راه‌انداز` : "راه‌انداز"),
    resolve: async (name) => {
        const load = pages[`./pages/${name}.tsx`];

        if (!load) {
            throw new Error(`Missing page: ${name}`);
        }

        const module = (await load()) as { default: ResolvedComponent };

        return module.default;
    },
    setup({ el, App, props }) {
        createRoot(el).render(
            <StrictMode>
                <TooltipProvider>
                    <App {...props} />
                </TooltipProvider>
            </StrictMode>,
        );
    },
    progress: {
        color: "#4F46E5",
        showSpinner: false,
    },
});
