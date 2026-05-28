import { Head, Link, router } from '@inertiajs/react';
import { Check, MoreHorizontal, Moon, Palette, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import {
    AlertDialog,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    activate as activateRoute,
    clone as cloneRoute,
    create as createRoute,
    destroy as destroyRoute,
    edit as editRoute,
} from '@/routes/admin/themes';

type Swatches = {
    background: string | null;
    foreground: string | null;
    primary: string | null;
    accent: string | null;
    sidebar: string | null;
};

type ThemeCard = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    is_active: boolean;
    is_preset: boolean;
    mode_hint: string;
    font_family: string | null;
    swatches: Swatches;
    dark_swatches: { background: string | null; primary: string | null; sidebar: string | null };
    created_at: string | null;
};

type Props = {
    themes: ThemeCard[];
};

export default function ThemesIndex({ themes }: Props) {
    const [deleting, setDeleting] = useState<ThemeCard | null>(null);

    return (
        <>
            <Head title="Themes — Admin" />

            <div className="flex h-full flex-1 flex-col gap-6">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title="Themes"
                        description="Color tokens, fonts, and custom CSS. Activating a theme swaps the live look instantly — no rebuild."
                    />
                    <Button asChild>
                        <Link href={createRoute()}>
                            <Plus className="size-4" />
                            New theme
                        </Link>
                    </Button>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    {themes.map((theme) => (
                        <ThemeGalleryCard key={theme.id} theme={theme} onDelete={() => setDeleting(theme)} />
                    ))}
                </div>
            </div>

            <DeleteDialog theme={deleting} onClose={() => setDeleting(null)} />
        </>
    );
}

function Swatch({ color }: { color: string | null }) {
    return (
        <div
            className="h-9 flex-1 first:rounded-l-md last:rounded-r-md"
            style={{ backgroundColor: color ?? 'transparent' }}
        />
    );
}

function ThemeGalleryCard({ theme, onDelete }: { theme: ThemeCard; onDelete: () => void }) {
    const s = theme.swatches;

    return (
        <Card className="overflow-hidden py-0">
            <div className="flex w-full border-b">
                <Swatch color={s.background} />
                <Swatch color={s.sidebar} />
                <Swatch color={s.accent} />
                <Swatch color={s.primary} />
                <Swatch color={s.foreground} />
            </div>
            <CardContent className="space-y-3 p-4">
                <div className="flex items-start justify-between gap-2">
                    <div className="min-w-0">
                        <div className="flex items-center gap-2">
                            <span className="truncate font-medium">{theme.name}</span>
                            {theme.is_active ? (
                                <Badge variant="default" className="shrink-0 gap-1">
                                    <Check className="size-3" />
                                    Active
                                </Badge>
                            ) : null}
                        </div>
                        <span className="font-mono text-xs text-muted-foreground">{theme.slug}</span>
                    </div>

                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button variant="ghost" size="icon" className="size-8 shrink-0">
                                <MoreHorizontal />
                                <span className="sr-only">Open actions</span>
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            {!theme.is_active ? (
                                <DropdownMenuItem
                                    onSelect={() =>
                                        router.post(activateRoute({ theme: theme.id }).url, {}, { preserveScroll: true })
                                    }
                                >
                                    <Check className="size-4" />
                                    Activate
                                </DropdownMenuItem>
                            ) : null}
                            <DropdownMenuItem asChild>
                                <Link href={editRoute({ theme: theme.id })}>
                                    <Pencil className="size-4" />
                                    {theme.is_preset ? 'View' : 'Edit'}
                                </Link>
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                onSelect={() =>
                                    router.post(cloneRoute({ theme: theme.id }).url, {}, { preserveScroll: true })
                                }
                            >
                                <Palette className="size-4" />
                                Clone
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem
                                variant="destructive"
                                disabled={theme.is_preset || theme.is_active}
                                onSelect={() => onDelete()}
                            >
                                <Trash2 className="size-4" />
                                Delete
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>

                {theme.description ? (
                    <p className="line-clamp-2 text-xs text-muted-foreground">{theme.description}</p>
                ) : null}

                <div className="flex flex-wrap items-center gap-1.5">
                    {theme.is_preset ? <Badge variant="secondary">Preset</Badge> : null}
                    {theme.mode_hint === 'dark' ? (
                        <Badge variant="outline" className="gap-1">
                            <Moon className="size-3" />
                            Dark
                        </Badge>
                    ) : null}
                    {theme.font_family ? (
                        <Badge variant="outline" className="font-normal">
                            {theme.font_family}
                        </Badge>
                    ) : null}
                </div>
            </CardContent>
        </Card>
    );
}

function DeleteDialog({ theme, onClose }: { theme: ThemeCard | null; onClose: () => void }) {
    const open = theme !== null;

    return (
        <AlertDialog open={open} onOpenChange={(v) => !v && onClose()}>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Delete theme?</AlertDialogTitle>
                    <AlertDialogDescription>
                        <strong>{theme?.name}</strong> will be removed. This can't be undone from the UI.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <Button variant="secondary" onClick={onClose}>
                        Cancel
                    </Button>
                    {theme ? (
                        <Button
                            variant="destructive"
                            onClick={() => {
                                router.delete(destroyRoute({ theme: theme.id }).url, {
                                    onFinish: onClose,
                                    preserveScroll: true,
                                });
                            }}
                        >
                            Delete
                        </Button>
                    ) : null}
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}
