import { toast } from "sonner";

export const notify = {
    success(message: string) {
        toast.success(message);
    },
    error(message: string) {
        toast.error(message);
    },
    info(message: string) {
        toast.message(message);
    },
    queued(message: string) {
        toast.message(message, {
            description: "این پیام به‌معنی پایان عملیات روی میزبان نیست.",
        });
    },
};
