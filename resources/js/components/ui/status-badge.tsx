import { Badge } from "@/components/ui/badge";
import { labelOf, statusTone } from "@/lib/labels";

export function StatusBadge({ value }: { value: string }) {
    return <Badge tone={statusTone(value)}>{labelOf(value)}</Badge>;
}
