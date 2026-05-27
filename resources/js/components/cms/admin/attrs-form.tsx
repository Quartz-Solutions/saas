import { Plus, X } from 'lucide-react';
import type { ChangeEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';

/**
 * Generic attribute editor for a block. Renders an input per attribute
 * based on the attribute *key* (since attr keys repeat across blocks)
 * and the default-attrs shape. Specialised handling for:
 *   - `items` (stats repeater)
 *   - `*_ids` / `*_slugs` arrays (comma-separated for now; M5/M4 will
 *     replace with proper pickers)
 *   - select fields with known enum values
 *
 * The component is intentionally schema-light — the canonical validation
 * lives on the server via the BlockTypeRegistry rules.
 */

type Attrs = Record<string, unknown>;

type Props = {
    attrs: Attrs;
    onChange: (next: Attrs) => void;
};

const ENUMS: Record<string, string[]> = {
    layout: ['centered', 'split-left', 'split-right', 'single', 'carousel', 'grid', 'contained', 'full', 'narrow'],
    image_side: ['left', 'right'],
    align: ['left', 'center', 'right'],
    provider: ['youtube', 'vimeo', 'mux', 'url'],
    variant: ['info', 'success', 'warning'],
    style: ['line', 'dotted', 'space'],
    aspect: ['16:9', '4:3', '1:1', '21:9'],
};

const LONG_TEXT_KEYS = new Set([
    'subtitle',
    'body',
    'description',
    'message',
    'caption',
    'html',
    'code',
    'success_message',
    'copy',
    'answer_html',
]);

const NUMERIC_KEYS = new Set(['columns', 'rating', 'sort_order']);

const ID_KEYS_RE = /(_id|_media_id)$/;

function labelize(key: string): string {
    return key
        .replace(/_/g, ' ')
        .replace(/\b\w/g, (c) => c.toUpperCase());
}

function isStringArray(v: unknown): v is string[] {
    return Array.isArray(v) && v.every((x) => typeof x === 'string');
}

function isNumberArray(v: unknown): v is number[] {
    return Array.isArray(v) && v.every((x) => typeof x === 'number');
}

function isStatsItem(v: unknown): v is { label: string; value: string; suffix?: string } {
    return !!v && typeof v === 'object' && 'label' in v && 'value' in v;
}

export default function AttrsForm({ attrs, onChange }: Props) {
    const keys = Object.keys(attrs);

    if (keys.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">No editable attributes on this block.</p>
        );
    }

    function set(key: string, value: unknown) {
        onChange({ ...attrs, [key]: value });
    }

    return (
        <div className="space-y-4">
            {keys.map((key) => {
                const value = attrs[key];

                // Stats items repeater
                if (key === 'items' && Array.isArray(value)) {
                    const items = value.filter(isStatsItem) as Array<{ label: string; value: string; suffix?: string }>;

                    return (
                        <div key={key} className="space-y-2 rounded-md border border-border/60 p-3">
                            <Label className="text-xs uppercase tracking-wide">Items</Label>
                            {items.map((item, idx) => (
                                <div key={idx} className="flex items-center gap-2">
                                    <Input
                                        value={item.label}
                                        placeholder="Label"
                                        onChange={(e) => {
                                            const next = [...items];
                                            next[idx] = { ...item, label: e.target.value };
                                            set('items', next);
                                        }}
                                    />
                                    <Input
                                        value={item.value}
                                        placeholder="Value"
                                        onChange={(e) => {
                                            const next = [...items];
                                            next[idx] = { ...item, value: e.target.value };
                                            set('items', next);
                                        }}
                                    />
                                    <Input
                                        value={item.suffix ?? ''}
                                        placeholder="Suffix"
                                        className="w-24"
                                        onChange={(e) => {
                                            const next = [...items];
                                            next[idx] = { ...item, suffix: e.target.value };
                                            set('items', next);
                                        }}
                                    />
                                    <Button
                                        type="button"
                                        size="icon"
                                        variant="ghost"
                                        onClick={() => {
                                            const next = items.filter((_, i) => i !== idx);
                                            set('items', next);
                                        }}
                                    >
                                        <X className="size-4" />
                                    </Button>
                                </div>
                            ))}
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                onClick={() => set('items', [...items, { label: '', value: '', suffix: '' }])}
                            >
                                <Plus className="mr-1 size-3" />
                                Add item
                            </Button>
                        </div>
                    );
                }

                // Number arrays (feature_ids, testimonial_ids) — comma-separated
                if ((key.endsWith('_ids') || key.endsWith('_slugs')) && (isNumberArray(value) || isStringArray(value) || (Array.isArray(value) && value.length === 0))) {
                    return (
                        <div key={key} className="space-y-1">
                            <Label htmlFor={key}>{labelize(key)}</Label>
                            <Input
                                id={key}
                                value={(value as Array<string | number>).join(',')}
                                placeholder="Comma-separated"
                                onChange={(e) => {
                                    const raw = e.target.value
                                        .split(',')
                                        .map((s) => s.trim())
                                        .filter(Boolean);

                                    if (key.endsWith('_ids')) {
                                        set(key, raw.map((x) => Number(x)).filter((n) => Number.isFinite(n)));
                                    } else {
                                        set(key, raw);
                                    }
                                }}
                            />
                            <p className="text-xs text-muted-foreground">
                                {key.endsWith('_ids') ? 'IDs from the matching collection (M5 wires a picker).' : 'Slugs, comma-separated.'}
                            </p>
                        </div>
                    );
                }

                // Booleans
                if (typeof value === 'boolean') {
                    return (
                        <div key={key} className="flex items-center justify-between gap-3">
                            <Label htmlFor={key}>{labelize(key)}</Label>
                            <input
                                id={key}
                                type="checkbox"
                                checked={value}
                                onChange={(e) => set(key, e.target.checked)}
                                className="size-4 rounded border-border"
                            />
                        </div>
                    );
                }

                // Numbers
                if (typeof value === 'number' || NUMERIC_KEYS.has(key)) {
                    return (
                        <div key={key} className="space-y-1">
                            <Label htmlFor={key}>{labelize(key)}</Label>
                            <Input
                                id={key}
                                type="number"
                                value={(value as number | null) ?? ''}
                                onChange={(e: ChangeEvent<HTMLInputElement>) => {
                                    const n = e.target.value === '' ? null : Number(e.target.value);
                                    set(key, n);
                                }}
                                className={cn(ID_KEYS_RE.test(key) && 'font-mono')}
                            />
                            {ID_KEYS_RE.test(key) && (
                                <p className="text-xs text-muted-foreground">
                                    Media library ID (M4 wires a picker).
                                </p>
                            )}
                        </div>
                    );
                }

                // Null ID-style attrs (e.g. image_media_id null)
                if (value === null && ID_KEYS_RE.test(key)) {
                    return (
                        <div key={key} className="space-y-1">
                            <Label htmlFor={key}>{labelize(key)}</Label>
                            <Input
                                id={key}
                                type="number"
                                value=""
                                placeholder="Media ID"
                                onChange={(e) => {
                                    const n = e.target.value === '' ? null : Number(e.target.value);
                                    set(key, n);
                                }}
                            />
                            <p className="text-xs text-muted-foreground">Media library ID (M4 wires a picker).</p>
                        </div>
                    );
                }

                // Enum selects
                if (ENUMS[key]) {
                    return (
                        <div key={key} className="space-y-1">
                            <Label htmlFor={key}>{labelize(key)}</Label>
                            <Select value={(value as string) ?? ''} onValueChange={(v) => set(key, v)}>
                                <SelectTrigger id={key} className="w-full">
                                    <SelectValue placeholder="Select…" />
                                </SelectTrigger>
                                <SelectContent>
                                    {ENUMS[key].map((opt) => (
                                        <SelectItem key={opt} value={opt}>
                                            {opt}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    );
                }

                // Long text → textarea
                if (LONG_TEXT_KEYS.has(key) || (typeof value === 'string' && value.length > 120)) {
                    return (
                        <div key={key} className="space-y-1">
                            <Label htmlFor={key}>{labelize(key)}</Label>
                            <Textarea
                                id={key}
                                value={(value as string) ?? ''}
                                rows={key === 'code' || key === 'html' || key === 'answer_html' ? 8 : 3}
                                onChange={(e) => set(key, e.target.value)}
                                className={cn(['code', 'html', 'answer_html'].includes(key) && 'font-mono text-sm')}
                            />
                        </div>
                    );
                }

                // Default — text input
                return (
                    <div key={key} className="space-y-1">
                        <Label htmlFor={key}>{labelize(key)}</Label>
                        <Input
                            id={key}
                            value={(value as string) ?? ''}
                            onChange={(e) => set(key, e.target.value)}
                        />
                    </div>
                );
            })}
        </div>
    );
}
