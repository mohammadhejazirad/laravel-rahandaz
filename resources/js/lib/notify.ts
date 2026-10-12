import {
    toast,
    type Id,
    type ToastOptions,
    type TypeOptions,
    type UpdateOptions,
} from "react-toastify/unstyled";

type NotifyOptions = Omit<ToastOptions, "type">;

const queuedNotice =
    "این پیام فقط ثبت درخواست را تأیید می‌کند و به‌معنی پایان موفق عملیات روی میزبان نیست.";

function optionsFor(
    type: TypeOptions,
    options: NotifyOptions = {},
): NotifyOptions {
    return {
        role: type === "error" || type === "warning" ? "alert" : "status",
        ...options,
    };
}

function firstError(errors: Record<string, unknown>): string | null {
    for (const value of Object.values(errors)) {
        if (typeof value === "string" && value.trim() !== "") {
            return value;
        }

        if (Array.isArray(value)) {
            const message = value.find(
                (item): item is string =>
                    typeof item === "string" && item.trim() !== "",
            );

            if (message) {
                return message;
            }
        }
    }

    return null;
}

export const notify = {
    success(message: string, options?: NotifyOptions) {
        return toast.success(message, optionsFor("success", options));
    },
    error(message: string, options?: NotifyOptions) {
        return toast.error(message, optionsFor("error", options));
    },
    warning(message: string, options?: NotifyOptions) {
        return toast.warning(message, optionsFor("warning", options));
    },
    info(message: string, options?: NotifyOptions) {
        return toast.info(message, optionsFor("info", options));
    },
    default(message: string, options?: NotifyOptions) {
        return toast(message, optionsFor("default", options));
    },
    queued(message: string, options?: NotifyOptions) {
        return toast.info(`${message}\n${queuedNotice}`, {
            ...optionsFor("info", options),
            autoClose: options?.autoClose ?? 8000,
        });
    },
    loading(message: string, options?: NotifyOptions) {
        return toast.loading(message, optionsFor("default", options));
    },
    update(
        id: Id,
        message: string,
        type: TypeOptions = "default",
        options: UpdateOptions = {},
    ) {
        toast.update(id, {
            ...options,
            render: message,
            type,
            isLoading: false,
            role:
                type === "error" || type === "warning" ? "alert" : "status",
            autoClose: options.autoClose ?? 5000,
            closeButton: options.closeButton ?? true,
            closeOnClick: options.closeOnClick ?? false,
            draggable: options.draggable ?? "touch",
        });
    },
    dismiss(id?: Id) {
        toast.dismiss(id);
    },
    formErrors(
        errors: Record<string, unknown>,
        fallback = "اطلاعات فرم را بررسی کنید.",
    ) {
        const message = firstError(errors) ?? fallback;

        return toast.error(message, optionsFor("error"));
    },
};
