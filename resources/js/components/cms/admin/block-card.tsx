import { useSortable } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { ChevronDown, ChevronRight, ChevronUp, GripVertical, Trash2 } from 'lucide-react';
import { useState } from 'react';
import AttrsForm from '@/components/cms/admin/attrs-form';
import type { Block } from '@/components/cms/types';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

type Props = {
    block: Block;
    label: string;
    icon: string;
    onChange: (next: Block) => void;
    onDelete: () => void;
    onMoveUp: () => void;
    onMoveDown: () => void;
    isFirst: boolean;
    isLast: boolean;
};

export default function BlockCard({
    block,
    label,
    onChange,
    onDelete,
    onMoveUp,
    onMoveDown,
    isFirst,
    isLast,
}: Props) {
    const [open, setOpen] = useState(false);
    const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({ id: block.id });

    const style = {
        transform: CSS.Transform.toString(transform),
        transition,
    };

    return (
        <div
            ref={setNodeRef}
            style={style}
            className={cn(
                'group rounded-md border border-border/60 bg-card transition-shadow',
                isDragging && 'shadow-lg ring-1 ring-primary/40',
            )}
            data-test={`block-card-${block.type}`}
        >
            <div className="flex items-center gap-2 px-3 py-2">
                <button
                    type="button"
                    className="cursor-grab touch-none rounded p-1 text-muted-foreground hover:bg-muted active:cursor-grabbing"
                    aria-label="Drag to reorder"
                    {...attributes}
                    {...listeners}
                >
                    <GripVertical className="size-4" />
                </button>

                <button
                    type="button"
                    className="flex flex-1 items-center gap-2 text-left"
                    onClick={() => setOpen((o) => !o)}
                >
                    {open ? <ChevronDown className="size-4 text-muted-foreground" /> : <ChevronRight className="size-4 text-muted-foreground" />}
                    <span className="font-medium">{label}</span>
                    <span className="font-mono text-xs text-muted-foreground">{block.type}</span>
                </button>

                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className="size-7"
                    onClick={onMoveUp}
                    disabled={isFirst}
                    aria-label="Move up"
                >
                    <ChevronUp className="size-4" />
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className="size-7"
                    onClick={onMoveDown}
                    disabled={isLast}
                    aria-label="Move down"
                >
                    <ChevronDown className="size-4" />
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className="size-7 text-destructive hover:bg-destructive/10"
                    onClick={onDelete}
                    aria-label="Delete block"
                >
                    <Trash2 className="size-4" />
                </Button>
            </div>

            {open && (
                <div className="border-t border-border/60 px-4 py-3">
                    <AttrsForm
                        attrs={block.attrs}
                        onChange={(next) => onChange({ ...block, attrs: next })}
                    />
                </div>
            )}
        </div>
    );
}
