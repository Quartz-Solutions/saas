import { Form, Head, Link } from '@inertiajs/react';
import {
    BadgeCheck,
    Building2,
    Download,
    KeyRound,
    LogIn,
    LogOut,
    Mail,
    Pause,
    Play,
    ShieldCheck,
    ShieldX,
} from 'lucide-react';
import { useState } from 'react';
import UsersAdminController from '@/actions/App/Http/Controllers/Admin/UsersAdminController';
import { ActionsMenu  } from '@/components/admin/entity-detail/actions-menu';
import type {ActionItem} from '@/components/admin/entity-detail/actions-menu';
import { ActivityPanel } from '@/components/admin/entity-detail/activity-panel';
import { EntityHeader } from '@/components/admin/entity-detail/entity-header';
import { EntityHeroBanner } from '@/components/admin/entity-detail/entity-hero-banner';
import { FactCard, FactGrid, Mono } from '@/components/admin/entity-detail/fact-card';
import { TabBar  } from '@/components/admin/entity-detail/tab-bar';
import type {TabSpec} from '@/components/admin/entity-detail/tab-bar';
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
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { cn, formatDateTime } from '@/lib/utils';
import { index as adminAuditIndex } from '@/routes/admin/audit';
import { show as adminTenantsShow } from '@/routes/admin/tenants';
import { index as adminUsersIndex } from '@/routes/admin/users';

type UserProp = {
    id: number;
    name: string;
    email: string;
    avatar_path: string | null;
    locale: string;
    timezone: string;
    last_login_at: string | null;
    last_seen_at: string | null;
    last_login_ip: string | null;
    suspended_at: string | null;
    force_password_reset: boolean;
    email_verified_at: string | null;
    two_factor_confirmed_at: string | null;
    has_recovery_codes: boolean;
    roles: string[];
    created_at: string | null;
    sessions_count: number;
    tokens_count: number;
    is_super_admin: boolean;
    current_tenant: { id: number; slug: string; name: string } | null;
};

type MembershipRow = {
    membership_id: number;
    tenant: { id: number; slug: string; name: string } | null;
    is_owner: boolean;
    joined_at: string | null;
};

type SessionRow = {
    id: string;
    ip: string | null;
    user_agent: string | null;
    last_activity: string | null;
};

type LoginRow = {
    id: number;
    outcome: string;
    method: string | null;
    ip: string | null;
    created_at: string | null;
};

type AuditRow = {
    id: number;
    action: string;
    auditable_type: string | null;
    auditable_id: number | null;
    new_values: Record<string, unknown> | null;
    created_at: string | null;
};

type WebhookRow = {
    id: number;
    gateway: string | null;
    event_type: string;
    status: string;
    created_at: string | null;
};

type TokenRow = {
    id: number;
    name: string;
    abilities: string[];
    last_used_at: string | null;
    created_at: string | null;
};

type SocialRow = {
    id: number;
    provider: string;
    email: string | null;
    name: string | null;
    created_at: string | null;
};

type NotifPref = { event_type: string; channel: string; enabled: boolean };

type Props = {
    user: UserProp;
    memberships: MembershipRow[];
    sessions: SessionRow[];
    loginHistory: LoginRow[];
    auditLog: AuditRow[];
    webhookEvents: WebhookRow[];
    tokens: TokenRow[];
    socialAccounts: SocialRow[];
    notificationPreferences: NotifPref[];
};

function getTab(): string {
    if (typeof window === 'undefined') {
return 'overview';
}

    return new URL(window.location.href).searchParams.get('tab') || 'overview';
}

export default function AdminUsersShow({
    user,
    memberships,
    sessions,
    loginHistory,
    auditLog,
    webhookEvents,
    tokens,
    socialAccounts,
    notificationPreferences,
}: Props) {
    const [tab, setTab] = useState<string>(getTab());
    const [activeAction, setActiveAction] = useState<
        | null
        | 'suspend'
        | 'restore'
        | 'resend_verification'
        | 'force_password_reset'
        | 'disable_two_factor'
        | 'revoke_sessions'
        | 'revoke_tokens'
        | 'grant_super_admin'
        | 'revoke_super_admin'
        | 'impersonate'
    >(null);

    const isSuspended = user.suspended_at !== null;
    const isVerified = user.email_verified_at !== null;
    const has2FA = user.two_factor_confirmed_at !== null;

    const heroValue = user.last_login_at ? formatDateTime(user.last_login_at) : 'Never logged in';

    const heroHelper = user.sessions_count > 0
        ? `${user.sessions_count} active session${user.sessions_count === 1 ? '' : 's'}`
        : 'No active sessions.';

    const actions: ActionItem[] = [
        {
            label: 'Impersonate user',
            icon: <LogIn className="size-4" />,
            onSelect: () => setActiveAction('impersonate'),
            'data-test': 'action-impersonate',
        },
        {
            label: 'Resend verification email',
            icon: <Mail className="size-4" />,
            disabled: isVerified,
            onSelect: () => setActiveAction('resend_verification'),
            'data-test': 'action-resend-verification',
        },
        {
            label: 'Force password reset',
            icon: <KeyRound className="size-4" />,
            onSelect: () => setActiveAction('force_password_reset'),
            'data-test': 'action-force-password-reset',
        },
        {
            label: 'Disable 2FA',
            icon: <ShieldX className="size-4" />,
            disabled: !has2FA,
            destructive: true,
            onSelect: () => setActiveAction('disable_two_factor'),
            'data-test': 'action-disable-2fa',
        },
        {
            label: 'Revoke all sessions',
            icon: <LogOut className="size-4" />,
            destructive: true,
            onSelect: () => setActiveAction('revoke_sessions'),
            'data-test': 'action-revoke-sessions',
        },
        {
            label: 'Revoke all API tokens',
            icon: <KeyRound className="size-4" />,
            destructive: true,
            disabled: user.tokens_count === 0,
            onSelect: () => setActiveAction('revoke_tokens'),
            'data-test': 'action-revoke-tokens',
        },
        user.is_super_admin
            ? {
                  label: 'Revoke Super Admin',
                  icon: <ShieldX className="size-4" />,
                  destructive: true,
                  onSelect: () => setActiveAction('revoke_super_admin'),
                  'data-test': 'action-revoke-super-admin',
              }
            : {
                  label: 'Grant Super Admin',
                  icon: <ShieldCheck className="size-4" />,
                  onSelect: () => setActiveAction('grant_super_admin'),
                  'data-test': 'action-grant-super-admin',
              },
        {
            label: 'GDPR data export',
            icon: <Download className="size-4" />,
            onSelect: () => {
                window.location.href = UsersAdminController.gdprExport.url({ user: user.id });
            },
            'data-test': 'action-gdpr-export',
        },
        isSuspended
            ? {
                  label: 'Restore user',
                  icon: <Play className="size-4" />,
                  onSelect: () => setActiveAction('restore'),
                  'data-test': 'action-restore',
              }
            : {
                  label: 'Suspend user',
                  icon: <Pause className="size-4" />,
                  destructive: true,
                  onSelect: () => setActiveAction('suspend'),
                  'data-test': 'action-suspend',
              },
    ];

    const tabs: TabSpec[] = [
        { value: 'overview', label: 'Overview' },
        { value: 'tenants', label: 'Tenants', badge: memberships.length },
        { value: 'security', label: 'Security' },
        { value: 'activity', label: 'Activity' },
        { value: 'danger', label: 'Danger zone', danger: true },
    ];

    return (
        <>
            <Head title={`${user.name} — User`} />

            <div className="flex flex-col gap-6">
                <EntityHeader
                    backHref={adminUsersIndex().url}
                    backLabel="Users"
                    breadcrumb={[
                        { label: 'Users', href: adminUsersIndex().url },
                        { label: user.name },
                    ]}
                    avatarUrl={user.avatar_path}
                    avatarFallback={user.name.slice(0, 2).toUpperCase()}
                    name={user.name}
                    subtitle={
                        <span className="flex flex-wrap items-center gap-2">
                            <span>{user.email}</span>
                            {isVerified ? (
                                <Badge variant="outline" className="gap-1 text-[10px]">
                                    <BadgeCheck className="size-3" /> Verified
                                </Badge>
                            ) : (
                                <Badge variant="outline" className="border-amber-500/40 text-amber-700 text-[10px]">
                                    Unverified
                                </Badge>
                            )}
                            {has2FA && (
                                <Badge variant="outline" className="gap-1 text-[10px]">
                                    <KeyRound className="size-3" /> 2FA
                                </Badge>
                            )}
                            {user.is_super_admin && (
                                <Badge variant="default" className="gap-1 text-[10px]">
                                    <ShieldCheck className="size-3" /> Super Admin
                                </Badge>
                            )}
                            {isSuspended && (
                                <Badge variant="destructive" className="text-[10px]">
                                    Suspended
                                </Badge>
                            )}
                        </span>
                    }
                    statusDot={
                        isSuspended ? 'suspended' : isVerified ? 'active' : 'unverified'
                    }
                    actions={
                        <ActionsMenu
                            items={actions}
                            leading={
                                <Button
                                    size="sm"
                                    onClick={() => setActiveAction('impersonate')}
                                    data-test="impersonate-quick"
                                >
                                    <LogIn className="size-4" />
                                    Impersonate
                                </Button>
                            }
                        />
                    }
                />

                <EntityHeroBanner
                    label="Last activity"
                    value={heroValue}
                    pill={
                        isSuspended
                            ? { label: 'Suspended', variant: 'destructive' }
                            : isVerified
                              ? { label: 'Active', variant: 'default' }
                              : { label: 'Pending verification', variant: 'secondary' }
                    }
                    helper={heroHelper}
                />

                <TabBar tabs={tabs} value={tab} onChange={setTab} />

                {tab === 'overview' && (
                    <OverviewLayout
                        user={user}
                        memberships={memberships}
                        loginHistory={loginHistory}
                        auditLog={auditLog}
                        tokens={tokens}
                        sessions={sessions}
                        socialAccounts={socialAccounts}
                        notificationPreferences={notificationPreferences}
                    />
                )}

                {tab === 'tenants' && (
                    <TenantsTab memberships={memberships} user={user} />
                )}

                {tab === 'security' && (
                    <SecurityTab
                        user={user}
                        sessions={sessions}
                        tokens={tokens}
                        socialAccounts={socialAccounts}
                    />
                )}

                {tab === 'activity' && (
                    <ActivityTab
                        loginHistory={loginHistory}
                        auditLog={auditLog}
                        webhookEvents={webhookEvents}
                    />
                )}

                {tab === 'danger' && (
                    <DangerTab
                        user={user}
                        onSuspend={() => setActiveAction('suspend')}
                        onRestore={() => setActiveAction('restore')}
                    />
                )}
            </div>

            <SimpleConfirmDialog
                open={activeAction === 'resend_verification'}
                onClose={() => setActiveAction(null)}
                title={`Resend verification email to ${user.email}?`}
                description="They will receive a fresh verification link."
                action={UsersAdminController.resendVerification.form({ user: user.id })}
                cta="Resend"
            />
            <SimpleConfirmDialog
                open={activeAction === 'force_password_reset'}
                onClose={() => setActiveAction(null)}
                title="Force password reset?"
                description="A reset link will be sent and the user must reset on next login."
                action={UsersAdminController.forcePasswordReset.form({ user: user.id })}
                cta="Send reset link"
            />
            <SimpleConfirmDialog
                open={activeAction === 'disable_two_factor'}
                onClose={() => setActiveAction(null)}
                title="Disable two-factor authentication?"
                description="The user will lose their TOTP secret and recovery codes. They can re-enable from Settings."
                action={UsersAdminController.disableTwoFactor.form({ user: user.id })}
                cta="Disable 2FA"
                destructive
            />
            <SimpleConfirmDialog
                open={activeAction === 'revoke_sessions'}
                onClose={() => setActiveAction(null)}
                title="Revoke all sessions?"
                description="The user will be signed out everywhere immediately."
                action={UsersAdminController.revokeSessions.form({ user: user.id })}
                cta="Revoke sessions"
                destructive
            />
            <SimpleConfirmDialog
                open={activeAction === 'revoke_tokens'}
                onClose={() => setActiveAction(null)}
                title="Revoke all API tokens?"
                description="All Sanctum personal access tokens are deleted. Cannot be undone."
                action={UsersAdminController.revokeTokens.form({ user: user.id })}
                cta="Revoke tokens"
                destructive
            />
            <SimpleConfirmDialog
                open={activeAction === 'grant_super_admin'}
                onClose={() => setActiveAction(null)}
                title="Grant Super Admin?"
                description="The user gains full administrative access including impersonation."
                action={UsersAdminController.grantSuperAdmin.form({ user: user.id })}
                cta="Grant"
            />
            <SimpleConfirmDialog
                open={activeAction === 'revoke_super_admin'}
                onClose={() => setActiveAction(null)}
                title="Revoke Super Admin?"
                description="The user loses administrative access. Cannot revoke from yourself."
                action={UsersAdminController.revokeSuperAdmin.form({ user: user.id })}
                cta="Revoke"
                destructive
            />
            <SimpleConfirmDialog
                open={activeAction === 'impersonate'}
                onClose={() => setActiveAction(null)}
                title={`Impersonate ${user.email}?`}
                description="You will be logged in as this user. Return via the impersonation banner."
                action={UsersAdminController.impersonate.form({ user: user.id })}
                cta="Impersonate"
            />
            <ReasonDialog
                open={activeAction === 'suspend'}
                onClose={() => setActiveAction(null)}
                title={`Suspend ${user.email}?`}
                description="Signs the user out of all sessions. Login is blocked until restored."
                action={UsersAdminController.suspend.form({ user: user.id })}
                cta="Suspend"
                destructive
            />
            <SimpleConfirmDialog
                open={activeAction === 'restore'}
                onClose={() => setActiveAction(null)}
                title={`Restore ${user.email}?`}
                description="The user can sign in again immediately."
                action={UsersAdminController.restore.form({ user: user.id })}
                cta="Restore"
            />
        </>
    );
}

function OverviewLayout({
    user,
    memberships,
    loginHistory,
    auditLog,
    tokens,
    sessions,
    socialAccounts,
    notificationPreferences,
}: {
    user: UserProp;
    memberships: MembershipRow[];
    loginHistory: LoginRow[];
    auditLog: AuditRow[];
    tokens: TokenRow[];
    sessions: SessionRow[];
    socialAccounts: SocialRow[];
    notificationPreferences: NotifPref[];
}) {
    return (
        <div className="grid grid-cols-1 gap-6 lg:grid-cols-5">
            <div className="flex flex-col gap-4 lg:col-span-2">
                <FactCard title="Personal details">
                    <FactGrid
                        rows={[
                            ['Name', user.name],
                            ['Email', user.email],
                            ['Locale', user.locale],
                            ['Timezone', user.timezone],
                            ['Member since', user.created_at ? formatDateTime(user.created_at) : '—'],
                            ['Last login', user.last_login_at ? formatDateTime(user.last_login_at) : '—'],
                            ['Last IP', user.last_login_ip ? <Mono key="ip">{user.last_login_ip}</Mono> : '—'],
                            user.suspended_at && [
                                'Suspended at',
                                <span key="s" className="text-destructive">
                                    {formatDateTime(user.suspended_at)}
                                </span>,
                            ],
                        ]}
                    />
                </FactCard>

                <FactCard title="Security">
                    <FactGrid
                        rows={[
                            ['Verified', user.email_verified_at ? formatDateTime(user.email_verified_at) : 'No'],
                            ['2FA enabled', user.two_factor_confirmed_at ? formatDateTime(user.two_factor_confirmed_at) : 'No'],
                            ['Recovery codes', user.has_recovery_codes ? 'Generated' : 'No'],
                            ['Active sessions', user.sessions_count],
                            ['API tokens', user.tokens_count],
                            ['Force password reset', user.force_password_reset ? 'Yes' : 'No'],
                        ]}
                    />
                </FactCard>

                <FactCard title="Tenants">
                    {memberships.length === 0 ? (
                        <p className="text-sm text-muted-foreground">No tenant memberships.</p>
                    ) : (
                        <ul className="flex flex-col gap-2 text-sm">
                            {memberships.map((m) => (
                                <li
                                    key={m.membership_id}
                                    className="flex items-center justify-between gap-2"
                                >
                                    {m.tenant ? (
                                        <Link
                                            href={adminTenantsShow({ tenant: m.tenant.id })}
                                            className="flex items-center gap-2 hover:underline"
                                        >
                                            <Building2 className="size-4 text-muted-foreground" />
                                            <span>{m.tenant.name}</span>
                                            <Mono>/t/{m.tenant.slug}</Mono>
                                        </Link>
                                    ) : (
                                        <span className="text-muted-foreground">(deleted tenant)</span>
                                    )}
                                    {m.is_owner && (
                                        <Badge variant="secondary" className="text-[10px]">
                                            Owner
                                        </Badge>
                                    )}
                                </li>
                            ))}
                        </ul>
                    )}
                </FactCard>

                <FactCard title="API tokens">
                    {tokens.length === 0 ? (
                        <p className="text-sm text-muted-foreground">No API tokens.</p>
                    ) : (
                        <ul className="flex flex-col gap-2 text-sm">
                            {tokens.map((t) => (
                                <li key={t.id} className="flex items-center justify-between gap-2">
                                    <span className="flex flex-col">
                                        <span className="font-medium">{t.name}</span>
                                        <span className="text-xs text-muted-foreground">
                                            Last used{' '}
                                            {t.last_used_at ? formatDateTime(t.last_used_at) : 'never'}
                                        </span>
                                    </span>
                                    <Mono>{(t.abilities ?? []).length} abilities</Mono>
                                </li>
                            ))}
                        </ul>
                    )}
                </FactCard>

                <FactCard title="Linked social accounts">
                    {socialAccounts.length === 0 ? (
                        <p className="text-sm text-muted-foreground">None linked.</p>
                    ) : (
                        <ul className="flex flex-col gap-2 text-sm">
                            {socialAccounts.map((s) => (
                                <li key={s.id} className="flex items-center gap-2">
                                    <Badge variant="outline">{s.provider}</Badge>
                                    <span className="text-xs text-muted-foreground">{s.email ?? s.name}</span>
                                </li>
                            ))}
                        </ul>
                    )}
                </FactCard>

                <FactCard title="Notification preferences">
                    {notificationPreferences.length === 0 ? (
                        <p className="text-sm text-muted-foreground">Defaults only.</p>
                    ) : (
                        <ul className="flex flex-col gap-1 text-xs">
                            {notificationPreferences.map((p, i) => (
                                <li key={i} className="flex items-center justify-between gap-2">
                                    <Mono>{p.event_type}.{p.channel}</Mono>
                                    <Badge variant={p.enabled ? 'default' : 'outline'} className="text-[10px]">
                                        {p.enabled ? 'on' : 'off'}
                                    </Badge>
                                </li>
                            ))}
                        </ul>
                    )}
                </FactCard>
            </div>

            <div className="flex flex-col gap-4 lg:col-span-3">
                <ActivityPanel
                    title="Login history"
                    rows={loginHistory}
                    rowKey={(r) => r.id}
                    columns={[
                        { key: 'date', header: 'Date', render: (r) => <span className="font-mono text-xs">{r.created_at ? formatDateTime(r.created_at) : '—'}</span> },
                        { key: 'ip', header: 'IP', render: (r) => <Mono>{r.ip ?? '—'}</Mono> },
                        { key: 'method', header: 'Method', render: (r) => <Mono>{r.method ?? 'password'}</Mono> },
                        {
                            key: 'outcome',
                            header: 'Outcome',
                            render: (r) => (
                                <Badge variant={r.outcome === 'succeeded' ? 'default' : 'destructive'}>
                                    {r.outcome}
                                </Badge>
                            ),
                        },
                    ]}
                    emptyMessage="No login attempts."
                />

                <ActivityPanel
                    title="Audit log"
                    description="Actions performed by, or against, this user."
                    viewAllHref={adminAuditIndex({ query: { user_id: user.id } }).url}
                    rows={auditLog}
                    rowKey={(r) => r.id}
                    columns={[
                        { key: 'date', header: 'Date', render: (r) => <span className="font-mono text-xs">{r.created_at ? formatDateTime(r.created_at) : '—'}</span> },
                        { key: 'action', header: 'Action', render: (r) => <span className="font-mono text-xs">{r.action}</span> },
                        { key: 'target', header: 'Target', render: (r) => r.auditable_type ? <Mono>{r.auditable_type.split('\\').pop()}#{r.auditable_id}</Mono> : '—' },
                    ]}
                    emptyMessage="No audit activity."
                />

                <ActivityPanel
                    title="Active sessions"
                    rows={sessions}
                    rowKey={(r) => r.id}
                    columns={[
                        { key: 'last', header: 'Last active', render: (r) => <span className="font-mono text-xs">{r.last_activity ? formatDateTime(r.last_activity) : '—'}</span> },
                        { key: 'ip', header: 'IP', render: (r) => <Mono>{r.ip ?? '—'}</Mono> },
                        { key: 'ua', header: 'User agent', render: (r) => (
                            <span className="block truncate font-mono text-[11px] text-muted-foreground" title={r.user_agent ?? ''}>
                                {(r.user_agent ?? '').slice(0, 60) || '—'}
                            </span>
                        ) },
                    ]}
                    emptyMessage="No active sessions."
                />
            </div>
        </div>
    );
}

function TenantsTab({ memberships }: { memberships: MembershipRow[]; user: UserProp }) {
    return (
        <ActivityPanel
            title={`Tenants (${memberships.length})`}
            rows={memberships}
            rowKey={(r) => r.membership_id}
            columns={[
                {
                    key: 'tenant',
                    header: 'Tenant',
                    render: (r) =>
                        r.tenant ? (
                            <Link
                                href={adminTenantsShow({ tenant: r.tenant.id })}
                                className="flex items-center gap-2 hover:underline"
                            >
                                <Building2 className="size-4 text-muted-foreground" />
                                <span>{r.tenant.name}</span>
                                <Mono>/t/{r.tenant.slug}</Mono>
                            </Link>
                        ) : (
                            <span className="text-muted-foreground">(deleted)</span>
                        ),
                },
                {
                    key: 'role',
                    header: 'Role',
                    render: (r) =>
                        r.is_owner ? (
                            <Badge variant="secondary">Owner</Badge>
                        ) : (
                            <Badge variant="outline">Member</Badge>
                        ),
                },
                {
                    key: 'joined',
                    header: 'Joined',
                    render: (r) => (
                        <span className="font-mono text-xs text-muted-foreground">
                            {r.joined_at ? formatDateTime(r.joined_at) : '—'}
                        </span>
                    ),
                },
            ]}
            emptyMessage="Not a member of any tenant."
        />
    );
}

function SecurityTab({
    user,
    sessions,
    tokens,
    socialAccounts,
}: {
    user: UserProp;
    sessions: SessionRow[];
    tokens: TokenRow[];
    socialAccounts: SocialRow[];
}) {
    return (
        <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
            <FactCard title="Security summary">
                <FactGrid
                    rows={[
                        ['Email verified', user.email_verified_at ? formatDateTime(user.email_verified_at) : 'No'],
                        ['Two-factor', user.two_factor_confirmed_at ? formatDateTime(user.two_factor_confirmed_at) : 'No'],
                        ['Recovery codes', user.has_recovery_codes ? 'Generated' : 'No'],
                        ['Force password reset', user.force_password_reset ? 'Yes' : 'No'],
                    ]}
                />
            </FactCard>

            <ActivityPanel
                title="Active sessions"
                rows={sessions}
                rowKey={(r) => r.id}
                columns={[
                    { key: 'last', header: 'Last activity', render: (r) => <span className="font-mono text-xs">{r.last_activity ? formatDateTime(r.last_activity) : '—'}</span> },
                    { key: 'ip', header: 'IP', render: (r) => <Mono>{r.ip ?? '—'}</Mono> },
                ]}
                emptyMessage="No active sessions."
            />

            <ActivityPanel
                title={`API tokens (${tokens.length})`}
                rows={tokens}
                rowKey={(r) => r.id}
                columns={[
                    { key: 'name', header: 'Name', render: (r) => <span className="font-medium">{r.name}</span> },
                    { key: 'last', header: 'Last used', render: (r) => <span className="font-mono text-xs">{r.last_used_at ? formatDateTime(r.last_used_at) : 'never'}</span> },
                    { key: 'abil', header: 'Abilities', render: (r) => <Mono>{(r.abilities ?? []).length}</Mono> },
                ]}
                emptyMessage="No tokens."
            />

            <ActivityPanel
                title="Social accounts"
                rows={socialAccounts}
                rowKey={(r) => r.id}
                columns={[
                    { key: 'p', header: 'Provider', render: (r) => <Badge variant="outline">{r.provider}</Badge> },
                    { key: 'e', header: 'Email', render: (r) => r.email ?? '—' },
                    { key: 'when', header: 'Linked', render: (r) => <span className="font-mono text-xs">{r.created_at ? formatDateTime(r.created_at) : '—'}</span> },
                ]}
                emptyMessage="None linked."
            />
        </div>
    );
}

function ActivityTab({
    loginHistory,
    auditLog,
    webhookEvents,
}: {
    loginHistory: LoginRow[];
    auditLog: AuditRow[];
    webhookEvents: WebhookRow[];
}) {
    return (
        <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
            <ActivityPanel
                title="Login history"
                rows={loginHistory}
                rowKey={(r) => r.id}
                columns={[
                    { key: 'd', header: 'Date', render: (r) => <span className="font-mono text-xs">{r.created_at ? formatDateTime(r.created_at) : '—'}</span> },
                    { key: 'o', header: 'Outcome', render: (r) => <Badge variant={r.outcome === 'succeeded' ? 'default' : 'destructive'}>{r.outcome}</Badge> },
                    { key: 'i', header: 'IP', render: (r) => <Mono>{r.ip ?? '—'}</Mono> },
                ]}
                emptyMessage="No login history."
            />

            <ActivityPanel
                title="Audit log"
                rows={auditLog}
                rowKey={(r) => r.id}
                columns={[
                    { key: 'd', header: 'Date', render: (r) => <span className="font-mono text-xs">{r.created_at ? formatDateTime(r.created_at) : '—'}</span> },
                    { key: 'a', header: 'Action', render: (r) => <span className="font-mono text-xs">{r.action}</span> },
                ]}
                emptyMessage="No audit entries."
            />

            <ActivityPanel
                title="Recent webhook events"
                rows={webhookEvents}
                rowKey={(r) => r.id}
                columns={[
                    { key: 'd', header: 'Date', render: (r) => <span className="font-mono text-xs">{r.created_at ? formatDateTime(r.created_at) : '—'}</span> },
                    { key: 'g', header: 'Gateway', render: (r) => <Mono>{r.gateway ?? '—'}</Mono> },
                    { key: 'e', header: 'Event', render: (r) => <span className="font-mono text-xs">{r.event_type}</span> },
                    { key: 's', header: 'Status', render: (r) => <Badge variant={r.status === 'processed' ? 'default' : 'outline'}>{r.status}</Badge> },
                ]}
                emptyMessage="No events."
            />
        </div>
    );
}

function DangerTab({
    user,
    onSuspend,
    onRestore,
}: {
    user: UserProp;
    onSuspend: () => void;
    onRestore: () => void;
}) {
    return (
        <div className="flex flex-col gap-3">
            {user.suspended_at ? (
                <DangerRow
                    title="Restore user"
                    description="Lift the suspension and re-enable sign-in."
                    buttonLabel="Restore"
                    onClick={onRestore}
                    testId="danger-restore"
                />
            ) : (
                <DangerRow
                    title="Suspend user"
                    description="Sign out everywhere and block future logins until restored."
                    buttonLabel="Suspend"
                    onClick={onSuspend}
                    destructive
                    testId="danger-suspend"
                />
            )}
        </div>
    );
}

function DangerRow({
    title,
    description,
    buttonLabel,
    onClick,
    destructive,
    testId,
}: {
    title: string;
    description: string;
    buttonLabel: string;
    onClick: () => void;
    destructive?: boolean;
    testId: string;
}) {
    return (
        <div
            className={cn(
                'flex flex-col gap-3 rounded-md border p-4 sm:flex-row sm:items-center sm:justify-between',
                destructive && 'border-destructive/40 bg-destructive/5',
            )}
        >
            <div className="flex flex-col gap-1">
                <h3 className="text-sm font-medium">{title}</h3>
                <p className="text-xs text-muted-foreground">{description}</p>
            </div>
            <Button
                variant={destructive ? 'destructive' : 'outline'}
                size="sm"
                onClick={onClick}
                data-test={testId}
            >
                {buttonLabel}
            </Button>
        </div>
    );
}

function SimpleConfirmDialog({
    open,
    onClose,
    title,
    description,
    action,
    cta,
    destructive,
}: {
    open: boolean;
    onClose: () => void;
    title: string;
    description: string;
    action: { action: string; method: 'post' | 'delete' | 'put' | 'patch' | 'get' };
    cta: string;
    destructive?: boolean;
}) {
    return (
        <AlertDialog open={open} onOpenChange={(o) => !o && onClose()}>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>{title}</AlertDialogTitle>
                    <AlertDialogDescription>{description}</AlertDialogDescription>
                </AlertDialogHeader>
                <Form {...action} onSuccess={onClose}>
                    {({ processing }) => (
                        <AlertDialogFooter>
                            <Button type="button" variant="secondary" onClick={onClose}>
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                variant={destructive ? 'destructive' : 'default'}
                                disabled={processing}
                            >
                                {processing && <Spinner />}
                                {cta}
                            </Button>
                        </AlertDialogFooter>
                    )}
                </Form>
            </AlertDialogContent>
        </AlertDialog>
    );
}

function ReasonDialog({
    open,
    onClose,
    title,
    description,
    action,
    cta,
    destructive,
}: {
    open: boolean;
    onClose: () => void;
    title: string;
    description: string;
    action: { action: string; method: 'post' | 'delete' | 'put' | 'patch' | 'get' };
    cta: string;
    destructive?: boolean;
}) {
    return (
        <AlertDialog open={open} onOpenChange={(o) => !o && onClose()}>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>{title}</AlertDialogTitle>
                    <AlertDialogDescription>{description}</AlertDialogDescription>
                </AlertDialogHeader>
                <Form {...action} onSuccess={onClose}>
                    {({ processing }) => (
                        <>
                            <Textarea
                                name="reason"
                                rows={3}
                                placeholder="Reason (optional, recorded in audit log)"
                            />
                            <AlertDialogFooter>
                                <Button type="button" variant="secondary" onClick={onClose}>
                                    Cancel
                                </Button>
                                <Button
                                    type="submit"
                                    variant={destructive ? 'destructive' : 'default'}
                                    disabled={processing}
                                >
                                    {processing && <Spinner />}
                                    {cta}
                                </Button>
                            </AlertDialogFooter>
                        </>
                    )}
                </Form>
            </AlertDialogContent>
        </AlertDialog>
    );
}
