import {
    endOfMonth,
    endOfToday,
    endOfYesterday,
    format,
    startOfMonth,
    startOfToday,
    startOfYesterday,
    subDays,
    subMonths,
} from 'date-fns';
import { Calendar as CalendarIcon, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { type DateRange } from 'react-day-picker';

import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { cn } from '@/lib/utils';

interface DateRangePickerProps {
    value?: DateRange;
    onChange?: (range: DateRange | undefined) => void;
    placeholder?: string;
    className?: string;
}

interface PresetRange {
    label: string;
    getValue: () => DateRange;
}

const presetRanges: PresetRange[] = [
    {
        label: 'Today',
        getValue: () => ({
            from: startOfToday(),
            to: endOfToday(),
        }),
    },
    {
        label: 'Yesterday',
        getValue: () => ({
            from: startOfYesterday(),
            to: endOfYesterday(),
        }),
    },
    {
        label: 'Last 7 Days',
        getValue: () => ({
            from: subDays(startOfToday(), 6),
            to: endOfToday(),
        }),
    },
    {
        label: 'Last 30 Days',
        getValue: () => ({
            from: subDays(startOfToday(), 29),
            to: endOfToday(),
        }),
    },
    {
        label: 'Last 90 Days',
        getValue: () => ({
            from: subDays(startOfToday(), 89),
            to: endOfToday(),
        }),
    },
    {
        label: 'This Month',
        getValue: () => ({
            from: startOfMonth(new Date()),
            to: endOfToday(),
        }),
    },
    {
        label: 'Last Month',
        getValue: () => {
            const lastMonth = subMonths(new Date(), 1);
            return {
                from: startOfMonth(lastMonth),
                to: endOfMonth(lastMonth),
            };
        },
    },
];

export function DateRangePicker({
    value,
    onChange,
    placeholder = 'Pick a date range',
    className,
}: DateRangePickerProps) {
    const [open, setOpen] = useState(false);
    const [localRange, setLocalRange] = useState<DateRange | undefined>(value);

    useEffect(() => {
        setLocalRange(value);
    }, [value]);

    useEffect(() => {
        if (open) {
            setLocalRange(value);
        }
    }, [open, value]);

    const handlePresetSelect = (preset: PresetRange) => {
        const range = preset.getValue();
        setLocalRange(range);
        onChange?.(range);
        setOpen(false);
    };

    const handleCalendarSelect = (range: DateRange | undefined) => {
        setLocalRange(range);
        if (range?.from && range?.to) {
            onChange?.(range);
            setOpen(false);
        }
    };

    const handleClear = (e: React.MouseEvent) => {
        e.stopPropagation();
        e.preventDefault();
        setLocalRange(undefined);
        onChange?.(undefined);
    };

    const handleClearFromPopover = () => {
        setLocalRange(undefined);
        onChange?.(undefined);
        setOpen(false);
    };

    return (
        <div className={cn('grid gap-2', className)}>
            <Popover open={open} onOpenChange={setOpen}>
                <PopoverTrigger asChild>
                    <Button
                        id="date"
                        variant="outline"
                        className={cn(
                            'w-full justify-start text-left font-normal',
                            !value && 'text-muted-foreground',
                        )}
                    >
                        <CalendarIcon className="mr-2 h-4 w-4" />
                        <span className="flex-1">
                            {value?.from ? (
                                value.to ? (
                                    <>
                                        {format(value.from, 'LLL dd, y')} -{' '}
                                        {format(value.to, 'LLL dd, y')}
                                    </>
                                ) : (
                                    format(value.from, 'LLL dd, y')
                                )
                            ) : (
                                placeholder
                            )}
                        </span>
                        {value?.from && (
                            <X
                                className="ml-2 h-4 w-4 cursor-pointer opacity-50 hover:opacity-100"
                                onClick={handleClear}
                            />
                        )}
                    </Button>
                </PopoverTrigger>
                <PopoverContent className="w-auto p-0" align="start">
                    <div className="flex">
                        <div className="flex flex-col gap-1 border-r p-2">
                            {presetRanges.map((preset) => (
                                <Button
                                    key={preset.label}
                                    variant="ghost"
                                    size="sm"
                                    className="justify-start font-normal"
                                    onClick={() => handlePresetSelect(preset)}
                                >
                                    {preset.label}
                                </Button>
                            ))}
                            <div className="my-1 border-t" />
                            <Button
                                variant="ghost"
                                size="sm"
                                className="justify-start font-normal text-destructive hover:text-destructive"
                                onClick={handleClearFromPopover}
                            >
                                Clear
                            </Button>
                        </div>

                        <div className="p-0">
                            <Calendar
                                initialFocus
                                mode="range"
                                defaultMonth={localRange?.from}
                                selected={localRange}
                                onSelect={handleCalendarSelect}
                                numberOfMonths={2}
                            />
                        </div>
                    </div>
                </PopoverContent>
            </Popover>
        </div>
    );
}
