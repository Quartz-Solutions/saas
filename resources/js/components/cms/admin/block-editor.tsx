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
    verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import BlockCard from '@/components/cms/admin/block-card';
import BlockPicker from '@/components/cms/admin/block-picker';
import type { BlockCatalogEntry } from '@/components/cms/admin/block-picker';
import type { Block } from '@/components/cms/types';

type Props = {
    blocks: Block[];
    catalog: BlockCatalogEntry[];
    onChange: (next: Block[]) => void;
};

function labelFor(catalog: BlockCatalogEntry[], type: string): { label: string; icon: string } {
    const entry = catalog.find((c) => c.id === type);

    return { label: entry?.label ?? type, icon: entry?.icon ?? 'box' };
}

export default function BlockEditor({ blocks, catalog, onChange }: Props) {
    const sensors = useSensors(
        useSensor(PointerSensor, { activationConstraint: { distance: 4 } }),
        useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }),
    );

    function onDragEnd(event: DragEndEvent) {
        const { active, over } = event;

        if (!over || active.id === over.id) {
return;
}

        const oldIndex = blocks.findIndex((b) => b.id === active.id);
        const newIndex = blocks.findIndex((b) => b.id === over.id);

        if (oldIndex < 0 || newIndex < 0) {
return;
}

        onChange(arrayMove(blocks, oldIndex, newIndex));
    }

    function update(index: number, next: Block) {
        const copy = blocks.slice();
        copy[index] = next;
        onChange(copy);
    }

    function remove(index: number) {
        onChange(blocks.filter((_, i) => i !== index));
    }

    function move(index: number, delta: number) {
        const newIndex = index + delta;

        if (newIndex < 0 || newIndex >= blocks.length) {
return;
}

        onChange(arrayMove(blocks, index, newIndex));
    }

    function insert(block: Block, atIndex?: number) {
        if (atIndex === undefined) {
            onChange([...blocks, block]);
        } else {
            const copy = blocks.slice();
            copy.splice(atIndex, 0, block);
            onChange(copy);
        }
    }

    return (
        <div className="space-y-3" data-test="block-editor">
            <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={onDragEnd}>
                <SortableContext items={blocks.map((b) => b.id)} strategy={verticalListSortingStrategy}>
                    <div className="space-y-2">
                        {blocks.length === 0 && (
                            <div className="rounded-md border border-dashed border-border/60 bg-muted/30 px-4 py-10 text-center text-sm text-muted-foreground">
                                No blocks yet. Click "Add block" below to insert one.
                            </div>
                        )}
                        {blocks.map((block, idx) => {
                            const { label, icon } = labelFor(catalog, block.type);

                            return (
                                <BlockCard
                                    key={block.id}
                                    block={block}
                                    label={label}
                                    icon={icon}
                                    isFirst={idx === 0}
                                    isLast={idx === blocks.length - 1}
                                    onChange={(next) => update(idx, next)}
                                    onDelete={() => remove(idx)}
                                    onMoveUp={() => move(idx, -1)}
                                    onMoveDown={() => move(idx, 1)}
                                />
                            );
                        })}
                    </div>
                </SortableContext>
            </DndContext>

            <div className="flex justify-center pt-2">
                <BlockPicker catalog={catalog} onInsert={(b) => insert(b)} />
            </div>
        </div>
    );
}
