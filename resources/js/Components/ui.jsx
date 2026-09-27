import { motion } from 'framer-motion';
import { AlertTriangle, CheckCircle2, Info } from 'lucide-react';
import { usePage } from '@inertiajs/react';

/* ===========================================================================
 * Brand palette shared by every chart + badge in the app
 * ======================================================================== */

export const BRAND = {
    primary: '#D81B60',
    secondary: '#0288D1',
    accent: '#FBC02D',
    canvas: '#F8F9FA',
    ink: '#1E293B',
    grid: '#E2E8F0',
    muted: '#94A3B8',
};

/** Consistent tooltip skin for all Recharts graphs. */
export const tooltipStyle = {
    backgroundColor: '#1E293B',
    border: 'none',
    borderRadius: '0.75rem',
    color: '#F8F9FA',
    fontSize: 12,
    fontWeight: 600,
    padding: '8px 12px',
    boxShadow: '0 8px 24px -8px rgba(30,41,59,0.35)',
};

/* ===========================================================================
 * Stat card — KPI tile with icon, value and label
 * ======================================================================== */

const ACCENTS = {
    primary: { ring: 'bg-primary/10 text-primary', bar: 'bg-primary' },
    secondary: { ring: 'bg-secondary/10 text-secondary', bar: 'bg-secondary' },
    accent: { ring: 'bg-accent/20 text-amber-600', bar: 'bg-accent' },
};

export function StatCard({ icon: Icon, label, value, accent = 'primary', hint, index = 0 }) {
    const palette = ACCENTS[accent] ?? ACCENTS.primary;

    return (
        <motion.div
            initial={{ opacity: 0, y: 14 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: index * 0.07, duration: 0.35, ease: 'easeOut' }}
            className="stat-card"
        >
            <span className={`absolute inset-x-0 top-0 h-1 ${palette.bar}`} />
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <p className="truncate text-xs font-bold uppercase tracking-wider text-slate-400">
                        {label}
                    </p>
                    <p className="mt-1.5 truncate font-display text-2xl font-bold text-ink sm:text-3xl">
                        {value}
                    </p>
                    {hint && <p className="mt-1 text-xs text-slate-500">{hint}</p>}
                </div>
                {Icon && (
                    <span className={`grid h-11 w-11 shrink-0 place-items-center rounded-xl ${palette.ring}`}>
                        <Icon className="h-5 w-5" strokeWidth={2.2} />
                    </span>
                )}
            </div>
        </motion.div>
    );
}

/* ===========================================================================
 * Generic glass card with optional header action
 * ======================================================================== */

export function Card({ title, subtitle, action, children, className = '', delay = 0 }) {
    return (
        <motion.section
            initial={{ opacity: 0, y: 14 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay, duration: 0.35, ease: 'easeOut' }}
            className={`glass-card p-5 sm:p-6 ${className}`}
        >
            {(title || action) && (
                <header className="mb-4 flex flex-wrap items-center justify-between gap-2">
                    <div>
                        {title && (
                            <h3 className="font-display text-base font-bold text-ink sm:text-lg">
                                {title}
                            </h3>
                        )}
                        {subtitle && (
                            <p className="mt-0.5 text-xs text-slate-500 sm:text-sm">{subtitle}</p>
                        )}
                    </div>
                    {action}
                </header>
            )}
            {children}
        </motion.section>
    );
}

/* ===========================================================================
 * Badges (status chips)
 * ======================================================================== */

const BADGE_STYLES = {
    primary: 'bg-primary/10 text-primary',
    secondary: 'bg-secondary/10 text-secondary',
    accent: 'bg-accent/25 text-amber-700',
    success: 'bg-emerald-100 text-emerald-700',
    danger: 'bg-rose-100 text-rose-700',
    warning: 'bg-amber-100 text-amber-700',
    muted: 'bg-slate-100 text-slate-600',
};

export function Badge({ children, tone = 'primary', className = '' }) {
    return (
        <span className={`badge ${BADGE_STYLES[tone] ?? BADGE_STYLES.primary} ${className}`}>
            {children}
        </span>
    );
}

/* ===========================================================================
 * Section heading (public website)
 * ======================================================================== */

export function SectionHeading({ eyebrow, title, description, center = true }) {
    return (
        <div className={`mb-10 ${center ? 'mx-auto max-w-2xl text-center' : 'max-w-2xl'}`}>
            {eyebrow && <span className="badge bg-primary/10 text-primary">{eyebrow}</span>}
            <h2 className="section-title mt-3">{title}</h2>
            {description && (
                <p className="mt-3 text-balance text-sm leading-relaxed text-slate-500 sm:text-base">
                    {description}
                </p>
            )}
        </div>
    );
}

/* ===========================================================================
 * Flash alerts (session success/error shared via HandleInertiaRequests)
 * ======================================================================== */

const FLASH_META = {
    success: { icon: CheckCircle2, tone: 'border-emerald-300 bg-emerald-50 text-emerald-800' },
    error: { icon: AlertTriangle, tone: 'border-rose-300 bg-rose-50 text-rose-800' },
    info: { icon: Info, tone: 'border-sky-300 bg-sky-50 text-sky-800' },
};

export function Flash({ className = '' }) {
    const { flash } = usePage().props;
    const message = flash?.success || flash?.error || flash?.info;
    if (!message) return null;

    const type = flash.success ? 'success' : flash.error ? 'error' : 'info';
    const { icon: Icon, tone } = FLASH_META[type];

    return (
        <motion.div
            initial={{ opacity: 0, y: -8 }}
            animate={{ opacity: 1, y: 0 }}
            className={`flex items-start gap-3 rounded-xl border px-4 py-3 text-sm font-semibold shadow-sm ${tone} ${className}`}
            role="status"
        >
            <Icon className="mt-0.5 h-5 w-5 shrink-0" />
            <span className="flex-1">{message}</span>
        </motion.div>
    );
}

/* ===========================================================================
 * Empty state (charts/lists without data yet)
 * ======================================================================== */

export function EmptyState({ icon: Icon = Info, title = 'Nothing here yet', hint }) {
    return (
        <div className="flex flex-col items-center justify-center gap-2 py-10 text-center">
            <span className="grid h-12 w-12 place-items-center rounded-2xl bg-slate-100 text-slate-400">
                <Icon className="h-6 w-6" />
            </span>
            <p className="text-sm font-bold text-slate-600">{title}</p>
            {hint && <p className="max-w-xs text-xs text-slate-400">{hint}</p>}
        </div>
    );
}

/* ===========================================================================
 * Animated progress bar (fee collection / attendance targets)
 * ======================================================================== */

export function ProgressBar({ value = 0, color = 'bg-primary', label }) {
    return (
        <div>
            {label && (
                <div className="mb-1.5 flex items-center justify-between text-xs font-bold text-slate-500">
                    <span>{label}</span>
                    <span className="text-ink">{value}%</span>
                </div>
            )}
            <div className="h-2.5 w-full overflow-hidden rounded-full bg-slate-100">
                <motion.div
                    initial={{ width: 0 }}
                    animate={{ width: `${Math.min(100, Math.max(0, value))}%` }}
                    transition={{ duration: 0.9, ease: 'easeOut' }}
                    className={`h-full rounded-full ${color}`}
                />
            </div>
        </div>
    );
}