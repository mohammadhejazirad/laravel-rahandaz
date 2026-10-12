import { Badge } from "@/components/ui/badge";
import { labelOf, statusTone } from "@/lib/labels";

export function StatusBadge({ value }: { value: string }) {
    const tone = statusTone(value);

    return (
        <Badge tone={tone}>
            <span className="size-1.5 rounded-full bg-current" aria-hidden="true" />
            {labelOf(value)}
        </Badge>
    );
}
