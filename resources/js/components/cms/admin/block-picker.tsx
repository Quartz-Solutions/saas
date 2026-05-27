import { Plus } from 'lucide-react';
import { useState } from 'react';
import type { Block } from '@/components/cms/types';
import { Button } from '@/components/ui/button';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { ulid } from '@/lib/cms-ulid';

export type BlockCatalogEntry = {
    id: string;
    label: string;
    group: string;
    icon: string;
    description: string;
    defaultAttrs: Record<string, unknown>;
};

type Props = {
    catalog: BlockCatalogEntry[];
    onInsert: (block: Block) => void;
    triggerLabel?: string;
    variant?: 'default' | 'outline' | 'ghost';
};

export default function BlockPicker({ catalog, onInsert, triggerLabel = 'Add block', variant = 'outline' }: Props) {
    const [open, setOpen] = useState(false);
    const grouped: Record<string, BlockCatalogEntry[]> = {};
    catalog.forEach((c) => {
        (grouped[c.group] ??= []).push(c);
    });

    function pick(entry: BlockCatalogEntry) {
        onInsert({
            id: ulid(),
            type: entry.id,
            attrs: JSON.parse(JSON.stringify(entry.defaultAttrs ?? {})),
        });
        setOpen(false);
    }

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button type="button" variant={variant} size="sm">
                    <Plus className="mr-1 size-4" />
                    {triggerLabel}
                </Button>
            </DialogTrigger>
            <DialogContent className="max-w-xl p-0">
                <DialogHeader className="px-4 pb-2 pt-4">
                    <DialogTitle>Insert block</DialogTitle>
                </DialogHeader>
                <Command>
                    <CommandInput placeholder="Search blocks…" autoFocus />
                    <CommandList className="max-h-[60vh]">
                        <CommandEmpty>No blocks found.</CommandEmpty>
                        {Object.entries(grouped).map(([group, entries]) => (
                            <CommandGroup key={group} heading={group}>
                                {entries.map((entry) => (
                                    <CommandItem
                                        key={entry.id}
                                        value={`${entry.label} ${entry.description}`}
                                        onSelect={() => pick(entry)}
                                        data-test={`block-picker-${entry.id}`}
                                    >
                                        <div className="flex flex-col">
                                            <span className="font-medium">{entry.label}</span>
                                            {entry.description && (
                                                <span className="text-xs text-muted-foreground">{entry.description}</span>
                                            )}
                                        </div>
                                    </CommandItem>
                                ))}
                            </CommandGroup>
                        ))}
                    </CommandList>
                </Command>
            </DialogContent>
        </Dialog>
    );
}
