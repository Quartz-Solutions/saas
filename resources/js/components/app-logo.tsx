import { usePage } from '@inertiajs/react';
import BrandMark from '@/components/brand-mark';

type SharedProps = {
    name?: string;
    currentTenant: { name: string } | null;
};

export default function AppLogo() {
    const { currentTenant, name } = usePage<SharedProps>().props;
    const brand = currentTenant?.name ?? name ?? 'App';

    return (
        <>
            <BrandMark />
            <div className="ml-1 grid flex-1 text-left text-sm">
                <span
                    className="mb-0.5 truncate leading-tight font-semibold"
                    data-test="app-brand-name"
                >
                    {brand}
                </span>
            </div>
        </>
    );
}
