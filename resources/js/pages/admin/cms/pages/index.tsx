import { Head, Link, router } from '@inertiajs/react';
import { Archive, MoreHorizontal, Plus, RotateCcw } from 'lucide-react';
import { useState } from 'react';
import {
    DataTable,
} from '@/components/data-table/data-table';
import type {
    DataTableColumn,
    DataTableFilter,
    PaginationData,
} from '@/components/data-table/data-table';
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
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { formatDateTime } from '@/lib/utils';
import {
    create as pagesCreate,
    destroy as pagesDestroy,
    edit as pagesEdit,
    index as pagesIndex,
    restore as pagesRestore,
} from '@/routes/admin/cms/pages';

type PageRow = {
    id: number;
    slug: string;
    title: string;
    locale: string;
    path: string | null;
    route_name: string | null;
    status: 'draft' | 'published' | 'archived';
    template: string;
    no_index: boolean;
    published_at: string | null;
    updated_at: string | null;
    deleted_at: string | null;
};

type Props = {
    pages: {
        data: PageRow[];
        meta: PaginationData;
    };
    tableState: {
        search: string;
        filters: Record<string, string>;
        sort: { column: string; direction: 'asc' | 'desc' };
    };
};

const STATUS_VARIANT: Record<PageRow['status'], 'default' | 'secondary' | 'outline'> = {
    published: 'default',
    draft: 'secondary',
    archived: 'outline',
};

export default function CmsPagesIndex({ pages, tableState }: Props) {
    const [archiving, setArchiving] = useState<PageRow | null>(null);

    const reload = (params: {
        search?: string;
        filters?: Record<string, string>;
        sort?: { column: string; direction: 'asc' | 'desc' };
        page?: number;
    }) => {
        const data: Record<string, string | number | Record<string, string>> = {};

        if (params.search !== undefined && params.search !== '') {
data.search = params.search;
}

        if (params.filters && Object.keys(params.filters).length > 0) {
data.filter = params.filters;
}

        if (params.sort) {
            data.sort = params.sort.column;
            data.direction = params.sort.direction;
        }

        if (params.page && params.page > 1) {
data.page = params.page;
}

        router.get(pagesIndex().url, data, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['pages', 'tableState'],
        });
    };

    const columns: DataTableColumn<PageRow>[] = [
        {
            key: 'title',
            header: 'Title',
            sortable: true,
            render: (row) => (
                <div className="flex flex-col">
                    <Link href={pagesEdit({ cms_page: row.id })} className="font-medium hover:underline">
                        {row.title}
                    </Link>
                    <span className="font-mono text-xs text-muted-foreground">
                        /{row.path ?? row.slug}
                    </span>
                </div>
            ),
        },
        {
            key: 'status',
            header: 'Status',
            sortable: true,
            render: (row) =>
                row.deleted_at ? (
                    <Badge variant="outline" className="text-muted-foreground">Archived</Badge>
                ) : (
                    <Badge variant={STATUS_VARIANT[row.status]} className="capitalize">{row.status}</Badge>
                ),
        },
        {
            key: 'template',
            header: 'Template',
            sortable: true,
            render: (row) => <span className="text-sm capitalize">{row.template}</span>,
        },
        {
            key: 'locale',
            header: 'Locale',
            render: (row) => <span className="font-mono text-xs">{row.locale}</span>,
        },
        {
            key: 'no_index',
            header: 'Index',
            render: (row) =>
                row.no_index ? (
                    <Badge variant="outline" className="text-muted-foreground">noindex</Badge>
                ) : (
                    <span className="text-muted-foreground">—</span>
                ),
        },
        {
            key: 'updated_at',
            header: 'Updated',
            sortable: true,
            render: (row) =>
                row.updated_at ? (
                    <span className="font-mono text-xs text-muted-foreground">
                        {formatDateTime(row.updated_at)}
                    </span>
                ) : (
                    <span className="text-muted-foreground">—</span>
                ),
        },
        {
            key: 'actions',
            header: () => <span className="sr-only">Actions</span>,
            className: 'w-px text-right',
            render: (row) => (
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button variant="ghost" size="icon" className="size-8">
                            <MoreHorizontal />
                            <span className="sr-only">Open actions</span>
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <DropdownMenuItem asChild>
                            <Link href={pagesEdit({ cms_page: row.id })}>Edit</Link>
                        </DropdownMenuItem>
                        {row.deleted_at ? (
                            <DropdownMenuItem
                                onSelect={() =>
                                    router.post(pagesRestore({ id: row.id }).url, {}, { preserveScroll: true })
                                }
                            >
                                <RotateCcw className="size-4" />
                                Restore
                            </DropdownMenuItem>
                        ) : (
                            <DropdownMenuItem
                                variant="destructive"
                                onSelect={() => setArchiving(row)}
                            >
                                <Archive className="size-4" />
                                Archive
                            </DropdownMenuItem>
                        )}
                    </DropdownMenuContent>
                </DropdownMenu>
            ),
        },
    ];

    const filters: DataTableFilter[] = [
        {
            key: 'status',
            label: 'Status',
            type: 'select',
            placeholder: 'Any',
            options: [
                { label: 'Draft', value: 'draft' },
                { label: 'Published', value: 'published' },
                { label: 'Archived', value: 'archived' },
            ],
        },
        {
            key: 'template',
            label: 'Template',
            type: 'select',
            placeholder: 'Any',
            options: [
                { label: 'Default', value: 'default' },
                { label: 'Landing', value: 'landing' },
                { label: 'Docs', value: 'docs' },
                { label: 'Legal', value: 'legal' },
            ],
        },
        {
            key: 'locale',
            label: 'Locale',
            type: 'select',
            placeholder: 'Any',
            options: [
                { label: 'English', value: 'en' },
                { label: 'Arabic', value: 'ar' },
            ],
        },
    ];

    return (
        <>
            <Head title="Pages — CMS" />

            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title="Pages"
                        description="Authored landing, docs and legal pages. Each page is a tree of typed blocks."
                    />
                    <Button asChild>
                        <Link href={pagesCreate().url}>
                            <Plus className="size-4" />
                            New page
                        </Link>
                    </Button>
                </div>

                <DataTable<PageRow>
                    tableId="admin-cms-pages-index"
                    data={pages.data}
                    pagination={pages.meta}
                    columns={columns}
                    filters={filters}
                    initialSearch={tableState.search}
                    initialFilters={tableState.filters}
                    initialSort={tableState.sort}
                    onSearch={(search) => reload({ search })}
                    onFilter={(f) => reload({ filters: f, search: tableState.search })}
                    onClearAll={() => reload({})}
                    onSort={(column, direction) =>
                        reload({
                            search: tableState.search,
                            filters: tableState.filters,
                            sort: { column, direction },
                        })
                    }
                    onPageChange={(page) =>
                        reload({
                            search: tableState.search,
                            filters: tableState.filters,
                            sort: tableState.sort,
                            page,
                        })
                    }
                />
            </div>

            <AlertDialog open={archiving !== null} onOpenChange={(open) => !open && setArchiving(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Archive this page?</AlertDialogTitle>
                        <AlertDialogDescription>
                            The page is soft-deleted and excluded from the public site. You can restore it later.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <Button type="button" variant="ghost" onClick={() => setArchiving(null)}>
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            onClick={() => {
                                if (!archiving) {
return;
}

                                router.delete(pagesDestroy({ cms_page: archiving.id }).url, {
                                    preserveScroll: true,
                                    onFinish: () => setArchiving(null),
                                });
                            }}
                        >
                            Archive
                        </Button>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </>
    );
}
