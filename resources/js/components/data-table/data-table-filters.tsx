import axios from 'axios';
import { format, parseISO } from 'date-fns';
import { Loader2 } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import type {DateRange} from 'react-day-picker';


import { Button } from '@/components/ui/button';
import { DateRangePicker } from '@/components/ui/date-range-picker';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type {DataTableFilter} from './data-table';


function AsyncSelectFilter({
    filterKey,
    searchUrl,
    placeholder,
    value,
    onChange,
}: {
    filterKey: string;
    searchUrl: string;
    placeholder?: string;
    value: string;
    onChange: (value: string, label: string) => void;
}) {
    const [search, setSearch] = useState('');
    const [options, setOptions] = useState<{ label: string; value: string }[]>([]);
    const [loading, setLoading] = useState(false);
    const [open, setOpen] = useState(false);
    const [selectedLabel, setSelectedLabel] = useState('');
    const debounceRef = useRef<ReturnType<typeof setTimeout>>(null);
    const containerRef = useRef<HTMLDivElement>(null);

    const hasFetchedRef = useRef(false);

    const fetchOptions = useCallback(async (query: string) => {
        setLoading(true);

        try {
            const response = await axios.get<{ data: { label: string; value: string }[] }>(searchUrl, {
                params: { search: query },
            });
            setOptions(response.data.data);
        } catch {
            setOptions([]);
        } finally {
            setLoading(false);
        }
    }, [searchUrl]);

    // Fetch only when dropdown is opened for the first time
    const handleOpen = () => {
        const next = !open;
        setOpen(next);

        if (next && !hasFetchedRef.current) {
            hasFetchedRef.current = true;
            fetchOptions('');
        }
    };

    const handleSearchChange = (query: string) => {
        setSearch(query);

        if (debounceRef.current) {
clearTimeout(debounceRef.current);
}

        debounceRef.current = setTimeout(() => fetchOptions(query), 300);
    };

    const handleSelect = (optionValue: string, optionLabel: string) => {
        onChange(optionValue, optionLabel);
        setSelectedLabel(optionLabel);
        setOpen(false);
        setSearch('');
    };

    const handleClear = () => {
        onChange('', '');
        setSelectedLabel('');
        setSearch('');
    };

    // Close on outside click
    useEffect(() => {
        const handleClickOutside = (e: MouseEvent) => {
            if (containerRef.current && !containerRef.current.contains(e.target as Node)) {
                setOpen(false);
            }
        };
        document.addEventListener('mousedown', handleClickOutside);

        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    return (
        <div ref={containerRef} className="relative">
            <div
                className="flex h-9 w-full items-center justify-between rounded-md border border-input bg-transparent px-3 py-1 text-sm cursor-pointer"
                onClick={handleOpen}
            >
                <span className={selectedLabel ? '' : 'text-muted-foreground'}>
                    {selectedLabel || placeholder || 'Select...'}
                </span>
                {value && (
                    <button
                        type="button"
                        className="ml-1 text-muted-foreground hover:text-foreground"
                        onClick={(e) => {
 e.stopPropagation(); handleClear(); 
}}
                    >
                        x
                    </button>
                )}
            </div>
            {open && (
                <div className="absolute z-50 mt-1 w-full rounded-md border bg-popover shadow-md">
                    <div className="p-2">
                        <Input
                            placeholder="Search..."
                            value={search}
                            onChange={(e) => handleSearchChange(e.target.value)}
                            autoFocus
                            className="h-8"
                        />
                    </div>
                    <div className="max-h-[160px] overflow-y-auto">
                        {loading ? (
                            <div className="flex items-center justify-center py-3">
                                <Loader2 className="h-4 w-4 animate-spin text-muted-foreground" />
                            </div>
                        ) : options.length === 0 ? (
                            <div className="py-3 text-center text-sm text-muted-foreground">No results</div>
                        ) : (
                            options.map((option) => (
                                <div
                                    key={option.value}
                                    className={`cursor-pointer px-3 py-2 text-sm hover:bg-accent ${option.value === value ? 'bg-accent font-medium' : ''}`}
                                    onClick={() => handleSelect(option.value, option.label)}
                                >
                                    {option.label}
                                </div>
                            ))
                        )}
                    </div>
                </div>
            )}
        </div>
    );
}

interface DataTableFiltersProps {
    filters: DataTableFilter[];
    activeFilters: Record<string, string>;
    onFilterChange: (filters: Record<string, string>) => void;
    onClose: () => void;
    onAsyncLabelChange?: (key: string, label: string) => void;
}

export function DataTableFilters({
    filters,
    activeFilters,
    onFilterChange,
    onClose,
    onAsyncLabelChange,
}: DataTableFiltersProps) {
    const [localFilters, setLocalFilters] =
        useState<Record<string, string>>(activeFilters);

    useEffect(() => {
        setLocalFilters(activeFilters);
    }, [activeFilters]);

    const handleFilterValueChange = (key: string, value: string) => {
        setLocalFilters((prev) => ({
            ...prev,
            [key]: value,
        }));
    };

    // Convert string to DateRange for daterange filters
    const parseDateRangeValue = (value: string | undefined): DateRange | undefined => {
        if (!value) {
return undefined;
}

        const [fromStr, toStr] = value.split('|');

        return {
            from: fromStr ? parseISO(fromStr) : undefined,
            to: toStr ? parseISO(toStr) : undefined,
        };
    };

    // Convert DateRange to string for storage
    const formatDateRangeValue = (range: DateRange | undefined): string => {
        if (!range?.from) {
return '';
}

        const from = format(range.from, 'yyyy-MM-dd');
        const to = range.to ? format(range.to, 'yyyy-MM-dd') : '';

        return to ? `${from}|${to}` : from;
    };

    const handleDateRangeChange = (key: string, range: DateRange | undefined) => {
        const value = formatDateRangeValue(range);
        setLocalFilters((prev) => ({
            ...prev,
            [key]: value,
        }));
    };

    // Parse range value from string "min|max"
    const parseRangeValue = (value: string | undefined): { from: string; to: string } => {
        if (!value) {
return { from: '', to: '' };
}

        const [fromVal, toVal] = value.split('|');

        return {
            from: fromVal || '',
            to: toVal || '',
        };
    };

    // Handle range input change
    const handleRangeInputChange = (key: string, field: 'from' | 'to', inputValue: string) => {
        const currentRange = parseRangeValue(localFilters[key]);
        const newRange = {
            ...currentRange,
            [field]: inputValue,
        };

        // Format as "from|to", handling empty values
        let value = '';

        if (newRange.from || newRange.to) {
            value = `${newRange.from}|${newRange.to}`;
        }

        setLocalFilters((prev) => ({
            ...prev,
            [key]: value,
        }));
    };

    const handleApplyFilters = () => {
        // Remove empty filters
        const cleanedFilters = Object.entries(localFilters).reduce(
            (acc, [key, value]) => {
                if (value !== '' && value !== null && value !== undefined) {
                    acc[key] = value;
                }

                return acc;
            },
            {} as Record<string, string>
        );

        onFilterChange(cleanedFilters);
        onClose();
    };

    const handleResetFilters = () => {
        setLocalFilters({});
        onFilterChange({});
    };

    return (
        <div className="mb-4 flex h-full flex-col overflow-y-auto">
            <div className="flex-1 space-y-4 px-2 py-6">
                {filters.map((filter) => (
                    <div key={filter.key} className="space-y-2">
                        <Label htmlFor={filter.key} className="font-medium">
                            {filter.label}
                        </Label>
                        {filter.type === 'text' && (
                            <Input
                                id={filter.key}
                                placeholder={filter.placeholder}
                                value={localFilters[filter.key] || ''}
                                onChange={(e) =>
                                    handleFilterValueChange(
                                        filter.key,
                                        e.target.value
                                    )
                                }
                            />
                        )}
                        {filter.type === 'select' && filter.options && (
                            <Select
                                value={localFilters[filter.key] || ''}
                                onValueChange={(value) =>
                                    handleFilterValueChange(filter.key, value)
                                }
                            >
                                <SelectTrigger id={filter.key} className="w-full">
                                    <SelectValue
                                        placeholder={
                                            filter.placeholder || 'Select...'
                                        }
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    {filter.options.map((option) => (
                                        <SelectItem
                                            key={option.value}
                                            value={option.value}
                                        >
                                            {option.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        )}
                        {filter.type === 'date' && (
                            <Input
                                id={filter.key}
                                type="date"
                                value={localFilters[filter.key] || ''}
                                onChange={(e) =>
                                    handleFilterValueChange(
                                        filter.key,
                                        e.target.value
                                    )
                                }
                            />
                        )}
                        {filter.type === 'daterange' && (
                            <DateRangePicker
                                value={parseDateRangeValue(localFilters[filter.key])}
                                onChange={(range) => handleDateRangeChange(filter.key, range)}
                                placeholder={filter.placeholder || 'Select date range'}
                            />
                        )}
                        {filter.type === 'async-select' && filter.searchUrl && (
                            <AsyncSelectFilter
                                filterKey={filter.key}
                                searchUrl={filter.searchUrl}
                                placeholder={filter.placeholder}
                                value={localFilters[filter.key] || ''}
                                onChange={(value, label) => {
                                    handleFilterValueChange(filter.key, value);

                                    if (onAsyncLabelChange) {
                                        onAsyncLabelChange(filter.key, label);
                                    }
                                }}
                            />
                        )}
                        {filter.type === 'range' && (
                            <div className="flex items-center gap-2">
                                <Input
                                    type="number"
                                    placeholder="From"
                                    value={parseRangeValue(localFilters[filter.key]).from}
                                    onChange={(e) => handleRangeInputChange(filter.key, 'from', e.target.value)}
                                    className="flex-1"
                                    step={filter.step || 1}
                                />
                                <span className="text-muted-foreground">-</span>
                                <Input
                                    type="number"
                                    placeholder="To"
                                    value={parseRangeValue(localFilters[filter.key]).to}
                                    onChange={(e) => handleRangeInputChange(filter.key, 'to', e.target.value)}
                                    className="flex-1"
                                    step={filter.step || 1}
                                />
                            </div>
                        )}
                    </div>
                ))}
            </div>

            <div className="flex gap-2 border-t px-1 pt-4">
                <Button
                    variant="outline"
                    className="flex-1"
                    onClick={handleResetFilters}
                >
                    Reset
                </Button>
                <Button className="flex-1" onClick={handleApplyFilters}>
                    Apply Filters
                </Button>
            </div>
        </div>
    );
}
