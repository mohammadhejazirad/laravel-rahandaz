import { ChevronLeft } from "lucide-react";
import type { ComponentProps } from "react";
import { Slot } from "@radix-ui/react-slot";
import { cn } from "@/lib/cn";

function Breadcrumb(props: ComponentProps<"nav">) {
    return <nav aria-label="مسیر صفحه" data-slot="breadcrumb" {...props} />;
}

function BreadcrumbList({ className, ...props }: ComponentProps<"ol">) {
    return (
        <ol
            data-slot="breadcrumb-list"
            className={cn(
                "flex flex-wrap items-center gap-1.5 text-sm text-muted",
                className,
            )}
            {...props}
        />
    );
}

function BreadcrumbItem({ className, ...props }: ComponentProps<"li">) {
    return (
        <li
            data-slot="breadcrumb-item"
            className={cn("inline-flex min-w-0 max-w-full items-center gap-1.5 break-words", className)}
            {...props}
        />
    );
}

function BreadcrumbLink({
    asChild = false,
    className,
    ...props
}: ComponentProps<"a"> & { asChild?: boolean }) {
    const Component = asChild ? Slot : "a";

    return (
        <Component
            data-slot="breadcrumb-link"
            className={cn(
                "rounded-md font-medium transition-colors hover:text-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent",
                className,
            )}
            {...props}
        />
    );
}

function BreadcrumbPage({ className, ...props }: ComponentProps<"span">) {
    return (
        <span
            data-slot="breadcrumb-page"
            aria-current="page"
            className={cn("font-medium text-ink", className)}
            {...props}
        />
    );
}

function BreadcrumbSeparator({ className, ...props }: ComponentProps<"li">) {
    return (
        <li
            data-slot="breadcrumb-separator"
            role="presentation"
            aria-hidden="true"
            className={cn("text-muted/70", className)}
            {...props}
        >
            <ChevronLeft className="size-4" />
        </li>
    );
}

export {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
};
