import type { InertiaLinkProps } from '@inertiajs/vue3';
import { clsx, type ClassValue } from 'clsx';
import { format, isToday, isYesterday, parseISO } from 'date-fns';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function toUrl(href: NonNullable<InertiaLinkProps['href']>) {
    return typeof href === 'string' ? href : href?.url;
}

export function formatMessageDate(dateValue: any): string {
    if (!dateValue) return '';

    try {
        let date: Date;
        if (typeof dateValue === 'number') {
            // If it's a small number, it's likely seconds, otherwise milliseconds
            date = new Date(
                dateValue < 10000000000 ? dateValue * 1000 : dateValue,
            );
        } else {
            // Use parseISO for more robust string parsing (handles Z, offsets better across browsers)
            // If it's a raw date string, try new Date first if parseISO fails or behaves oddly,
            // but parseISO is generally safer for ISO strings from Laravel.
            // However, date-fns v3 parseISO expects a string.
            date =
                typeof dateValue === 'string'
                    ? parseISO(dateValue)
                    : new Date(dateValue);

            // Fallback validity check
            if (isNaN(date.getTime())) {
                date = new Date(dateValue);
            }
        }

        if (isNaN(date.getTime())) {
            return '';
        }

        if (isToday(date)) {
            return `Today at ${format(date, 'h:mm a')}`;
        }

        if (isYesterday(date)) {
            return `Yesterday at ${format(date, 'h:mm a')}`;
        }

        return format(date, 'MM/dd/yyyy');
    } catch {
        return '';
    }
}
