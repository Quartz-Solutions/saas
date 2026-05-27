import {
    DndContext,
    KeyboardSensor,
    PointerSensor,
    closestCenter,
    useSensor,
    useSensors,
} from '@dnd-kit/core';
import type { DragEndEvent } from '@dnd-kit/core';
import {
    SortableContext,
    arrayMove,
    sortableKeyboardCoordinates,
    useSortable,
    verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { GripVertical, Plus, X } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

export type MenuItem = {
    label: string;
    url: string;
    target?: '_self' | '_blank';
    children?: MenuItem[];
};

type Props = {
    items: MenuItem[];
    onChange: (next: MenuItem[]) => void;
};

function makeId(idx: number, item: MenuItem): string {
    return `${idx}-${item.label || item.url || 'untitled'}`;
}

function SortableRow({
    id,
    item,
    onChange,
    onDelete,
}: {
    id: string;
    item: MenuItem;
    onChange: (next: MenuItem) => void;
    onDelete: () => void;
}) {
    const { attributes, listeners, setNodeRef, transform, transition } = useSortable({ id });

    return (
        <div
            ref={setNodeRef}
            style={{ transform: CSS.Transform.toString(transform), transition }}
            className="flex items-center gap-2 rounded-md border border-border/60 bg-card px-2 py-2"
        >
            <button
                type="button"
                className="cursor-grab touch-none rounded p-1 text-muted-foreground hover:bg-muted active:cursor-grabbing"
                aria-label="Drag to reorder"
                {...attributes}
                {...listeners}
            >
                <GripVertical className="size-4" />
            </button>
            <Input
                value={item.label}
                onChange={(e) => onChange({ ...item, label: e.target.value })}
                placeholder="Label"
                className="flex-1"
            />
            <Input
                value={item.url}
                onChange={(e) => onChange({ ...item, url: e.target.value })}
                placeholder="/path or https://…"
                className="flex-1"
            />
            <Button type="button" variant="ghost" size="icon" onClick={onDelete} aria-label="Remove">
                <X className="size-4" />
            </Button>
        </div>
    );
}

export default function MenuEditor({ items, onChange }: Props) {
    const sensors = useSensors(
        useSensor(PointerSensor, { activationConstraint: { distance: 4 } }),
        useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }),
    );

    const ids = items.map((it, idx) => makeId(idx, it));

    function onDragEnd(event: DragEndEvent) {
        const { active, over } = event;

        if (!over || active.id === over.id) {
return;
}

        const oldIndex = ids.indexOf(String(active.id));
        const newIndex = ids.indexOf(String(over.id));

        if (oldIndex < 0 || newIndex < 0) {
return;
}

        onChange(arrayMove(items, oldIndex, newIndex));
    }

    return (
        <div className="space-y-2">
            <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={onDragEnd}>
                <SortableContext items={ids} strategy={verticalListSortingStrategy}>
                    {items.map((item, idx) => (
                        <SortableRow
                            key={ids[idx]}
                            id={ids[idx]}
                            item={item}
                            onChange={(next) => {
                                const copy = items.slice();
                                copy[idx] = next;
                                onChange(copy);
                            }}
                            onDelete={() => onChange(items.filter((_, i) => i !== idx))}
                        />
                    ))}
                </SortableContext>
            </DndContext>
            <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={() => onChange([...items, { label: '', url: '', target: '_self', children: [] }])}
            >
                <Plus className="mr-1 size-3" />
                Add link
            </Button>
        </div>
    );
}
