import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Plus, Search } from 'lucide-react';
import { EmptyState } from './ui';

/* ===========================================================================
 * PAGE HEADER — title, subtitle, primary action
 * ======================================================================== */

export function PageHeader({ title, subtitle, action, back }) {
    return (
        <div className="mb-6 flex flex-wrap items-start justify-between gap-3">
            <div className="min-w-0">
                {back && (
                    <Link href={back} className="mb-1 inline-flex items-center gap-1 text-xs font-bold text-primary hover:underline">
                        <ChevronLeft className="h-3.5 w-3.5" />
                        Back
                    </Link>
                )}
                <h1 className="font-display text-xl font-extrabold text-ink sm:text-2xl">{title}</h1>
                {subtitle && <p className="mt-1 text-sm text-slate-500">{subtitle}</p>}
            </div>
            {action}
        </div>
    );
}

/** "New Student" style button (link or click handler). */
export function PrimaryAction({ href, children, onClick, icon: Icon = Plus }) {
    const inner = (
        <>
            <Icon className="h-4 w-4" />
            {children}
        </>
    );

    return href ? (
        <Link href={href} className="btn-primary">
            {inner}
        </Link>
    ) : (
        <button type="button" onClick={onClick} className="btn-primary">
            {inner}
        </button>
    );
}

/* ===========================================================================
 * SEARCH + FILTER BAR
 * ======================================================================== */

export function FilterBar({ value, onChange, placeholder = 'Search…', children, onSubmit }) {
    return (
        <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end">
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    onSubmit?.();
                }}
                className="relative flex-1"
            >
                <Search className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                <input
                    className="input pl-10"
                    defaultValue={value ?? ''}
                    onChange={(e) => onChange(e.target.value)}
                    placeholder={placeholder}
                />
            </form>
            {children && <div className="flex flex-wrap gap-2">{children}</div>}
        </div>
    );
}

/* ===========================================================================
 * TABLE SHELL — real table on desktop, stacked cards on mobile
 * ======================================================================== */

export function Table({ head, children, empty = 'No records found.' }) {
    const isEmpty = !children || (Array.isArray(children) && children.length === 0);

    if (isEmpty) return <EmptyState title={empty} />;

    return (
        <>
            <div className="hidden overflow-x-auto sm:block">
                <table className="w-full text-left text-sm">
                    <thead>
                        <tr className="border-b border-slate-100 text-xs uppercase tracking-wider text-slate-400">
                            {head.map((column) => (
                                <th key={column} className="whitespace-nowrap pb-3 font-bold">
                                    {column}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">{children}</tbody>
                </table>
            </div>
            <ul className="space-y-3 sm:hidden">{children}</ul>
        </>
    );
}

/* ===========================================================================
 * PAGINATION — Inertia paginator props
 * ======================================================================== */

export function Pagination({ meta, onPage }) {
    if (!meta || meta.last_page <= 1) return null;

    const from = (meta.current_page - 1) * meta.per_page + 1;
    const to = Math.min(meta.current_page * meta.per_page, meta.total);

    return (
        <nav className="mt-5 flex flex-col items-center justify-between gap-3 sm:flex-row">
            <p className="text-xs font-semibold text-slate-500">
                Showing <span className="text-ink">{from}–{to}</span> of{' '}
                <span className="text-ink">{meta.total}</span>
            </p>
            <div className="flex items-center gap-1">
                <button
                    type="button"
                    disabled={meta.current_page <= 1}
                    onClick={() => onPage(meta.current_page - 1)}
                    className="grid h-9 w-9 place-items-center rounded-xl border border-slate-200 text-slate-600 disabled:opacity-40"
                    aria-label="Previous page"
                >
                    <ChevronLeft className="h-4 w-4" />
                </button>
                <span className="px-3 text-xs font-bold text-ink">
                    {meta.current_page} / {meta.last_page}
                </span>
                <button
                    type="button"
                    disabled={meta.current_page >= meta.last_page}
                    onClick={() => onPage(meta.current_page + 1)}
                    className="grid h-9 w-9 place-items-center rounded-xl border border-slate-200 text-slate-600 disabled:opacity-40"
                    aria-label="Next page"
                >
                    <ChevronRight className="h-4 w-4" />
                </button>
            </div>
        </nav>
    );
}