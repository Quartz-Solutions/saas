import { Head } from '@inertiajs/react';
import {
    AlertCircleIcon,
    Bold,
    CalendarIcon,
    ChevronDownIcon,
    Copy,
    InfoIcon,
    Italic,
    Loader2,
    Mail,
    MoreHorizontal,
    Plus,
    Settings,
    Underline,
    User,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import AlertError from '@/components/alert-error';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import {
    Accordion,
    AccordionContent,
    AccordionItem,
    AccordionTrigger,
} from '@/components/ui/accordion';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { AspectRatio } from '@/components/ui/aspect-ratio';
import {
    Avatar,
    AvatarFallback,
    AvatarImage,
} from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/components/ui/breadcrumb';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    ContextMenu,
    ContextMenuContent,
    ContextMenuItem,
    ContextMenuSeparator,
    ContextMenuShortcut,
    ContextMenuTrigger,
} from '@/components/ui/context-menu';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    HoverCard,
    HoverCardContent,
    HoverCardTrigger,
} from '@/components/ui/hover-card';
import { Input } from '@/components/ui/input';
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSeparator,
    InputOTPSlot,
} from '@/components/ui/input-otp';
import { Label } from '@/components/ui/label';
import {
    Menubar,
    MenubarContent,
    MenubarItem,
    MenubarMenu,
    MenubarSeparator,
    MenubarShortcut,
    MenubarTrigger,
} from '@/components/ui/menubar';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { Progress } from '@/components/ui/progress';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { ScrollArea } from '@/components/ui/scroll-area';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectLabel,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { Skeleton } from '@/components/ui/skeleton';
import { Slider } from '@/components/ui/slider';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { Toggle } from '@/components/ui/toggle';
import {
    ToggleGroup,
    ToggleGroupItem,
} from '@/components/ui/toggle-group';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import {
    DataTable,
    type DataTableColumn,
    type DataTableFilter,
    type PaginationData,
} from '@/components/data-table/data-table';
import { DateRangePicker } from '@/components/ui/date-range-picker';
import {
    LocalDataTable,
    type LocalTableColumn,
    type LocalTableFilter,
} from '@/components/local-data-table';
import { dashboard, sharedComponents } from '@/routes';
import { toast } from 'sonner';
import { type DateRange } from 'react-day-picker';

interface Product {
    id: number;
    sku: string;
    name: string;
    category: 'coffee' | 'tea' | 'pastry' | 'merch' | 'syrup';
    stock: number;
    price: number;
    status: 'active' | 'draft' | 'archived';
    created_at: string;
}

function ComponentSection({
    id,
    title,
    description,
    preview,
    code,
    block = false,
}: {
    id: string;
    title: string;
    description: string;
    preview: React.ReactNode;
    code: string;
    block?: boolean;
}) {
    return (
        <Card id={id} className="scroll-mt-20">
            <CardHeader>
                <div className="flex items-center gap-2">
                    <CardTitle className="text-lg">{title}</CardTitle>
                    <Badge variant="outline" className="font-mono text-[10px]">
                        {id}
                    </Badge>
                </div>
                <CardDescription>{description}</CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
                <div className="rounded-md border bg-muted/30 p-6">
                    {block ? (
                        <div className="w-full">{preview}</div>
                    ) : (
                        <div className="flex flex-wrap items-start gap-3">
                            {preview}
                        </div>
                    )}
                </div>
                <details className="group rounded-md border bg-background">
                    <summary className="flex cursor-pointer items-center justify-between px-4 py-2 text-sm font-medium">
                        <span>Usage</span>
                        <ChevronDownIcon className="size-4 transition-transform group-open:rotate-180" />
                    </summary>
                    <pre className="overflow-x-auto border-t px-4 py-3 text-xs leading-relaxed">
                        <code>{code.trim()}</code>
                    </pre>
                </details>
            </CardContent>
        </Card>
    );
}

function TocLink({ id, label }: { id: string; label: string }) {
    return (
        <a
            href={`#${id}`}
            className="rounded-sm px-2 py-1 text-xs text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
        >
            {label}
        </a>
    );
}

export default function SharedComponents() {
    const [progress, setProgress] = useState(45);
    const [sliderValue, setSliderValue] = useState([50]);
    const [switchOn, setSwitchOn] = useState(true);
    const [checked, setChecked] = useState(true);
    const [radio, setRadio] = useState('comfortable');
    const [otp, setOtp] = useState('');
    const [showPopover, setShowPopover] = useState(false);
    const [dateRange, setDateRange] = useState<DateRange | undefined>(undefined);

    // Mock server dataset for the DataTable demo
    const allProducts = useMemo<Product[]>(
        () => [
            { id: 1, sku: 'COF-001', name: 'Espresso Beans 1kg', category: 'coffee', stock: 42, price: 18.5, status: 'active', created_at: '2026-01-12T08:00:00Z' },
            { id: 2, sku: 'COF-002', name: 'Colombian Ground 500g', category: 'coffee', stock: 15, price: 12.0, status: 'active', created_at: '2026-01-15T08:00:00Z' },
            { id: 3, sku: 'TEA-001', name: 'Earl Grey 250g', category: 'tea', stock: 0, price: 7.25, status: 'archived', created_at: '2025-12-20T08:00:00Z' },
            { id: 4, sku: 'TEA-002', name: 'Matcha Powder 100g', category: 'tea', stock: 28, price: 24.0, status: 'active', created_at: '2026-02-03T08:00:00Z' },
            { id: 5, sku: 'PAS-001', name: 'Croissant (dozen)', category: 'pastry', stock: 6, price: 15.0, status: 'active', created_at: '2026-03-01T08:00:00Z' },
            { id: 6, sku: 'PAS-002', name: 'Cinnamon Roll', category: 'pastry', stock: 3, price: 4.5, status: 'active', created_at: '2026-03-10T08:00:00Z' },
            { id: 7, sku: 'MER-001', name: 'Ceramic Mug', category: 'merch', stock: 120, price: 9.0, status: 'active', created_at: '2025-11-22T08:00:00Z' },
            { id: 8, sku: 'MER-002', name: 'Canvas Tote', category: 'merch', stock: 55, price: 14.0, status: 'draft', created_at: '2026-04-01T08:00:00Z' },
            { id: 9, sku: 'COF-003', name: 'Decaf Blend 1kg', category: 'coffee', stock: 8, price: 19.0, status: 'active', created_at: '2026-02-18T08:00:00Z' },
            { id: 10, sku: 'SYR-001', name: 'Vanilla Syrup 750ml', category: 'syrup', stock: 22, price: 8.5, status: 'active', created_at: '2026-01-28T08:00:00Z' },
            { id: 11, sku: 'SYR-002', name: 'Hazelnut Syrup 750ml', category: 'syrup', stock: 18, price: 8.5, status: 'active', created_at: '2026-02-10T08:00:00Z' },
            { id: 12, sku: 'PAS-003', name: 'Blueberry Muffin', category: 'pastry', stock: 0, price: 3.75, status: 'archived', created_at: '2025-10-05T08:00:00Z' },
        ],
        [],
    );

    const [serverPage, setServerPage] = useState(1);
    const [serverSort, setServerSort] = useState<{ column: string; direction: 'asc' | 'desc' }>({
        column: 'created_at',
        direction: 'desc',
    });
    const [serverFilters, setServerFilters] = useState<Record<string, string>>({});
    const [serverSearch, setServerSearch] = useState('');

    const serverPageSize = 5;
    const serverFiltered = useMemo(() => {
        let rows = [...allProducts];
        if (serverSearch.trim()) {
            const q = serverSearch.toLowerCase();
            rows = rows.filter(
                (r) =>
                    r.name.toLowerCase().includes(q) ||
                    r.sku.toLowerCase().includes(q),
            );
        }
        if (serverFilters.category) {
            rows = rows.filter((r) => r.category === serverFilters.category);
        }
        if (serverFilters.status) {
            rows = rows.filter((r) => r.status === serverFilters.status);
        }
        if (serverFilters.price?.includes('|')) {
            const [min, max] = serverFilters.price.split('|');
            rows = rows.filter((r) => {
                if (min && r.price < Number(min)) return false;
                if (max && r.price > Number(max)) return false;
                return true;
            });
        }
        if (serverSort.column) {
            rows.sort((a, b) => {
                const av = a[serverSort.column as keyof Product];
                const bv = b[serverSort.column as keyof Product];
                const cmp =
                    typeof av === 'number' && typeof bv === 'number'
                        ? av - bv
                        : String(av).localeCompare(String(bv));
                return serverSort.direction === 'asc' ? cmp : -cmp;
            });
        }
        return rows;
    }, [allProducts, serverSearch, serverFilters, serverSort]);

    const serverPaginated = useMemo(() => {
        const start = (serverPage - 1) * serverPageSize;
        return serverFiltered.slice(start, start + serverPageSize);
    }, [serverFiltered, serverPage]);

    const serverPagination: PaginationData = {
        current_page: serverPage,
        last_page: Math.max(1, Math.ceil(serverFiltered.length / serverPageSize)),
        per_page: serverPageSize,
        total: serverFiltered.length,
        from: serverFiltered.length === 0 ? 0 : (serverPage - 1) * serverPageSize + 1,
        to: Math.min(serverPage * serverPageSize, serverFiltered.length),
    };

    const sections = [
        { id: 'accordion', label: 'Accordion' },
        { id: 'alert', label: 'Alert' },
        { id: 'alert-dialog', label: 'Alert Dialog' },
        { id: 'aspect-ratio', label: 'Aspect Ratio' },
        { id: 'avatar', label: 'Avatar' },
        { id: 'badge', label: 'Badge' },
        { id: 'breadcrumb', label: 'Breadcrumb' },
        { id: 'button', label: 'Button' },
        { id: 'card', label: 'Card' },
        { id: 'checkbox', label: 'Checkbox' },
        { id: 'collapsible', label: 'Collapsible' },
        { id: 'context-menu', label: 'Context Menu' },
        { id: 'dialog', label: 'Dialog' },
        { id: 'dropdown-menu', label: 'Dropdown Menu' },
        { id: 'hover-card', label: 'Hover Card' },
        { id: 'input', label: 'Input' },
        { id: 'input-otp', label: 'Input OTP' },
        { id: 'label', label: 'Label' },
        { id: 'menubar', label: 'Menubar' },
        { id: 'popover', label: 'Popover' },
        { id: 'progress', label: 'Progress' },
        { id: 'radio-group', label: 'Radio Group' },
        { id: 'scroll-area', label: 'Scroll Area' },
        { id: 'select', label: 'Select' },
        { id: 'separator', label: 'Separator' },
        { id: 'sheet', label: 'Sheet' },
        { id: 'skeleton', label: 'Skeleton' },
        { id: 'slider', label: 'Slider' },
        { id: 'sonner', label: 'Sonner (Toast)' },
        { id: 'spinner', label: 'Spinner' },
        { id: 'switch', label: 'Switch' },
        { id: 'tabs', label: 'Tabs' },
        { id: 'textarea', label: 'Textarea' },
        { id: 'toggle', label: 'Toggle' },
        { id: 'toggle-group', label: 'Toggle Group' },
        { id: 'tooltip', label: 'Tooltip' },
        { id: 'alert-error', label: 'AlertError' },
        { id: 'heading', label: 'Heading' },
        { id: 'input-error', label: 'InputError' },
        { id: 'password-input', label: 'PasswordInput' },
        { id: 'text-link', label: 'TextLink' },
        { id: 'date-range-picker', label: 'DateRangePicker' },
        { id: 'data-table', label: 'DataTable (server)' },
        { id: 'local-data-table', label: 'LocalDataTable (client)' },
    ];

    return (
        <>
            <Head title="Shared Components" />
            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto p-4 md:p-6">
                <div className="space-y-2">
                    <Heading
                        title="Shared Components"
                        description="Live reference for every reusable UI primitive in this project. Import from @/components/ui or @/components."
                    />
                    <div className="flex flex-wrap gap-1 rounded-md border bg-muted/30 p-3">
                        {sections.map((s) => (
                            <TocLink key={s.id} id={s.id} label={s.label} />
                        ))}
                    </div>
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    <ComponentSection
                        id="accordion"
                        title="Accordion"
                        description="Vertically stacked, expandable items. Good for FAQs and grouped settings."
                        preview={
                            <Accordion type="single" collapsible className="w-full">
                                <AccordionItem value="item-1">
                                    <AccordionTrigger>
                                        What is this component?
                                    </AccordionTrigger>
                                    <AccordionContent>
                                        A disclosure primitive built on Radix.
                                    </AccordionContent>
                                </AccordionItem>
                                <AccordionItem value="item-2">
                                    <AccordionTrigger>Keyboard</AccordionTrigger>
                                    <AccordionContent>
                                        Arrow keys navigate between triggers.
                                    </AccordionContent>
                                </AccordionItem>
                            </Accordion>
                        }
                        code={`import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from '@/components/ui/accordion';

<Accordion type="single" collapsible>
    <AccordionItem value="item-1">
        <AccordionTrigger>What is this component?</AccordionTrigger>
        <AccordionContent>A disclosure primitive built on Radix.</AccordionContent>
    </AccordionItem>
</Accordion>`}
                    />

                    <ComponentSection
                        id="alert"
                        title="Alert"
                        description="Callout for important, short messages. Variants: default, destructive."
                        preview={
                            <div className="w-full space-y-3">
                                <Alert>
                                    <InfoIcon />
                                    <AlertTitle>Heads up!</AlertTitle>
                                    <AlertDescription>
                                        This is an informational alert.
                                    </AlertDescription>
                                </Alert>
                                <Alert variant="destructive">
                                    <AlertCircleIcon />
                                    <AlertTitle>Error</AlertTitle>
                                    <AlertDescription>
                                        Something went wrong.
                                    </AlertDescription>
                                </Alert>
                            </div>
                        }
                        code={`import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { InfoIcon } from 'lucide-react';

<Alert variant="destructive">
    <InfoIcon />
    <AlertTitle>Error</AlertTitle>
    <AlertDescription>Something went wrong.</AlertDescription>
</Alert>`}
                    />

                    <ComponentSection
                        id="alert-dialog"
                        title="Alert Dialog"
                        description="Modal confirmation that interrupts the user to confirm a destructive action."
                        preview={
                            <AlertDialog>
                                <AlertDialogTrigger asChild>
                                    <Button variant="destructive">
                                        Delete account
                                    </Button>
                                </AlertDialogTrigger>
                                <AlertDialogContent>
                                    <AlertDialogHeader>
                                        <AlertDialogTitle>
                                            Are you sure?
                                        </AlertDialogTitle>
                                        <AlertDialogDescription>
                                            This action cannot be undone.
                                        </AlertDialogDescription>
                                    </AlertDialogHeader>
                                    <AlertDialogFooter>
                                        <AlertDialogCancel>
                                            Cancel
                                        </AlertDialogCancel>
                                        <AlertDialogAction>
                                            Continue
                                        </AlertDialogAction>
                                    </AlertDialogFooter>
                                </AlertDialogContent>
                            </AlertDialog>
                        }
                        code={`import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle, AlertDialogTrigger } from '@/components/ui/alert-dialog';

<AlertDialog>
    <AlertDialogTrigger asChild>
        <Button variant="destructive">Delete</Button>
    </AlertDialogTrigger>
    <AlertDialogContent>
        <AlertDialogHeader>
            <AlertDialogTitle>Are you sure?</AlertDialogTitle>
            <AlertDialogDescription>This cannot be undone.</AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
            <AlertDialogCancel>Cancel</AlertDialogCancel>
            <AlertDialogAction>Continue</AlertDialogAction>
        </AlertDialogFooter>
    </AlertDialogContent>
</AlertDialog>`}
                    />

                    <ComponentSection
                        id="aspect-ratio"
                        title="Aspect Ratio"
                        description="Locks content to a given aspect ratio (useful for images & videos)."
                        preview={
                            <div className="w-64">
                                <AspectRatio
                                    ratio={16 / 9}
                                    className="bg-muted rounded-md flex items-center justify-center text-sm text-muted-foreground"
                                >
                                    16 / 9
                                </AspectRatio>
                            </div>
                        }
                        code={`import { AspectRatio } from '@/components/ui/aspect-ratio';

<AspectRatio ratio={16 / 9}>
    <img src="..." alt="" className="size-full rounded-md object-cover" />
</AspectRatio>`}
                    />

                    <ComponentSection
                        id="avatar"
                        title="Avatar"
                        description="Image element with a text fallback for user profiles."
                        preview={
                            <div className="flex items-center gap-3">
                                <Avatar>
                                    <AvatarImage
                                        src="https://github.com/shadcn.png"
                                        alt="@shadcn"
                                    />
                                    <AvatarFallback>CN</AvatarFallback>
                                </Avatar>
                                <Avatar>
                                    <AvatarFallback>AB</AvatarFallback>
                                </Avatar>
                            </div>
                        }
                        code={`import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';

<Avatar>
    <AvatarImage src="https://github.com/shadcn.png" alt="@shadcn" />
    <AvatarFallback>CN</AvatarFallback>
</Avatar>`}
                    />

                    <ComponentSection
                        id="badge"
                        title="Badge"
                        description="Compact status or category label. Variants: default, secondary, destructive, outline."
                        preview={
                            <div className="flex gap-2">
                                <Badge>Default</Badge>
                                <Badge variant="secondary">Secondary</Badge>
                                <Badge variant="destructive">Destructive</Badge>
                                <Badge variant="outline">Outline</Badge>
                            </div>
                        }
                        code={`import { Badge } from '@/components/ui/badge';

<Badge variant="secondary">New</Badge>`}
                    />

                    <ComponentSection
                        id="breadcrumb"
                        title="Breadcrumb"
                        description="Navigation trail showing the user's location in the hierarchy."
                        preview={
                            <Breadcrumb>
                                <BreadcrumbList>
                                    <BreadcrumbItem>
                                        <BreadcrumbLink href="#">
                                            Home
                                        </BreadcrumbLink>
                                    </BreadcrumbItem>
                                    <BreadcrumbSeparator />
                                    <BreadcrumbItem>
                                        <BreadcrumbLink href="#">
                                            Products
                                        </BreadcrumbLink>
                                    </BreadcrumbItem>
                                    <BreadcrumbSeparator />
                                    <BreadcrumbItem>
                                        <BreadcrumbPage>Details</BreadcrumbPage>
                                    </BreadcrumbItem>
                                </BreadcrumbList>
                            </Breadcrumb>
                        }
                        code={`import { Breadcrumb, BreadcrumbItem, BreadcrumbLink, BreadcrumbList, BreadcrumbPage, BreadcrumbSeparator } from '@/components/ui/breadcrumb';

<Breadcrumb>
    <BreadcrumbList>
        <BreadcrumbItem><BreadcrumbLink href="/">Home</BreadcrumbLink></BreadcrumbItem>
        <BreadcrumbSeparator />
        <BreadcrumbItem><BreadcrumbPage>Details</BreadcrumbPage></BreadcrumbItem>
    </BreadcrumbList>
</Breadcrumb>`}
                    />

                    <ComponentSection
                        id="button"
                        title="Button"
                        description="Primary interaction. Variants: default, secondary, destructive, outline, ghost, link. Sizes: sm, default, lg, icon."
                        preview={
                            <div className="flex flex-wrap gap-2">
                                <Button>Default</Button>
                                <Button variant="secondary">Secondary</Button>
                                <Button variant="destructive">Destructive</Button>
                                <Button variant="outline">Outline</Button>
                                <Button variant="ghost">Ghost</Button>
                                <Button variant="link">Link</Button>
                                <Button size="icon" variant="outline">
                                    <Plus />
                                </Button>
                                <Button disabled>
                                    <Loader2 className="animate-spin" />
                                    Loading
                                </Button>
                            </div>
                        }
                        code={`import { Button } from '@/components/ui/button';

<Button variant="outline" size="sm">Click me</Button>
<Button asChild><a href="/go">Link-as-button</a></Button>`}
                    />

                    <ComponentSection
                        id="card"
                        title="Card"
                        description="Container for related content with header, body, and footer slots."
                        preview={
                            <Card className="w-full max-w-sm">
                                <CardHeader>
                                    <CardTitle>Invoice #1024</CardTitle>
                                    <CardDescription>
                                        Issued Apr 18, 2026
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <p className="text-sm">$240.00 due</p>
                                </CardContent>
                                <CardFooter>
                                    <Button size="sm">Pay now</Button>
                                </CardFooter>
                            </Card>
                        }
                        code={`import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';

<Card>
    <CardHeader><CardTitle>Title</CardTitle><CardDescription>Subtitle</CardDescription></CardHeader>
    <CardContent>Body content</CardContent>
    <CardFooter>Footer</CardFooter>
</Card>`}
                    />

                    <ComponentSection
                        id="checkbox"
                        title="Checkbox"
                        description="Binary selection control. Use with Label for accessible forms."
                        preview={
                            <div className="flex items-center gap-2">
                                <Checkbox
                                    id="demo-checkbox"
                                    checked={checked}
                                    onCheckedChange={(v) =>
                                        setChecked(Boolean(v))
                                    }
                                />
                                <Label htmlFor="demo-checkbox">
                                    Accept the terms
                                </Label>
                            </div>
                        }
                        code={`import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';

<div className="flex items-center gap-2">
    <Checkbox id="terms" />
    <Label htmlFor="terms">Accept terms</Label>
</div>`}
                    />

                    <ComponentSection
                        id="collapsible"
                        title="Collapsible"
                        description="Show/hide a region of content."
                        preview={
                            <Collapsible className="w-full">
                                <CollapsibleTrigger asChild>
                                    <Button variant="outline" size="sm">
                                        Toggle details
                                    </Button>
                                </CollapsibleTrigger>
                                <CollapsibleContent className="mt-2 rounded-md border p-3 text-sm">
                                    Hidden content revealed on toggle.
                                </CollapsibleContent>
                            </Collapsible>
                        }
                        code={`import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';

<Collapsible>
    <CollapsibleTrigger asChild><Button>Toggle</Button></CollapsibleTrigger>
    <CollapsibleContent>Hidden content</CollapsibleContent>
</Collapsible>`}
                    />

                    <ComponentSection
                        id="context-menu"
                        title="Context Menu"
                        description="Right-click menu surface."
                        preview={
                            <ContextMenu>
                                <ContextMenuTrigger className="flex h-24 w-full items-center justify-center rounded-md border border-dashed text-sm text-muted-foreground">
                                    Right-click here
                                </ContextMenuTrigger>
                                <ContextMenuContent>
                                    <ContextMenuItem>
                                        Back
                                        <ContextMenuShortcut>
                                            ⌘[
                                        </ContextMenuShortcut>
                                    </ContextMenuItem>
                                    <ContextMenuItem>Forward</ContextMenuItem>
                                    <ContextMenuSeparator />
                                    <ContextMenuItem>Reload</ContextMenuItem>
                                </ContextMenuContent>
                            </ContextMenu>
                        }
                        code={`import { ContextMenu, ContextMenuContent, ContextMenuItem, ContextMenuTrigger } from '@/components/ui/context-menu';

<ContextMenu>
    <ContextMenuTrigger>Right click</ContextMenuTrigger>
    <ContextMenuContent>
        <ContextMenuItem>Back</ContextMenuItem>
        <ContextMenuItem>Forward</ContextMenuItem>
    </ContextMenuContent>
</ContextMenu>`}
                    />

                    <ComponentSection
                        id="dialog"
                        title="Dialog"
                        description="Non-blocking modal for forms, confirmations, and detail views."
                        preview={
                            <Dialog>
                                <DialogTrigger asChild>
                                    <Button>Open dialog</Button>
                                </DialogTrigger>
                                <DialogContent>
                                    <DialogHeader>
                                        <DialogTitle>Edit profile</DialogTitle>
                                        <DialogDescription>
                                            Make changes and save.
                                        </DialogDescription>
                                    </DialogHeader>
                                    <div className="space-y-3">
                                        <Label htmlFor="dialog-name">Name</Label>
                                        <Input
                                            id="dialog-name"
                                            defaultValue="John Doe"
                                        />
                                    </div>
                                    <DialogFooter>
                                        <Button>Save</Button>
                                    </DialogFooter>
                                </DialogContent>
                            </Dialog>
                        }
                        code={`import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';

<Dialog>
    <DialogTrigger asChild><Button>Open</Button></DialogTrigger>
    <DialogContent>
        <DialogHeader><DialogTitle>Title</DialogTitle></DialogHeader>
        <DialogFooter><Button>Save</Button></DialogFooter>
    </DialogContent>
</Dialog>`}
                    />

                    <ComponentSection
                        id="dropdown-menu"
                        title="Dropdown Menu"
                        description="Button-triggered menu with items, groups, and shortcuts."
                        preview={
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <Button variant="outline">
                                        <MoreHorizontal />
                                        Actions
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent>
                                    <DropdownMenuLabel>
                                        My account
                                    </DropdownMenuLabel>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem>
                                        <User /> Profile
                                    </DropdownMenuItem>
                                    <DropdownMenuItem>
                                        <Settings /> Settings
                                    </DropdownMenuItem>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem variant="destructive">
                                        Delete
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        }
                        code={`import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';

<DropdownMenu>
    <DropdownMenuTrigger asChild><Button>Actions</Button></DropdownMenuTrigger>
    <DropdownMenuContent>
        <DropdownMenuLabel>My account</DropdownMenuLabel>
        <DropdownMenuItem>Profile</DropdownMenuItem>
    </DropdownMenuContent>
</DropdownMenu>`}
                    />

                    <ComponentSection
                        id="hover-card"
                        title="Hover Card"
                        description="Tooltip-like surface with richer content, shown on hover or focus."
                        preview={
                            <HoverCard>
                                <HoverCardTrigger asChild>
                                    <Button variant="link">@vercel</Button>
                                </HoverCardTrigger>
                                <HoverCardContent className="w-64">
                                    <div className="flex gap-3">
                                        <Avatar>
                                            <AvatarFallback>V</AvatarFallback>
                                        </Avatar>
                                        <div>
                                            <p className="text-sm font-semibold">
                                                Vercel
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                Frontend cloud platform.
                                            </p>
                                        </div>
                                    </div>
                                </HoverCardContent>
                            </HoverCard>
                        }
                        code={`import { HoverCard, HoverCardContent, HoverCardTrigger } from '@/components/ui/hover-card';

<HoverCard>
    <HoverCardTrigger asChild><Button variant="link">@user</Button></HoverCardTrigger>
    <HoverCardContent>User bio & stats</HoverCardContent>
</HoverCard>`}
                    />

                    <ComponentSection
                        id="input"
                        title="Input"
                        description="Text input styled with the shared form ring. Use with Label."
                        preview={
                            <div className="w-full max-w-sm space-y-2">
                                <Label htmlFor="demo-email">Email</Label>
                                <Input
                                    id="demo-email"
                                    type="email"
                                    placeholder="you@example.com"
                                />
                            </div>
                        }
                        code={`import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

<Label htmlFor="email">Email</Label>
<Input id="email" type="email" placeholder="you@example.com" />`}
                    />

                    <ComponentSection
                        id="input-otp"
                        title="Input OTP"
                        description="One-time password input — used by 2FA flows."
                        preview={
                            <InputOTP
                                maxLength={6}
                                value={otp}
                                onChange={setOtp}
                            >
                                <InputOTPGroup>
                                    <InputOTPSlot index={0} />
                                    <InputOTPSlot index={1} />
                                    <InputOTPSlot index={2} />
                                </InputOTPGroup>
                                <InputOTPSeparator />
                                <InputOTPGroup>
                                    <InputOTPSlot index={3} />
                                    <InputOTPSlot index={4} />
                                    <InputOTPSlot index={5} />
                                </InputOTPGroup>
                            </InputOTP>
                        }
                        code={`import { InputOTP, InputOTPGroup, InputOTPSeparator, InputOTPSlot } from '@/components/ui/input-otp';

<InputOTP maxLength={6} value={value} onChange={setValue}>
    <InputOTPGroup><InputOTPSlot index={0} />...</InputOTPGroup>
</InputOTP>`}
                    />

                    <ComponentSection
                        id="label"
                        title="Label"
                        description="Accessible label that associates with a form control via htmlFor."
                        preview={
                            <div className="space-y-1.5">
                                <Label htmlFor="demo-label">Full name</Label>
                                <Input
                                    id="demo-label"
                                    placeholder="Ada Lovelace"
                                />
                            </div>
                        }
                        code={`import { Label } from '@/components/ui/label';

<Label htmlFor="name">Full name</Label>`}
                    />

                    <ComponentSection
                        id="menubar"
                        title="Menubar"
                        description="Persistent menu bar (like a desktop app)."
                        preview={
                            <Menubar>
                                <MenubarMenu>
                                    <MenubarTrigger>File</MenubarTrigger>
                                    <MenubarContent>
                                        <MenubarItem>
                                            New
                                            <MenubarShortcut>⌘N</MenubarShortcut>
                                        </MenubarItem>
                                        <MenubarItem>Open</MenubarItem>
                                        <MenubarSeparator />
                                        <MenubarItem>Save</MenubarItem>
                                    </MenubarContent>
                                </MenubarMenu>
                                <MenubarMenu>
                                    <MenubarTrigger>Edit</MenubarTrigger>
                                    <MenubarContent>
                                        <MenubarItem>Undo</MenubarItem>
                                        <MenubarItem>Redo</MenubarItem>
                                    </MenubarContent>
                                </MenubarMenu>
                            </Menubar>
                        }
                        code={`import { Menubar, MenubarContent, MenubarItem, MenubarMenu, MenubarTrigger } from '@/components/ui/menubar';

<Menubar>
    <MenubarMenu>
        <MenubarTrigger>File</MenubarTrigger>
        <MenubarContent><MenubarItem>New</MenubarItem></MenubarContent>
    </MenubarMenu>
</Menubar>`}
                    />

                    <ComponentSection
                        id="popover"
                        title="Popover"
                        description="Floating surface anchored to a trigger. Use for inline forms and pickers."
                        preview={
                            <Popover
                                open={showPopover}
                                onOpenChange={setShowPopover}
                            >
                                <PopoverTrigger asChild>
                                    <Button variant="outline">
                                        <CalendarIcon />
                                        Schedule
                                    </Button>
                                </PopoverTrigger>
                                <PopoverContent>
                                    <div className="space-y-2">
                                        <p className="text-sm font-medium">
                                            Pick a time
                                        </p>
                                        <Input type="time" defaultValue="09:00" />
                                    </div>
                                </PopoverContent>
                            </Popover>
                        }
                        code={`import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';

<Popover>
    <PopoverTrigger asChild><Button>Open</Button></PopoverTrigger>
    <PopoverContent>Floating content</PopoverContent>
</Popover>`}
                    />

                    <ComponentSection
                        id="progress"
                        title="Progress"
                        description="Determinate progress indicator. Pass value 0-100."
                        preview={
                            <div className="w-full space-y-2">
                                <Progress value={progress} />
                                <div className="flex gap-2">
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        onClick={() =>
                                            setProgress((p) =>
                                                Math.max(0, p - 10),
                                            )
                                        }
                                    >
                                        -10
                                    </Button>
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        onClick={() =>
                                            setProgress((p) =>
                                                Math.min(100, p + 10),
                                            )
                                        }
                                    >
                                        +10
                                    </Button>
                                    <span className="text-sm text-muted-foreground">
                                        {progress}%
                                    </span>
                                </div>
                            </div>
                        }
                        code={`import { Progress } from '@/components/ui/progress';

<Progress value={progress} />`}
                    />

                    <ComponentSection
                        id="radio-group"
                        title="Radio Group"
                        description="Mutually exclusive choice. Wire to a form with a controlled value."
                        preview={
                            <RadioGroup value={radio} onValueChange={setRadio}>
                                <div className="flex items-center gap-2">
                                    <RadioGroupItem
                                        value="compact"
                                        id="r-compact"
                                    />
                                    <Label htmlFor="r-compact">Compact</Label>
                                </div>
                                <div className="flex items-center gap-2">
                                    <RadioGroupItem
                                        value="comfortable"
                                        id="r-comfortable"
                                    />
                                    <Label htmlFor="r-comfortable">
                                        Comfortable
                                    </Label>
                                </div>
                            </RadioGroup>
                        }
                        code={`import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';

<RadioGroup value={value} onValueChange={setValue}>
    <RadioGroupItem value="a" id="a" /> <Label htmlFor="a">A</Label>
</RadioGroup>`}
                    />

                    <ComponentSection
                        id="scroll-area"
                        title="Scroll Area"
                        description="Cross-browser styled scrollbar wrapper."
                        preview={
                            <ScrollArea className="h-40 w-full rounded-md border p-3">
                                <div className="space-y-2 text-sm">
                                    {Array.from({ length: 25 }).map((_, i) => (
                                        <p key={i}>Item {i + 1}</p>
                                    ))}
                                </div>
                            </ScrollArea>
                        }
                        code={`import { ScrollArea } from '@/components/ui/scroll-area';

<ScrollArea className="h-40 w-full">...</ScrollArea>`}
                    />

                    <ComponentSection
                        id="select"
                        title="Select"
                        description="Single-choice dropdown. Use SelectGroup + SelectLabel for categories."
                        preview={
                            <Select defaultValue="usd">
                                <SelectTrigger className="w-48">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        <SelectLabel>Currencies</SelectLabel>
                                        <SelectItem value="usd">USD</SelectItem>
                                        <SelectItem value="eur">EUR</SelectItem>
                                        <SelectItem value="jod">JOD</SelectItem>
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                        }
                        code={`import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

<Select value={value} onValueChange={setValue}>
    <SelectTrigger><SelectValue /></SelectTrigger>
    <SelectContent>
        <SelectItem value="a">A</SelectItem>
    </SelectContent>
</Select>`}
                    />

                    <ComponentSection
                        id="separator"
                        title="Separator"
                        description="Thin divider, horizontal or vertical."
                        preview={
                            <div className="w-full">
                                <p className="text-sm">Section A</p>
                                <Separator className="my-3" />
                                <p className="text-sm">Section B</p>
                                <div className="mt-3 flex h-8 items-center gap-2 text-xs">
                                    <span>Blog</span>
                                    <Separator orientation="vertical" />
                                    <span>Docs</span>
                                    <Separator orientation="vertical" />
                                    <span>Source</span>
                                </div>
                            </div>
                        }
                        code={`import { Separator } from '@/components/ui/separator';

<Separator />
<Separator orientation="vertical" />`}
                    />

                    <ComponentSection
                        id="sheet"
                        title="Sheet"
                        description="Dialog that slides in from an edge. Ideal for mobile nav and side forms."
                        preview={
                            <Sheet>
                                <SheetTrigger asChild>
                                    <Button variant="outline">
                                        Open sheet
                                    </Button>
                                </SheetTrigger>
                                <SheetContent>
                                    <SheetHeader>
                                        <SheetTitle>Edit profile</SheetTitle>
                                        <SheetDescription>
                                            Update your details.
                                        </SheetDescription>
                                    </SheetHeader>
                                    <div className="grid gap-3 p-4">
                                        <Label htmlFor="sheet-name">Name</Label>
                                        <Input id="sheet-name" />
                                    </div>
                                    <SheetFooter>
                                        <Button>Save</Button>
                                    </SheetFooter>
                                </SheetContent>
                            </Sheet>
                        }
                        code={`import { Sheet, SheetContent, SheetFooter, SheetHeader, SheetTitle, SheetTrigger } from '@/components/ui/sheet';

<Sheet>
    <SheetTrigger asChild><Button>Open</Button></SheetTrigger>
    <SheetContent side="right">...</SheetContent>
</Sheet>`}
                    />

                    <ComponentSection
                        id="skeleton"
                        title="Skeleton"
                        description="Loading placeholder with a pulse animation."
                        preview={
                            <div className="w-full max-w-sm space-y-2">
                                <Skeleton className="h-4 w-1/2" />
                                <Skeleton className="h-4 w-full" />
                                <Skeleton className="h-4 w-3/4" />
                            </div>
                        }
                        code={`import { Skeleton } from '@/components/ui/skeleton';

<Skeleton className="h-4 w-1/2" />`}
                    />

                    <ComponentSection
                        id="slider"
                        title="Slider"
                        description="Value range picker. Supports multi-thumb for ranges."
                        preview={
                            <div className="w-full space-y-2">
                                <Slider
                                    value={sliderValue}
                                    onValueChange={setSliderValue}
                                    max={100}
                                    step={1}
                                />
                                <p className="text-xs text-muted-foreground">
                                    Value: {sliderValue[0]}
                                </p>
                            </div>
                        }
                        code={`import { Slider } from '@/components/ui/slider';

<Slider value={value} onValueChange={setValue} max={100} step={1} />`}
                    />

                    <ComponentSection
                        id="sonner"
                        title="Sonner (Toast)"
                        description="App-wide toast notifications. Toaster is mounted in app.tsx — call toast() anywhere."
                        preview={
                            <div className="flex gap-2">
                                <Button
                                    onClick={() =>
                                        toast('Saved successfully!')
                                    }
                                >
                                    Show toast
                                </Button>
                                <Button
                                    variant="outline"
                                    onClick={() =>
                                        toast.error('Something broke')
                                    }
                                >
                                    Error toast
                                </Button>
                                <Button
                                    variant="outline"
                                    onClick={() =>
                                        toast.success('All good', {
                                            description: 'Order placed.',
                                        })
                                    }
                                >
                                    With description
                                </Button>
                            </div>
                        }
                        code={`import { toast } from 'sonner';

toast('Saved successfully!');
toast.error('Something broke');
toast.success('Order placed', { description: 'We sent you a receipt.' });`}
                    />

                    <ComponentSection
                        id="spinner"
                        title="Spinner"
                        description="Animated loading indicator (Lucide Loader2)."
                        preview={
                            <div className="flex items-center gap-3">
                                <Spinner />
                                <Spinner className="size-6 text-primary" />
                                <Button disabled>
                                    <Spinner />
                                    Loading
                                </Button>
                            </div>
                        }
                        code={`import { Spinner } from '@/components/ui/spinner';

<Spinner className="size-6" />`}
                    />

                    <ComponentSection
                        id="switch"
                        title="Switch"
                        description="Toggle on/off control. Use for instant-apply settings."
                        preview={
                            <div className="flex items-center gap-2">
                                <Switch
                                    id="demo-switch"
                                    checked={switchOn}
                                    onCheckedChange={setSwitchOn}
                                />
                                <Label htmlFor="demo-switch">
                                    Email notifications
                                </Label>
                            </div>
                        }
                        code={`import { Switch } from '@/components/ui/switch';

<Switch checked={value} onCheckedChange={setValue} />`}
                    />

                    <ComponentSection
                        id="tabs"
                        title="Tabs"
                        description="Panel switcher. Associate triggers and contents by matching value."
                        preview={
                            <Tabs defaultValue="account" className="w-full">
                                <TabsList>
                                    <TabsTrigger value="account">
                                        Account
                                    </TabsTrigger>
                                    <TabsTrigger value="password">
                                        Password
                                    </TabsTrigger>
                                </TabsList>
                                <TabsContent
                                    value="account"
                                    className="rounded-md border p-3 text-sm"
                                >
                                    Account details form goes here.
                                </TabsContent>
                                <TabsContent
                                    value="password"
                                    className="rounded-md border p-3 text-sm"
                                >
                                    Change password form goes here.
                                </TabsContent>
                            </Tabs>
                        }
                        code={`import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';

<Tabs defaultValue="a">
    <TabsList>
        <TabsTrigger value="a">A</TabsTrigger>
    </TabsList>
    <TabsContent value="a">Panel A</TabsContent>
</Tabs>`}
                    />

                    <ComponentSection
                        id="textarea"
                        title="Textarea"
                        description="Multi-line text input with auto field-sizing."
                        preview={
                            <div className="w-full max-w-sm space-y-2">
                                <Label htmlFor="demo-textarea">Notes</Label>
                                <Textarea
                                    id="demo-textarea"
                                    placeholder="Anything the cashier should know..."
                                />
                            </div>
                        }
                        code={`import { Textarea } from '@/components/ui/textarea';

<Textarea placeholder="Notes..." />`}
                    />

                    <ComponentSection
                        id="toggle"
                        title="Toggle"
                        description="Single pressed/unpressed button. Good for formatting toolbars."
                        preview={
                            <Toggle aria-label="Toggle italic">
                                <Italic />
                            </Toggle>
                        }
                        code={`import { Toggle } from '@/components/ui/toggle';

<Toggle aria-label="Italic"><Italic /></Toggle>`}
                    />

                    <ComponentSection
                        id="toggle-group"
                        title="Toggle Group"
                        description="Group of toggles. Type 'single' or 'multiple'."
                        preview={
                            <ToggleGroup
                                type="multiple"
                                variant="outline"
                                defaultValue={['bold']}
                            >
                                <ToggleGroupItem value="bold" aria-label="Bold">
                                    <Bold />
                                </ToggleGroupItem>
                                <ToggleGroupItem
                                    value="italic"
                                    aria-label="Italic"
                                >
                                    <Italic />
                                </ToggleGroupItem>
                                <ToggleGroupItem
                                    value="underline"
                                    aria-label="Underline"
                                >
                                    <Underline />
                                </ToggleGroupItem>
                            </ToggleGroup>
                        }
                        code={`import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';

<ToggleGroup type="multiple" variant="outline">
    <ToggleGroupItem value="bold"><Bold /></ToggleGroupItem>
</ToggleGroup>`}
                    />

                    <ComponentSection
                        id="tooltip"
                        title="Tooltip"
                        description="Hover/focus hint. TooltipProvider is already mounted in app.tsx."
                        preview={
                            <Tooltip>
                                <TooltipTrigger asChild>
                                    <Button variant="outline" size="icon">
                                        <Copy />
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent>Copy to clipboard</TooltipContent>
                            </Tooltip>
                        }
                        code={`import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';

<Tooltip>
    <TooltipTrigger asChild><Button>Hover</Button></TooltipTrigger>
    <TooltipContent>Tooltip text</TooltipContent>
</Tooltip>`}
                    />

                    <ComponentSection
                        id="alert-error"
                        title="AlertError"
                        description="Project-specific helper that renders a destructive alert from an array of validation errors."
                        preview={
                            <AlertError
                                title="Please fix these"
                                errors={[
                                    'Email is required.',
                                    'Password must be at least 8 characters.',
                                ]}
                            />
                        }
                        code={`import AlertError from '@/components/alert-error';

<AlertError
    title="Please fix these"
    errors={['Email is required.', 'Password too short.']}
/>`}
                    />

                    <ComponentSection
                        id="heading"
                        title="Heading"
                        description="Page/section heading with optional description. Variants: default, small."
                        preview={
                            <div className="w-full space-y-4">
                                <Heading
                                    title="Section title"
                                    description="This is the default variant used on settings pages."
                                />
                                <Heading
                                    title="Small heading"
                                    description="Used inside cards."
                                    variant="small"
                                />
                            </div>
                        }
                        code={`import Heading from '@/components/heading';

<Heading title="Title" description="Subtitle" />
<Heading title="Small" variant="small" />`}
                    />

                    <ComponentSection
                        id="input-error"
                        title="InputError"
                        description="Renders a red message below a form field — null if message is empty."
                        preview={
                            <div className="w-full max-w-sm space-y-1.5">
                                <Label htmlFor="err-email">Email</Label>
                                <Input
                                    id="err-email"
                                    aria-invalid
                                    defaultValue="not-an-email"
                                />
                                <InputError message="Must be a valid email address." />
                            </div>
                        }
                        code={`import InputError from '@/components/input-error';

<Input aria-invalid />
<InputError message={errors.email} />`}
                    />

                    <ComponentSection
                        id="password-input"
                        title="PasswordInput"
                        description="Password field with a show/hide toggle. Drop-in replacement for <Input type='password' />."
                        preview={
                            <div className="w-full max-w-sm space-y-1.5">
                                <Label htmlFor="demo-password">Password</Label>
                                <PasswordInput
                                    id="demo-password"
                                    placeholder="••••••••"
                                />
                            </div>
                        }
                        code={`import PasswordInput from '@/components/password-input';

<PasswordInput id="password" name="password" />`}
                    />

                    <ComponentSection
                        id="text-link"
                        title="TextLink"
                        description="Styled Inertia <Link>. Pass any Inertia Link prop (href, method, etc)."
                        preview={
                            <p className="text-sm">
                                Go back to{' '}
                                <TextLink href={dashboard().url}>
                                    the dashboard
                                </TextLink>
                                .
                            </p>
                        }
                        code={`import TextLink from '@/components/text-link';
import { dashboard } from '@/routes';

<TextLink href={dashboard().url}>Go to dashboard</TextLink>`}
                    />
                </div>

                <div className="space-y-6">
                    <ComponentSection
                        id="date-range-picker"
                        block
                        title="DateRangePicker"
                        description="Popover calendar with preset ranges (Today / Last 7 / 30 / 90 Days / This Month / Last Month). Emits a react-day-picker DateRange."
                        preview={
                            <div className="max-w-sm space-y-2">
                                <DateRangePicker
                                    value={dateRange}
                                    onChange={setDateRange}
                                />
                                <p className="text-xs text-muted-foreground">
                                    {dateRange?.from
                                        ? `${dateRange.from.toISOString().slice(0, 10)} → ${dateRange.to?.toISOString().slice(0, 10) ?? '…'}`
                                        : 'No range selected'}
                                </p>
                            </div>
                        }
                        code={`import { DateRangePicker } from '@/components/ui/date-range-picker';
import { type DateRange } from 'react-day-picker';

const [range, setRange] = useState<DateRange | undefined>();

<DateRangePicker value={range} onChange={setRange} />`}
                    />

                    <ComponentSection
                        id="data-table"
                        block
                        title="DataTable (server-driven)"
                        description="Generic table for server-paginated data. Supports text / select / date / daterange / range / async-select filters, sortable columns, search, CSV-style export callback, column visibility toggle, and cookie+API preference persistence via tableId. The demo below mocks the server in-memory so you can play with pagination, sort, filter chips and async-select (which really does hit /app/users/search)."
                        preview={
                            <DataTable<Product>
                                tableId="demo-products"
                                data={serverPaginated}
                                pagination={serverPagination}
                                initialSort={serverSort}
                                initialFilters={serverFilters}
                                initialSearch={serverSearch}
                                onPageChange={setServerPage}
                                onSort={(column, direction) => {
                                    setServerSort({ column, direction });
                                    setServerPage(1);
                                }}
                                onSearch={(q) => {
                                    setServerSearch(q);
                                    setServerPage(1);
                                }}
                                onFilter={(f) => {
                                    setServerFilters(f);
                                    setServerPage(1);
                                }}
                                onClearAll={() => {
                                    setServerFilters({});
                                    setServerSearch('');
                                    setServerPage(1);
                                }}
                                onExport={(f, q) =>
                                    toast.success('Exported', {
                                        description: `filters=${JSON.stringify(f)} search="${q}"`,
                                    })
                                }
                                columns={
                                    [
                                        {
                                            key: 'sku',
                                            header: 'SKU',
                                            sortable: true,
                                            className: 'font-mono text-xs',
                                        },
                                        {
                                            key: 'name',
                                            header: 'Name',
                                            sortable: true,
                                        },
                                        {
                                            key: 'category',
                                            header: 'Category',
                                            sortable: true,
                                            render: (row) => (
                                                <Badge variant="secondary">
                                                    {row.category}
                                                </Badge>
                                            ),
                                        },
                                        {
                                            key: 'stock',
                                            header: 'Stock',
                                            sortable: true,
                                            headerClassName: 'justify-end',
                                            className: 'text-right tabular-nums',
                                            render: (row) => (
                                                <span
                                                    className={
                                                        row.stock === 0
                                                            ? 'text-destructive'
                                                            : row.stock < 10
                                                              ? 'text-amber-600'
                                                              : ''
                                                    }
                                                >
                                                    {row.stock}
                                                </span>
                                            ),
                                        },
                                        {
                                            key: 'price',
                                            header: 'Price',
                                            sortable: true,
                                            headerClassName: 'justify-end',
                                            className: 'text-right tabular-nums',
                                            render: (row) =>
                                                `$${row.price.toFixed(2)}`,
                                        },
                                        {
                                            key: 'status',
                                            header: 'Status',
                                            sortable: true,
                                            render: (row) => (
                                                <Badge
                                                    variant={
                                                        row.status === 'active'
                                                            ? 'default'
                                                            : row.status ===
                                                                'archived'
                                                              ? 'destructive'
                                                              : 'outline'
                                                    }
                                                >
                                                    {row.status}
                                                </Badge>
                                            ),
                                        },
                                    ] satisfies DataTableColumn<Product>[]
                                }
                                filters={
                                    [
                                        {
                                            key: 'category',
                                            label: 'Category',
                                            type: 'select',
                                            placeholder: 'All categories',
                                            options: [
                                                { label: 'Coffee', value: 'coffee' },
                                                { label: 'Tea', value: 'tea' },
                                                { label: 'Pastry', value: 'pastry' },
                                                { label: 'Merch', value: 'merch' },
                                                { label: 'Syrup', value: 'syrup' },
                                            ],
                                        },
                                        {
                                            key: 'status',
                                            label: 'Status',
                                            type: 'select',
                                            placeholder: 'Any status',
                                            options: [
                                                { label: 'Active', value: 'active' },
                                                { label: 'Draft', value: 'draft' },
                                                { label: 'Archived', value: 'archived' },
                                            ],
                                        },
                                        {
                                            key: 'price',
                                            label: 'Price range',
                                            type: 'range',
                                            step: 0.5,
                                            formatValue: (v) => `$${v.toFixed(2)}`,
                                        },
                                        {
                                            key: 'user_id',
                                            label: 'Added by user',
                                            type: 'async-select',
                                            searchUrl: '/app/users/search',
                                            placeholder: 'Any user',
                                        },
                                    ] satisfies DataTableFilter[]
                                }
                            />
                        }
                        code={`import { DataTable, type DataTableColumn, type DataTableFilter, type PaginationData } from '@/components/data-table/data-table';

// Controlled state lives in the parent; callbacks call the server.
<DataTable<Product>
    tableId="demo-products"
    data={rowsFromServer}
    pagination={paginationFromServer}
    onPageChange={(page) => router.reload({ only: ['products'], data: { page } })}
    onSort={(column, direction) => router.reload({ data: { sort: column, dir: direction } })}
    onFilter={(filters) => router.reload({ data: filters })}
    onSearch={(q) => router.reload({ data: { q } })}
    onExport={(filters, q) => router.post('/products/export', { filters, q })}
    columns={[
        { key: 'sku',   header: 'SKU',   sortable: true },
        { key: 'name',  header: 'Name',  sortable: true },
        { key: 'price', header: 'Price', sortable: true,
          headerClassName: 'justify-end', className: 'text-right tabular-nums',
          render: (r) => \`$\${r.price.toFixed(2)}\` },
    ]}
    filters={[
        { key: 'category', label: 'Category', type: 'select', options: [...] },
        { key: 'price',    label: 'Price',    type: 'range',  step: 0.5,
          formatValue: (v) => \`$\${v.toFixed(2)}\` },
        { key: 'user_id',  label: 'Added by', type: 'async-select',
          searchUrl: '/app/users/search' },
    ]}
/>`}
                    />

                    <ComponentSection
                        id="local-data-table"
                        block
                        title="LocalDataTable (client-side)"
                        description="Takes an in-memory array and handles search, filter, sort, paginate, and CSV export entirely in the browser. Use for static or pre-fetched datasets. searchKeys defines which columns the search box matches. exportable builds a CSV from the currently filtered rows."
                        preview={
                            <LocalDataTable<Product>
                                tableId="demo-products-local"
                                data={allProducts}
                                searchKeys={['name', 'sku']}
                                searchPlaceholder="Search by name or SKU…"
                                exportable
                                exportFilename="products"
                                pageSize={5}
                                columns={
                                    [
                                        {
                                            key: 'sku',
                                            header: 'SKU',
                                            sortable: true,
                                            render: (r) => (
                                                <span className="font-mono text-xs">
                                                    {r.sku}
                                                </span>
                                            ),
                                        },
                                        {
                                            key: 'name',
                                            header: 'Name',
                                            sortable: true,
                                            render: (r) => r.name,
                                        },
                                        {
                                            key: 'category',
                                            header: 'Category',
                                            sortable: true,
                                            render: (r) => (
                                                <Badge variant="secondary">
                                                    {r.category}
                                                </Badge>
                                            ),
                                        },
                                        {
                                            key: 'stock',
                                            header: 'Stock',
                                            sortable: true,
                                            className: 'text-right tabular-nums',
                                            render: (r) => r.stock,
                                        },
                                        {
                                            key: 'price',
                                            header: 'Price',
                                            sortable: true,
                                            className: 'text-right tabular-nums',
                                            render: (r) => `$${r.price.toFixed(2)}`,
                                            exportValue: (r) => r.price,
                                        },
                                        {
                                            key: 'created_at',
                                            header: 'Added',
                                            sortable: true,
                                            render: (r) =>
                                                new Date(r.created_at)
                                                    .toISOString()
                                                    .slice(0, 10),
                                        },
                                    ] satisfies LocalTableColumn<Product>[]
                                }
                                filters={
                                    [
                                        {
                                            key: 'category',
                                            label: 'Category',
                                            type: 'select',
                                            placeholder: 'All categories',
                                            options: [
                                                { label: 'Coffee', value: 'coffee' },
                                                { label: 'Tea', value: 'tea' },
                                                { label: 'Pastry', value: 'pastry' },
                                                { label: 'Merch', value: 'merch' },
                                                { label: 'Syrup', value: 'syrup' },
                                            ],
                                        },
                                        {
                                            key: 'created_at',
                                            label: 'Added between',
                                            type: 'daterange',
                                            placeholder: 'Any date',
                                        },
                                    ] satisfies LocalTableFilter<Product>[]
                                }
                            />
                        }
                        code={`import { LocalDataTable, type LocalTableColumn, type LocalTableFilter } from '@/components/local-data-table';

<LocalDataTable<Product>
    tableId="demo-products-local"
    data={products}
    searchKeys={['name', 'sku']}
    exportable
    exportFilename="products"
    pageSize={15}
    columns={[
        { key: 'sku',   header: 'SKU',   sortable: true, render: (r) => r.sku },
        { key: 'price', header: 'Price', sortable: true,
          render: (r) => \`$\${r.price.toFixed(2)}\`,
          exportValue: (r) => r.price },
    ]}
    filters={[
        { key: 'category',   label: 'Category', type: 'select',
          options: [{ label: 'Coffee', value: 'coffee' }, ...] },
        { key: 'created_at', label: 'Added between', type: 'daterange' },
    ]}
/>`}
                    />
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-lg">
                            <Mail className="mr-2 inline size-4" />
                            Need another component?
                        </CardTitle>
                        <CardDescription>
                            Add the Radix primitive via pnpm, then create a
                            wrapper in{' '}
                            <code className="rounded bg-muted px-1 text-xs">
                                resources/js/components/ui
                            </code>{' '}
                            following the existing shadcn new-york style.
                            Register a section on this page so the rest of the
                            team can find it.
                        </CardDescription>
                    </CardHeader>
                </Card>
            </div>
        </>
    );
}

SharedComponents.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Shared Components', href: sharedComponents() },
    ],
};
