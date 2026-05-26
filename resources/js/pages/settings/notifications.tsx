import { Form, Head } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import NotificationsPreferencesController from '@/actions/App/Http/Controllers/Settings/NotificationsPreferencesController';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Switch } from '@/components/ui/switch';
import { edit as editNotifications } from '@/routes/settings/notifications';

type EventDef = {
    slug: string;
    label: string;
    description: string | null;
    group: string;
    always_on: boolean;
};

type ChannelDef = {
    slug: string;
    label: string;
    description: string | null;
};

type Props = {
    events: EventDef[];
    channels: ChannelDef[];
    preferences: Record<string, Record<string, boolean>>;
};

export default function NotificationsPreferences({
    events,
    channels,
    preferences,
}: Props) {
    const [matrix, setMatrix] = useState<
        Record<string, Record<string, boolean>>
    >(() => {
        const initial: Record<string, Record<string, boolean>> = {};

        for (const event of events) {
            initial[event.slug] = {};

            for (const channel of channels) {
                initial[event.slug][channel.slug] =
                    preferences[event.slug]?.[channel.slug] ?? false;
            }
        }

        return initial;
    });

    const grouped = useMemo(() => {
        return events.reduce<Record<string, EventDef[]>>((acc, event) => {
            (acc[event.group] = acc[event.group] ?? []).push(event);

            return acc;
        }, {});
    }, [events]);

    const setCell = (event: string, channel: string, value: boolean) => {
        setMatrix((current) => ({
            ...current,
            [event]: {
                ...current[event],
                [channel]: value,
            },
        }));
    };

    return (
        <>
            <Head title="Notification preferences" />

            <h1 className="sr-only">Notification preferences</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Notification preferences"
                    description="Pick which events you want to receive and how. Email and in-app delivery can be toggled per event."
                />

                <Form
                    {...NotificationsPreferencesController.update.form()}
                    options={{ preserveScroll: true }}
                    className="space-y-6"
                >
                    {({ processing }) => (
                        <>
                            {Object.entries(grouped).map(
                                ([group, groupEvents]) => (
                                    <Card key={group}>
                                        <CardHeader>
                                            <CardTitle className="capitalize">
                                                {group}
                                            </CardTitle>
                                            <CardDescription>
                                                {channels
                                                    .map((c) => c.label)
                                                    .join(' · ')}
                                            </CardDescription>
                                        </CardHeader>

                                        <CardContent className="space-y-4">
                                            {groupEvents.map((event) => (
                                                <div
                                                    key={event.slug}
                                                    className="grid grid-cols-1 gap-3 border-t pt-4 first:border-t-0 first:pt-0 md:grid-cols-[1fr_auto]"
                                                    data-test={`notification-row-${event.slug}`}
                                                >
                                                    <div>
                                                        <div className="text-sm font-medium">
                                                            {event.label}
                                                            {event.always_on && (
                                                                <span className="ml-2 rounded bg-muted px-1.5 py-0.5 text-[10px] font-normal uppercase tracking-wide text-muted-foreground">
                                                                    Required
                                                                </span>
                                                            )}
                                                        </div>
                                                        {event.description && (
                                                            <p className="mt-0.5 text-xs text-muted-foreground">
                                                                {
                                                                    event.description
                                                                }
                                                            </p>
                                                        )}
                                                    </div>

                                                    <div className="flex items-center gap-4">
                                                        {channels.map(
                                                            (channel) => {
                                                                const checked =
                                                                    event.always_on
                                                                        ? true
                                                                        : (matrix[
                                                                              event
                                                                                  .slug
                                                                          ]?.[
                                                                              channel
                                                                                  .slug
                                                                          ] ??
                                                                          false);
                                                                const fieldName = `preferences[${event.slug}][${channel.slug}]`;

                                                                return (
                                                                    <label
                                                                        key={
                                                                            channel.slug
                                                                        }
                                                                        className="flex flex-col items-center gap-1 text-xs"
                                                                    >
                                                                        <span className="text-muted-foreground">
                                                                            {
                                                                                channel.label
                                                                            }
                                                                        </span>
                                                                        {/* Radix Switch sends nothing when off — hidden mirror keeps the value submitted. */}
                                                                        <input
                                                                            type="hidden"
                                                                            name={
                                                                                fieldName
                                                                            }
                                                                            value="0"
                                                                        />
                                                                        <Switch
                                                                            name={
                                                                                fieldName
                                                                            }
                                                                            value="1"
                                                                            checked={
                                                                                checked
                                                                            }
                                                                            disabled={
                                                                                event.always_on
                                                                            }
                                                                            onCheckedChange={(
                                                                                v,
                                                                            ) =>
                                                                                setCell(
                                                                                    event.slug,
                                                                                    channel.slug,
                                                                                    Boolean(
                                                                                        v,
                                                                                    ),
                                                                                )
                                                                            }
                                                                            data-test={`notification-toggle-${event.slug}-${channel.slug}`}
                                                                        />
                                                                    </label>
                                                                );
                                                            },
                                                        )}
                                                    </div>
                                                </div>
                                            ))}
                                        </CardContent>
                                    </Card>
                                ),
                            )}

                            <div className="flex items-center gap-4">
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    data-test="save-notification-preferences"
                                >
                                    Save preferences
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

NotificationsPreferences.layout = {
    breadcrumbs: [
        {
            title: 'Notification preferences',
            href: editNotifications(),
        },
    ],
};
