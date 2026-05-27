import type { PropsWithChildren } from 'react';

export default function AdminLayout({ children }: PropsWithChildren) {
    return (
        <div className="px-4 py-6">
            <div>{children}</div>
        </div>
    );
}
