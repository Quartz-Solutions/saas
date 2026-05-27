import { Plus, X } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export type FooterColumn = {
    title: string;
    items: Array<{ label: string; url: string }>;
};

type Props = {
    columns: FooterColumn[];
    onChange: (next: FooterColumn[]) => void;
};

export default function FooterColumnsEditor({ columns, onChange }: Props) {
    function updateColumn(idx: number, next: FooterColumn) {
        const copy = columns.slice();
        copy[idx] = next;
        onChange(copy);
    }

    return (
        <div className="space-y-4">
            {columns.map((column, ci) => (
                <div key={ci} className="space-y-2 rounded-md border border-border/60 p-3">
                    <div className="flex items-center gap-2">
                        <Label className="flex-1 text-xs uppercase tracking-wide">Column title</Label>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            onClick={() => onChange(columns.filter((_, i) => i !== ci))}
                            aria-label="Remove column"
                        >
                            <X className="size-4" />
                        </Button>
                    </div>
                    <Input
                        value={column.title}
                        onChange={(e) => updateColumn(ci, { ...column, title: e.target.value })}
                        placeholder="Section title"
                    />
                    <div className="space-y-2">
                        {column.items.map((item, ii) => (
                            <div key={ii} className="flex items-center gap-2">
                                <Input
                                    value={item.label}
                                    onChange={(e) => {
                                        const next = column.items.slice();
                                        next[ii] = { ...item, label: e.target.value };
                                        updateColumn(ci, { ...column, items: next });
                                    }}
                                    placeholder="Label"
                                />
                                <Input
                                    value={item.url}
                                    onChange={(e) => {
                                        const next = column.items.slice();
                                        next[ii] = { ...item, url: e.target.value };
                                        updateColumn(ci, { ...column, items: next });
                                    }}
                                    placeholder="/path"
                                />
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    onClick={() => {
                                        updateColumn(ci, {
                                            ...column,
                                            items: column.items.filter((_, i) => i !== ii),
                                        });
                                    }}
                                    aria-label="Remove link"
                                >
                                    <X className="size-4" />
                                </Button>
                            </div>
                        ))}
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => {
                                updateColumn(ci, {
                                    ...column,
                                    items: [...column.items, { label: '', url: '' }],
                                });
                            }}
                        >
                            <Plus className="mr-1 size-3" />
                            Add link
                        </Button>
                    </div>
                </div>
            ))}
            <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={() => onChange([...columns, { title: '', items: [] }])}
            >
                <Plus className="mr-1 size-3" />
                Add column
            </Button>
        </div>
    );
}
