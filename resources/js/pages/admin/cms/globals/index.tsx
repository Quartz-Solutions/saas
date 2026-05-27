import { Head, Link } from '@inertiajs/react';
import { ChevronRight, Settings2 } from 'lucide-react';
import Heading from '@/components/heading';
import { Card, CardContent } from '@/components/ui/card';
import { edit as globalsEdit } from '@/routes/admin/cms/globals';

type Item = {
    key: string;
    label: string;
    description: string;
};

type Props = {
    globals: Item[];
};

export default function CmsGlobalsIndex({ globals }: Props) {
    return (
        <>
            <Head title="Globals — CMS" />
            <div className="flex h-full flex-1 flex-col gap-6">
                <Heading
                    title="Globals"
                    description="Site-wide singletons. Edit once, used everywhere on the public site."
                />

                <div className="grid gap-3 md:grid-cols-2">
                    {globals.map((item) => (
                        <Link
                            key={item.key}
                            href={globalsEdit({ key: item.key }).url}
                            data-test={`globals-link-${item.key}`}
                        >
                            <Card className="h-full transition-colors hover:bg-muted/30">
                                <CardContent className="flex items-start gap-3 pt-6">
                                    <div className="inline-flex size-9 items-center justify-center rounded-md bg-primary/10 text-primary">
                                        <Settings2 className="size-4" />
                                    </div>
                                    <div className="flex-1">
                                        <div className="flex items-center justify-between gap-2">
                                            <h3 className="font-medium">{item.label}</h3>
                                            <ChevronRight className="size-4 text-muted-foreground" />
                                        </div>
                                        <p className="mt-1 text-sm text-muted-foreground">
                                            {item.description}
                                        </p>
                                        <p className="mt-2 font-mono text-xs text-muted-foreground">{item.key}</p>
                                    </div>
                                </CardContent>
                            </Card>
                        </Link>
                    ))}
                </div>
            </div>
        </>
    );
}
