import { usePage } from '@inertiajs/react';
import {
    ArrowRightLeft,
    Bell,
    BookOpen,
    Box,
    Bug,
    Building,
    ClipboardList,
    Code,
    CreditCard,
    Database,
    DollarSign,
    Download,
    Flag,
    Gauge,
    Globe,
    HelpCircle,
    History,
    Image as ImageIcon,
    Inbox,
    KeyRound,
    LayoutGrid,
    Lock,
    Mail,
    MailPlus,
    Monitor,
    MonitorSmartphone,
    Moon,
    RefreshCcw,
    Repeat,
    Search,
    Settings2,
    Shield,
    ShieldCheck,
    Siren,
    SlidersHorizontal,
    Sparkles,
    Star,
    Tag,
    UserCog,
    Users,
    Wand2,
    Webhook,
    Wrench,
    Zap,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { Card, CardContent } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import type { BlockComponentProps, CmsRefs, FeatureRef } from '../types';

type Attrs = {
    title?: string;
    subtitle?: string;
    columns?: 1 | 2 | 3 | 4;
    feature_ids?: number[];
};

type ShareProps = { cmsRefs?: CmsRefs };

const ICONS: Record<string, LucideIcon> = {
    // Foundation (kept in sync with CmsFeaturesSeeder).
    building: Building,
    'credit-card': CreditCard,
    shield: Shield,
    code: Code,
    mail: Mail,
    lock: Lock,
    // Content & marketing CMS.
    'layout-grid': LayoutGrid,
    image: ImageIcon,
    'settings-2': Settings2,
    'book-open': BookOpen,
    'mail-plus': MailPlus,
    inbox: Inbox,
    'arrow-right-left': ArrowRightLeft,
    search: Search,
    globe: Globe,
    history: History,
    monitor: Monitor,
    gauge: Gauge,
    // Auth & identity.
    users: Users,
    sparkles: Sparkles,
    'shield-check': ShieldCheck,
    'monitor-smartphone': MonitorSmartphone,
    siren: Siren,
    // API & integrations.
    'key-round': KeyRound,
    webhook: Webhook,
    flag: Flag,
    // Operations.
    'user-cog': UserCog,
    'clipboard-list': ClipboardList,
    repeat: Repeat,
    'sliders-horizontal': SlidersHorizontal,
    // Billing extras.
    'refresh-ccw': RefreshCcw,
    'dollar-sign': DollarSign,
    tag: Tag,
    // Compliance & ops.
    download: Download,
    database: Database,
    bug: Bug,
    // DX & polish.
    moon: Moon,
    'wand-2': Wand2,
    // Extras kept for back-compat / future use.
    zap: Zap,
    bell: Bell,
    wrench: Wrench,
    star: Star,
    'help-circle': HelpCircle,
    box: Box,
};

const COL_CLASS: Record<number, string> = {
    1: 'md:grid-cols-1',
    2: 'md:grid-cols-2',
    3: 'md:grid-cols-2 lg:grid-cols-3',
    4: 'md:grid-cols-2 lg:grid-cols-4',
};

export default function FeatureGridBlock({ block }: BlockComponentProps<Attrs>) {
    const { cmsRefs } = usePage<ShareProps>().props;
    const features: FeatureRef[] = (block.attrs.feature_ids ?? [])
        .map((id) => cmsRefs?.features?.[id])
        .filter((f): f is FeatureRef => Boolean(f));

    if (features.length === 0) {
return null;
}

    const colClass = COL_CLASS[block.attrs.columns ?? 3] ?? COL_CLASS[3];

    return (
        <section
            id="features"
            className="border-y border-border/40 bg-muted/20 py-20"
            data-test="block-feature-grid"
        >
            <div className="mx-auto w-full max-w-6xl px-4 md:px-6">
                {(block.attrs.title || block.attrs.subtitle) && (
                    <div className="mx-auto max-w-2xl text-center">
                        {block.attrs.title && (
                            <h2 className="text-3xl font-semibold md:text-4xl">{block.attrs.title}</h2>
                        )}
                        {block.attrs.subtitle && (
                            <p className="mt-4 text-muted-foreground">{block.attrs.subtitle}</p>
                        )}
                    </div>
                )}

                <div className={cn('mt-12 grid gap-4', colClass)}>
                    {features.map((feature) => {
                        const Icon = ICONS[feature.icon ?? ''] ?? Shield;

                        return (
                            <Card key={feature.id} className="h-full border-border/60" data-test="feature-card">
                                <CardContent className="pt-6">
                                    <div className="mb-4 inline-flex size-10 items-center justify-center rounded-md bg-primary/10 text-primary">
                                        <Icon className="size-5" />
                                    </div>
                                    <h3 className="text-lg font-semibold">{feature.title}</h3>
                                    {feature.description && (
                                        <p className="mt-2 text-sm text-muted-foreground">
                                            {feature.description}
                                        </p>
                                    )}
                                </CardContent>
                            </Card>
                        );
                    })}
                </div>
            </div>
        </section>
    );
}
