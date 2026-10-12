import { usePage } from "@inertiajs/react";
import { Badge } from "@/components/ui/badge";
import { labelOf, statusTone } from "@/lib/labels";
import type { SharedProps } from "@/types";

export function StatusBadge({ value }: { value: string }) {
    const labels = usePage<SharedProps>().props.enum_labels;
    const tone = statusTone(value);

    return (
        <Badge tone={tone}>
            <span className="size-1.5 rounded-full bg-current" aria-hidden="true" />
            {labelOf(value, labels)}
        </Badge>
    );
}
