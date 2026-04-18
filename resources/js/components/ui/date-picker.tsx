import { format } from 'date-fns';
import { Calendar as CalendarIcon } from 'lucide-react';
import { useState } from 'react';

import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { cn } from '@/lib/utils';

interface DatePickerProps {
    value?: Date;
    onChange?: (date: Date | undefined) => void;
    placeholder?: string;
    className?: string;
    disabled?: boolean;
    maxDate?: Date;
    minDate?: Date;
}

export function DatePicker({
    value,
    onChange,
    placeholder = 'Pick a date',
    className,
    disabled = false,
    maxDate,
    minDate,
}: DatePickerProps) {
    const [open, setOpen] = useState(false);

    const handleSelect = (date: Date | undefined) => {
        onChange?.(date);
        setOpen(false);
    };

    return (
        <div className={cn('grid gap-2', className)}>
            <Popover open={open} onOpenChange={setOpen}>
                <PopoverTrigger asChild>
                    <Button
                        variant="outline"
                        disabled={disabled}
                        className={cn(
                            'w-full justify-start text-left font-normal',
                            !value && 'text-muted-foreground',
                        )}
                    >
                        <CalendarIcon className="mr-2 h-4 w-4" />
                        {value ? (
                            format(value, 'yyyy-MM-dd')
                        ) : (
                            <span>{placeholder}</span>
                        )}
                    </Button>
                </PopoverTrigger>
                <PopoverContent className="w-auto p-0" align="start">
                    <Calendar
                        mode="single"
                        selected={value}
                        onSelect={handleSelect}
                        disabled={(date) => {
                            if (maxDate && date > maxDate) return true;
                            if (minDate && date < minDate) return true;
                            return false;
                        }}
                        initialFocus
                    />
                </PopoverContent>
            </Popover>
        </div>
    );
}
