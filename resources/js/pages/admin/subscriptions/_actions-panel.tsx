import { Form } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';

type Plan = {
    id: number;
    slug: string;
    name: string;
    price_cents: number;
    currency: string;
    billing_period: string;
};

type Reasons = {
    credit: Record<string, string>;
    comp: Record<string, string>;
    refund: Record<string, string>;
    cancellation: Record<string, string>;
    manual_payment_method: Record<string, string>;
};

type Props = {
    subscriptionId: number;
    cancelAtPeriodEnd: boolean;
    plans: Plan[];
    reasons: Reasons;
};

export default function ActionsPanel({
    subscriptionId,
    cancelAtPeriodEnd,
    plans,
    reasons,
}: Props) {
    const [open, setOpen] = useState<
        'change-plan' | 'cancel' | 'reactivate' | 'credit' | 'comp' | null
    >(null);
    const close = () => setOpen(null);

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-base">Admin actions</CardTitle>
                <CardDescription>
                    All actions audited with the current admin user + note.
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-2">
                <Button
                    variant="outline"
                    size="sm"
                    className="w-full"
                    onClick={() => setOpen('change-plan')}
                >
                    Change plan
                </Button>

                {cancelAtPeriodEnd ? (
                    <Button
                        variant="outline"
                        size="sm"
                        className="w-full"
                        onClick={() => setOpen('reactivate')}
                    >
                        Reactivate (un-cancel)
                    </Button>
                ) : (
                    <Button
                        variant="outline"
                        size="sm"
                        className="w-full"
                        onClick={() => setOpen('cancel')}
                    >
                        Cancel subscription
                    </Button>
                )}

                <Button
                    variant="outline"
                    size="sm"
                    className="w-full"
                    onClick={() => setOpen('credit')}
                >
                    Apply credit
                </Button>

                <Button
                    variant="outline"
                    size="sm"
                    className="w-full"
                    onClick={() => setOpen('comp')}
                >
                    Comp months
                </Button>

                <ChangePlanDialog
                    open={open === 'change-plan'}
                    onOpenChange={(v) => !v && close()}
                    plans={plans}
                    subscriptionId={subscriptionId}
                />
                <CancelDialog
                    open={open === 'cancel'}
                    onOpenChange={(v) => !v && close()}
                    reasons={reasons.cancellation}
                    subscriptionId={subscriptionId}
                />
                <ReactivateDialog
                    open={open === 'reactivate'}
                    onOpenChange={(v) => !v && close()}
                    subscriptionId={subscriptionId}
                />
                <CreditDialog
                    open={open === 'credit'}
                    onOpenChange={(v) => !v && close()}
                    reasons={reasons.credit}
                    subscriptionId={subscriptionId}
                />
                <CompDialog
                    open={open === 'comp'}
                    onOpenChange={(v) => !v && close()}
                    reasons={reasons.comp}
                    subscriptionId={subscriptionId}
                />
            </CardContent>
        </Card>
    );
}

function ReasonSelect({
    id,
    name,
    reasons,
    value,
    onValueChange,
}: {
    id: string;
    name: string;
    reasons: Record<string, string>;
    value: string;
    onValueChange: (v: string) => void;
}) {
    return (
        <>
            <Select value={value} onValueChange={onValueChange}>
                <SelectTrigger id={id} className="w-full">
                    <SelectValue placeholder="Select reason…" />
                </SelectTrigger>
                <SelectContent>
                    {Object.entries(reasons).map(([slug, label]) => (
                        <SelectItem key={slug} value={slug}>
                            {label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            <input type="hidden" name={name} value={value} />
        </>
    );
}

function NoteField({ name }: { name: string }) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={name}>Admin note (optional)</Label>
            <Textarea id={name} name={name} rows={2} maxLength={500} />
        </div>
    );
}

function ChangePlanDialog({
    open,
    onOpenChange,
    plans,
    subscriptionId,
}: {
    open: boolean;
    onOpenChange: (v: boolean) => void;
    plans: Plan[];
    subscriptionId: number;
}) {
    const [planId, setPlanId] = useState('');
    const [prorate, setProrate] = useState(true);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Change plan</DialogTitle>
                    <DialogDescription>
                        Move this subscription to a different plan. Pro-ration is recommended for
                        Stripe — leave on unless you've coordinated billing manually.
                    </DialogDescription>
                </DialogHeader>
                <Form
                    action={`/admin/subscriptions/${subscriptionId}/change-plan`}
                    method="post"
                    onSuccess={() => onOpenChange(false)}
                    disableWhileProcessing
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="change-plan-plan">New plan</Label>
                                <Select value={planId} onValueChange={setPlanId}>
                                    <SelectTrigger id="change-plan-plan" className="w-full">
                                        <SelectValue placeholder="Select plan…" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {plans.map((p) => (
                                            <SelectItem key={p.id} value={String(p.id)}>
                                                {p.name} ({p.slug}) — {p.price_cents / 100}{' '}
                                                {p.currency}/{p.billing_period}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <input type="hidden" name="plan_id" value={planId} />
                                <InputError message={errors.plan_id} />
                            </div>
                            <div className="flex items-center justify-between rounded-md border bg-muted/30 px-3 py-2">
                                <Label htmlFor="change-plan-prorate" className="text-sm">
                                    Pro-rate at gateway
                                </Label>
                                <input
                                    type="hidden"
                                    name="prorate"
                                    value={prorate ? '1' : '0'}
                                />
                                <Switch
                                    id="change-plan-prorate"
                                    checked={prorate}
                                    onCheckedChange={setProrate}
                                />
                            </div>
                            <NoteField name="admin_note" />
                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button type="button" variant="secondary">
                                        Cancel
                                    </Button>
                                </DialogClose>
                                <Button type="submit" disabled={processing || !planId}>
                                    Change plan
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function CancelDialog({
    open,
    onOpenChange,
    reasons,
    subscriptionId,
}: {
    open: boolean;
    onOpenChange: (v: boolean) => void;
    reasons: Record<string, string>;
    subscriptionId: number;
}) {
    const [reason, setReason] = useState('');
    const [immediately, setImmediately] = useState(false);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Cancel subscription</DialogTitle>
                    <DialogDescription>
                        Default is at-period-end — toggle below for immediate cancellation.
                    </DialogDescription>
                </DialogHeader>
                <Form
                    action={`/admin/subscriptions/${subscriptionId}/cancel`}
                    method="post"
                    onSuccess={() => onOpenChange(false)}
                    disableWhileProcessing
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="cancel-reason">Reason</Label>
                                <ReasonSelect
                                    id="cancel-reason"
                                    name="reason"
                                    reasons={reasons}
                                    value={reason}
                                    onValueChange={setReason}
                                />
                                <InputError message={errors.reason} />
                            </div>
                            <div className="flex items-center justify-between rounded-md border bg-muted/30 px-3 py-2">
                                <Label htmlFor="cancel-immediately" className="text-sm">
                                    Cancel immediately
                                </Label>
                                <input
                                    type="hidden"
                                    name="immediately"
                                    value={immediately ? '1' : '0'}
                                />
                                <Switch
                                    id="cancel-immediately"
                                    checked={immediately}
                                    onCheckedChange={setImmediately}
                                />
                            </div>
                            <NoteField name="admin_note" />
                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button type="button" variant="secondary">
                                        Back
                                    </Button>
                                </DialogClose>
                                <Button
                                    type="submit"
                                    variant="destructive"
                                    disabled={processing || !reason}
                                >
                                    Cancel subscription
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function ReactivateDialog({
    open,
    onOpenChange,
    subscriptionId,
}: {
    open: boolean;
    onOpenChange: (v: boolean) => void;
    subscriptionId: number;
}) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Reactivate subscription</DialogTitle>
                    <DialogDescription>
                        Removes the cancel-at-period-end flag so the subscription continues.
                    </DialogDescription>
                </DialogHeader>
                <Form
                    action={`/admin/subscriptions/${subscriptionId}/reactivate`}
                    method="post"
                    onSuccess={() => onOpenChange(false)}
                    disableWhileProcessing
                    className="space-y-4"
                >
                    {({ processing }) => (
                        <>
                            <NoteField name="admin_note" />
                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button type="button" variant="secondary">
                                        Cancel
                                    </Button>
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
                                    Reactivate
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function CreditDialog({
    open,
    onOpenChange,
    reasons,
    subscriptionId,
}: {
    open: boolean;
    onOpenChange: (v: boolean) => void;
    reasons: Record<string, string>;
    subscriptionId: number;
}) {
    const [dollars, setDollars] = useState('');
    const [reason, setReason] = useState('');
    const cents = Math.max(0, Math.round(Number(dollars || '0') * 100));

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Apply credit</DialogTitle>
                    <DialogDescription>
                        Credit is applied to the next invoice via the gateway (Stripe balance
                        transaction) and logged on the subscription.
                    </DialogDescription>
                </DialogHeader>
                <Form
                    action={`/admin/subscriptions/${subscriptionId}/credit`}
                    method="post"
                    onSuccess={() => onOpenChange(false)}
                    disableWhileProcessing
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="credit-amount">Amount</Label>
                                <Input
                                    id="credit-amount"
                                    type="number"
                                    step="0.01"
                                    min="0.01"
                                    value={dollars}
                                    onChange={(e) => setDollars(e.target.value)}
                                    placeholder="0.00"
                                    required
                                    className="font-mono"
                                />
                                <input type="hidden" name="amount_cents" value={cents} />
                                <p className="text-xs text-muted-foreground">= {cents} cents</p>
                                <InputError message={errors.amount_cents} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="credit-reason">Reason</Label>
                                <ReasonSelect
                                    id="credit-reason"
                                    name="reason"
                                    reasons={reasons}
                                    value={reason}
                                    onValueChange={setReason}
                                />
                                <InputError message={errors.reason} />
                            </div>
                            <NoteField name="admin_note" />
                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button type="button" variant="secondary">
                                        Cancel
                                    </Button>
                                </DialogClose>
                                <Button type="submit" disabled={processing || !reason || cents === 0}>
                                    Apply credit
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function CompDialog({
    open,
    onOpenChange,
    reasons,
    subscriptionId,
}: {
    open: boolean;
    onOpenChange: (v: boolean) => void;
    reasons: Record<string, string>;
    subscriptionId: number;
}) {
    const [months, setMonths] = useState(1);
    const [reason, setReason] = useState('');

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Comp months</DialogTitle>
                    <DialogDescription>
                        Extends the current period by N months and records a $0 paid invoice for
                        the audit trail.
                    </DialogDescription>
                </DialogHeader>
                <Form
                    action={`/admin/subscriptions/${subscriptionId}/comp`}
                    method="post"
                    onSuccess={() => onOpenChange(false)}
                    disableWhileProcessing
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="comp-months">Months</Label>
                                <Input
                                    id="comp-months"
                                    name="months"
                                    type="number"
                                    min="1"
                                    max="24"
                                    value={months}
                                    onChange={(e) => setMonths(Number(e.target.value))}
                                    required
                                    className="font-mono"
                                />
                                <InputError message={errors.months} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="comp-reason">Reason</Label>
                                <ReasonSelect
                                    id="comp-reason"
                                    name="reason"
                                    reasons={reasons}
                                    value={reason}
                                    onValueChange={setReason}
                                />
                                <InputError message={errors.reason} />
                            </div>
                            <NoteField name="admin_note" />
                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button type="button" variant="secondary">
                                        Cancel
                                    </Button>
                                </DialogClose>
                                <Button type="submit" disabled={processing || !reason || months < 1}>
                                    Comp {months} month{months === 1 ? '' : 's'}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
