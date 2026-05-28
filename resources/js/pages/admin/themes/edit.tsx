import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Check, Palette, Save, Trash2, Upload } from 'lucide-react';
import { useMemo, useRef, useState } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Slider } from '@/components/ui/slider';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { hexToOklch, oklchToHex } from '@/lib/color';
import { cn } from '@/lib/utils';
import {
    activate as activateRoute,
    clone as cloneRoute,
    customCss as customCssRoute,
    index as themesIndex,
    store as storeRoute,
    update as updateRoute,
} from '@/routes/admin/themes';
import { destroy as fontDestroyRoute, store as fontStoreRoute } from '@/routes/admin/themes/fonts';

type Mode = 'light' | 'dark';
type TokenMap = Record<string, string>;

type SchemaToken = { key: string; label: string; type: string };
type SchemaGroup = { key: string; label: string; description: string | null; tokens: SchemaToken[] };
type Schema = {
    groups: SchemaGroup[];
    defaults: { light: TokenMap; dark: TokenMap; radius: string };
    radius: { min: number; max: number; step: number };
};

type FontFace = {
    id: number;
    family: string;
    weight: string;
    style: string;
    format: string;
    size_bytes: number;
    original_filename: string;
};

type Theme = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    is_active: boolean;
    is_preset: boolean;
    mode_hint: string;
    radius: string;
    font_family: string | null;
    tokens: { light: TokenMap; dark: TokenMap };
    custom_css: string;
    fonts: FontFace[];
    families: string[];
};

type Props = {
    theme: Theme | null;
    schema: Schema;
};

function parseRem(value: string | null | undefined, fallback: number): number {
    if (!value) {
        return fallback;
    }

    const n = parseFloat(value);

    return Number.isFinite(n) ? n : fallback;
}

export default function ThemeEdit({ theme, schema }: Props) {
    const isEdit = theme !== null;
    const readOnly = theme?.is_preset ?? false;

    const [name, setName] = useState(theme?.name ?? '');
    const [description, setDescription] = useState(theme?.description ?? '');
    const [modeHint, setModeHint] = useState<string>(theme?.mode_hint ?? 'both');
    const [radius, setRadius] = useState<number>(parseRem(theme?.radius, parseRem(schema.defaults.radius, 0.625)));
    const [fontFamily, setFontFamily] = useState<string | null>(theme?.font_family ?? null);
    const [tokens, setTokens] = useState<{ light: TokenMap; dark: TokenMap }>(() => ({
        light: { ...(theme?.tokens?.light ?? {}) },
        dark: { ...(theme?.tokens?.dark ?? {}) },
    }));

    const initialMode: Mode = theme?.mode_hint === 'dark' ? 'dark' : 'light';
    const [editMode, setEditMode] = useState<Mode>(initialMode);
    const [previewMode, setPreviewMode] = useState<Mode>(initialMode);
    const [customCss, setCustomCss] = useState(theme?.custom_css ?? '');
    const [processing, setProcessing] = useState(false);

    const setToken = (mode: Mode, key: string, value: string) =>
        setTokens((prev) => ({ ...prev, [mode]: { ...prev[mode], [key]: value } }));

    const clearToken = (mode: Mode, key: string) =>
        setTokens((prev) => {
            const next = { ...prev[mode] };
            delete next[key];

            return { ...prev, [mode]: next };
        });

    const effective = (mode: Mode, key: string): string =>
        tokens[mode][key] ?? schema.defaults[mode][key] ?? 'oklch(0.5 0 0)';

    const save = () => {
        const payload = {
            name,
            description,
            mode_hint: modeHint,
            radius: `${radius}rem`,
            font_family: fontFamily ?? '',
            tokens,
        };

        setProcessing(true);
        const opts = {
            preserveScroll: true,
            onFinish: () => setProcessing(false),
        };

        if (isEdit) {
            router.put(updateRoute({ theme: theme.id }).url, payload, opts);
        } else {
            router.post(storeRoute().url, payload, opts);
        }
    };

    const previewVars = useMemo(() => {
        const eff: TokenMap = { ...schema.defaults[previewMode], ...tokens[previewMode] };
        const style: Record<string, string> = {};

        // Set both the raw token (--primary) and Tailwind's color alias
        // (--color-primary). shadcn utilities resolve var(--color-primary), and
        // @theme declares --color-primary: var(--primary) at :root — so that
        // indirection is computed at :root from the *active* theme. Overriding
        // only --primary on this wrapper therefore wouldn't reach bg-primary;
        // setting --color-* directly scopes the preview to the edited tokens.
        for (const [key, value] of Object.entries(eff)) {
            style[key] = value;
            style[key.replace(/^--/, '--color-')] = value;
        }

        const r = `${radius}rem`;
        style['--radius'] = r;
        style['--radius-lg'] = r;
        style['--radius-md'] = `calc(${r} - 2px)`;
        style['--radius-sm'] = `calc(${r} - 4px)`;

        if (fontFamily) {
            style['--font-sans'] = `'${fontFamily}', ui-sans-serif, system-ui, sans-serif`;
        }

        return style as React.CSSProperties;
    }, [tokens, previewMode, radius, fontFamily, schema.defaults]);

    return (
        <>
            <Head title={isEdit ? `${theme?.name} — Themes` : 'New theme — Themes'} />

            <div className="mb-6 flex items-center justify-between gap-4">
                <Button variant="ghost" size="sm" asChild>
                    <Link href={themesIndex()}>
                        <ArrowLeft className="size-4" />
                        Back to themes
                    </Link>
                </Button>
                <div className="flex items-center gap-2">
                    {isEdit && !theme.is_active ? (
                        <Button
                            variant="outline"
                            onClick={() => router.post(activateRoute({ theme: theme.id }).url, {}, { preserveScroll: true })}
                        >
                            <Check className="size-4" />
                            Activate
                        </Button>
                    ) : null}
                    {readOnly ? (
                        <Button onClick={() => router.post(cloneRoute({ theme: theme!.id }).url, {}, { preserveScroll: true })}>
                            <Palette className="size-4" />
                            Clone to edit
                        </Button>
                    ) : (
                        <Button onClick={save} disabled={processing || name.trim() === ''}>
                            <Save className="size-4" />
                            {isEdit ? 'Save theme' : 'Create theme'}
                        </Button>
                    )}
                </div>
            </div>

            <Heading
                title={isEdit ? theme!.name : 'New theme'}
                description={
                    isEdit
                        ? `Slug: ${theme!.slug}${theme!.is_active ? ' · currently active' : ''}`
                        : 'Define colors, shape, and typography. Save, then activate it from the gallery.'
                }
            />

            {readOnly ? (
                <div className="mb-4 rounded-md border border-dashed bg-muted/40 px-4 py-3 text-sm text-muted-foreground">
                    This is a built-in <strong>preset</strong> — it's read-only. Clone it to make an editable copy.
                </div>
            ) : null}

            <div className="grid gap-6 lg:grid-cols-5">
                <div className="space-y-6 lg:col-span-3">
                    <Card>
                        <CardHeader>
                            <CardTitle>Details</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-5">
                            <div className="grid gap-2">
                                <Label htmlFor="name">Name</Label>
                                <Input id="name" value={name} onChange={(e) => setName(e.target.value)} disabled={readOnly} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="description">Description</Label>
                                <Textarea
                                    id="description"
                                    rows={2}
                                    value={description ?? ''}
                                    onChange={(e) => setDescription(e.target.value)}
                                    disabled={readOnly}
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label>Designed for</Label>
                                <div className="flex gap-2">
                                    {(['light', 'dark', 'both'] as const).map((m) => (
                                        <Button
                                            key={m}
                                            type="button"
                                            size="sm"
                                            variant={modeHint === m ? 'default' : 'outline'}
                                            disabled={readOnly}
                                            onClick={() => setModeHint(m)}
                                            className="capitalize"
                                        >
                                            {m}
                                        </Button>
                                    ))}
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Tabs defaultValue="colors">
                        <TabsList>
                            <TabsTrigger value="colors">Colors</TabsTrigger>
                            <TabsTrigger value="shape">Shape</TabsTrigger>
                            <TabsTrigger value="type">Typography</TabsTrigger>
                            <TabsTrigger value="css">Custom CSS</TabsTrigger>
                        </TabsList>

                        <TabsContent value="colors" className="mt-4">
                            <Card>
                                <CardHeader>
                                    <div className="flex items-center justify-between gap-4">
                                        <div>
                                            <CardTitle>Color tokens</CardTitle>
                                            <CardDescription>
                                                Pick a hex color (stored as oklch). Empty = inherits the default.
                                            </CardDescription>
                                        </div>
                                        <ModeToggle value={editMode} onChange={setEditMode} />
                                    </div>
                                </CardHeader>
                                <CardContent className="space-y-6">
                                    {schema.groups.map((group) => (
                                        <div key={group.key} className="space-y-3">
                                            <h3 className="text-sm font-medium text-muted-foreground">{group.label}</h3>
                                            <div className="grid gap-2 sm:grid-cols-2">
                                                {group.tokens.map((token) => (
                                                    <ColorRow
                                                        key={token.key}
                                                        label={token.label}
                                                        tokenKey={token.key}
                                                        value={effective(editMode, token.key)}
                                                        overridden={tokens[editMode][token.key] !== undefined}
                                                        readOnly={readOnly}
                                                        onChange={(v) => setToken(editMode, token.key, v)}
                                                        onReset={() => clearToken(editMode, token.key)}
                                                    />
                                                ))}
                                            </div>
                                        </div>
                                    ))}
                                </CardContent>
                            </Card>
                        </TabsContent>

                        <TabsContent value="shape" className="mt-4">
                            <Card>
                                <CardHeader>
                                    <CardTitle>Corner radius</CardTitle>
                                    <CardDescription>Controls the global --radius token.</CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="flex items-center gap-4">
                                        <Slider
                                            value={[radius]}
                                            min={schema.radius.min}
                                            max={schema.radius.max}
                                            step={schema.radius.step}
                                            disabled={readOnly}
                                            onValueChange={([v]) => setRadius(v)}
                                            className="max-w-sm"
                                        />
                                        <span className="font-mono text-sm tabular-nums">{radius.toFixed(3)}rem</span>
                                    </div>
                                </CardContent>
                            </Card>
                        </TabsContent>

                        <TabsContent value="type" className="mt-4">
                            <TypographyTab
                                theme={theme}
                                fontFamily={fontFamily}
                                onPickFamily={setFontFamily}
                                readOnly={readOnly}
                            />
                        </TabsContent>

                        <TabsContent value="css" className="mt-4">
                            <CustomCssTab
                                theme={theme}
                                value={customCss}
                                onChange={setCustomCss}
                                readOnly={readOnly}
                            />
                        </TabsContent>
                    </Tabs>
                </div>

                <div className="lg:col-span-2">
                    <div className="sticky top-4 space-y-3">
                        <div className="flex items-center justify-between">
                            <h3 className="text-sm font-medium">Live preview</h3>
                            <ModeToggle value={previewMode} onChange={setPreviewMode} />
                        </div>
                        <ThemePreview style={previewVars} mode={previewMode} fontFamily={fontFamily} />
                    </div>
                </div>
            </div>
        </>
    );
}

function ModeToggle({ value, onChange }: { value: Mode; onChange: (m: Mode) => void }) {
    return (
        <div className="inline-flex rounded-md border p-0.5">
            {(['light', 'dark'] as const).map((m) => (
                <button
                    key={m}
                    type="button"
                    onClick={() => onChange(m)}
                    className={cn(
                        'rounded px-3 py-1 text-xs font-medium capitalize transition-colors',
                        value === m ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:text-foreground',
                    )}
                >
                    {m}
                </button>
            ))}
        </div>
    );
}

function ColorRow({
    label,
    tokenKey,
    value,
    overridden,
    readOnly,
    onChange,
    onReset,
}: {
    label: string;
    tokenKey: string;
    value: string;
    overridden: boolean;
    readOnly: boolean;
    onChange: (value: string) => void;
    onReset: () => void;
}) {
    const hex = oklchToHex(value, '#888888');

    return (
        <div className="flex items-center gap-2 rounded-md border bg-muted/20 p-2">
            <label className="relative size-8 shrink-0 cursor-pointer overflow-hidden rounded-md border">
                <span className="block size-full" style={{ backgroundColor: value }} />
                <input
                    type="color"
                    value={hex}
                    disabled={readOnly}
                    onChange={(e) => {
                        const oklch = hexToOklch(e.target.value);

                        if (oklch) {
                            onChange(oklch);
                        }
                    }}
                    className="absolute inset-0 cursor-pointer opacity-0"
                    aria-label={`${label} color`}
                />
            </label>
            <div className="min-w-0 flex-1">
                <div className="truncate text-xs font-medium">{label}</div>
                <Input
                    value={value}
                    disabled={readOnly}
                    onChange={(e) => onChange(e.target.value)}
                    className="h-7 px-2 font-mono text-[11px]"
                    spellCheck={false}
                />
            </div>
            {overridden && !readOnly ? (
                <Button variant="ghost" size="icon" className="size-7 shrink-0" onClick={onReset} title="Reset to default">
                    <Trash2 className="size-3.5" />
                </Button>
            ) : (
                <span className="w-7 shrink-0 text-center text-[10px] text-muted-foreground" title={`Inherited · ${tokenKey}`}>
                    def
                </span>
            )}
        </div>
    );
}

function ThemePreview({
    style,
    mode,
    fontFamily,
}: {
    style: React.CSSProperties;
    mode: Mode;
    fontFamily: string | null;
}) {
    return (
        <div className={cn('overflow-hidden rounded-xl border shadow-sm', mode === 'dark' && 'dark')} style={style}>
            <div className="flex bg-background text-foreground" style={fontFamily ? { fontFamily: `'${fontFamily}', sans-serif` } : undefined}>
                <div className="w-1/3 space-y-2 bg-sidebar p-3 text-sidebar-foreground">
                    <div className="text-xs font-semibold">Acme Inc</div>
                    <div className="rounded-md bg-sidebar-primary px-2 py-1 text-[11px] text-sidebar-primary-foreground">
                        Dashboard
                    </div>
                    <div className="rounded-md bg-sidebar-accent px-2 py-1 text-[11px] text-sidebar-accent-foreground">
                        Members
                    </div>
                    <div className="px-2 py-1 text-[11px]">Settings</div>
                </div>
                <div className="flex-1 space-y-3 p-3">
                    <div className="flex items-center justify-between">
                        <div className="text-sm font-semibold">Overview</div>
                        <button className="rounded-md bg-primary px-2.5 py-1 text-[11px] font-medium text-primary-foreground">
                            New
                        </button>
                    </div>
                    <div className="rounded-lg border bg-card p-3 text-card-foreground">
                        <div className="text-[11px] text-muted-foreground">Revenue</div>
                        <div className="text-lg font-semibold">$12,480</div>
                        <div className="mt-2 flex items-end gap-1">
                            {['--chart-1', '--chart-2', '--chart-3', '--chart-4', '--chart-5'].map((c, i) => (
                                <div
                                    key={c}
                                    className="w-full rounded-sm"
                                    style={{ backgroundColor: `var(${c})`, height: `${16 + i * 6}px` }}
                                />
                            ))}
                        </div>
                    </div>
                    <div className="flex flex-wrap gap-1.5">
                        <span className="rounded-md bg-secondary px-2 py-0.5 text-[10px] text-secondary-foreground">Secondary</span>
                        <span className="rounded-md bg-accent px-2 py-0.5 text-[10px] text-accent-foreground">Accent</span>
                        <span className="rounded-md bg-muted px-2 py-0.5 text-[10px] text-muted-foreground">Muted</span>
                        <span className="rounded-md bg-destructive px-2 py-0.5 text-[10px] text-white">Danger</span>
                    </div>
                    <Input className="h-7 text-[11px]" placeholder="Search…" readOnly />
                </div>
            </div>
        </div>
    );
}

function TypographyTab({
    theme,
    fontFamily,
    onPickFamily,
    readOnly,
}: {
    theme: Theme | null;
    fontFamily: string | null;
    onPickFamily: (family: string | null) => void;
    readOnly: boolean;
}) {
    const fileRef = useRef<HTMLInputElement>(null);
    const [uploading, setUploading] = useState(false);

    if (theme === null) {
        return (
            <Card>
                <CardHeader>
                    <CardTitle>Typography</CardTitle>
                    <CardDescription>Save the theme first, then upload font archives here.</CardDescription>
                </CardHeader>
            </Card>
        );
    }

    const families = theme.families;

    const upload = () => {
        const file = fileRef.current?.files?.[0];

        if (!file) {
            return;
        }

        setUploading(true);
        router.post(
            fontStoreRoute({ theme: theme.id }).url,
            { archive: file },
            {
                forceFormData: true,
                preserveScroll: true,
                onFinish: () => {
                    setUploading(false);

                    if (fileRef.current) {
                        fileRef.current.value = '';
                    }
                },
            },
        );
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle>Typography</CardTitle>
                <CardDescription>
                    Upload a Google-Fonts ZIP (self-hosted). Pick a family to set --font-sans. Save the theme to apply.
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-5">
                {!readOnly ? (
                    <div className="flex items-center gap-2">
                        <Input ref={fileRef} type="file" accept=".zip" className="max-w-xs" />
                        <Button type="button" variant="outline" onClick={upload} disabled={uploading}>
                            <Upload className="size-4" />
                            {uploading ? 'Uploading…' : 'Upload ZIP'}
                        </Button>
                    </div>
                ) : null}

                <div className="space-y-2">
                    <Label>Default family</Label>
                    <div className="flex flex-wrap gap-2">
                        <Button
                            type="button"
                            size="sm"
                            variant={fontFamily === null ? 'default' : 'outline'}
                            disabled={readOnly}
                            onClick={() => onPickFamily(null)}
                        >
                            System default
                        </Button>
                        {families.map((family) => (
                            <Button
                                key={family}
                                type="button"
                                size="sm"
                                variant={fontFamily === family ? 'default' : 'outline'}
                                disabled={readOnly}
                                onClick={() => onPickFamily(family)}
                                style={{ fontFamily: `'${family}', sans-serif` }}
                            >
                                {family}
                            </Button>
                        ))}
                    </div>
                </div>

                {theme.fonts.length > 0 ? (
                    <div className="space-y-2">
                        <Separator />
                        <Label>Uploaded faces ({theme.fonts.length})</Label>
                        <div className="space-y-1">
                            {theme.fonts.map((font) => (
                                <div
                                    key={font.id}
                                    className="flex items-center justify-between gap-2 rounded-md border bg-muted/20 px-3 py-1.5 text-xs"
                                >
                                    <span className="truncate">
                                        <span className="font-medium">{font.family}</span>{' '}
                                        <span className="text-muted-foreground">
                                            {font.weight} {font.style} · {font.format}
                                        </span>
                                    </span>
                                    {!readOnly ? (
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            className="size-6 shrink-0"
                                            onClick={() =>
                                                router.delete(fontDestroyRoute({ theme: theme.id, font: font.id }).url, {
                                                    preserveScroll: true,
                                                })
                                            }
                                        >
                                            <Trash2 className="size-3.5" />
                                        </Button>
                                    ) : null}
                                </div>
                            ))}
                        </div>
                    </div>
                ) : null}
            </CardContent>
        </Card>
    );
}

function CustomCssTab({
    theme,
    value,
    onChange,
    readOnly,
}: {
    theme: Theme | null;
    value: string;
    onChange: (css: string) => void;
    readOnly: boolean;
}) {
    const fileRef = useRef<HTMLInputElement>(null);
    const [saving, setSaving] = useState(false);

    if (theme === null) {
        return (
            <Card>
                <CardHeader>
                    <CardTitle>Custom CSS</CardTitle>
                    <CardDescription>Save the theme first, then add an advanced CSS override here.</CardDescription>
                </CardHeader>
            </Card>
        );
    }

    const loadFile = () => {
        const file = fileRef.current?.files?.[0];

        if (!file) {
            return;
        }

        file.text().then((text) => onChange(text));
    };

    const saveCss = () => {
        setSaving(true);
        router.put(
            customCssRoute({ theme: theme.id }).url,
            { css: value },
            { preserveScroll: true, onFinish: () => setSaving(false) },
        );
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle>Custom CSS</CardTitle>
                <CardDescription>
                    Appended last to the compiled stylesheet, so it overrides tokens. Remote @import is stripped.
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
                <Textarea
                    value={value}
                    onChange={(e) => onChange(e.target.value)}
                    disabled={readOnly}
                    rows={12}
                    spellCheck={false}
                    className="font-mono text-xs"
                    placeholder={`:root {\n  --primary: oklch(0.6 0.2 250);\n}`}
                />
                {!readOnly ? (
                    <div className="flex items-center gap-2">
                        <input ref={fileRef} type="file" accept=".css" className="hidden" onChange={loadFile} />
                        <Button type="button" variant="outline" size="sm" onClick={() => fileRef.current?.click()}>
                            <Upload className="size-4" />
                            Load .css file
                        </Button>
                        <Button type="button" size="sm" onClick={saveCss} disabled={saving}>
                            <Save className="size-4" />
                            {saving ? 'Saving…' : 'Save CSS'}
                        </Button>
                    </div>
                ) : null}
            </CardContent>
        </Card>
    );
}
