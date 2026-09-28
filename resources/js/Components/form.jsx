/* ===========================================================================
 * FORM PRIMITIVES — mobile-first inputs wired to Inertia's useForm
 * ======================================================================== */

export function Field({ label, error, hint, required = false, className = '', children }) {
    return (
        <label className={`block ${className}`}>
            <span className="label">
                {label} {required && <span className="text-primary">*</span>}
            </span>
            {children}
            {error ? (
                <span className="mt-1 block text-xs font-semibold text-rose-600">{error}</span>
            ) : (
                hint && <span className="mt-1 block text-xs text-slate-400">{hint}</span>
            )}
        </label>
    );
}

export function TextInput({ error, className = '', ...props }) {
    return (
        <input
            {...props}
            className={`input ${error ? 'border-rose-300 focus:border-rose-500 focus:ring-rose-100' : ''} ${className}`}
        />
    );
}

export function Select({ error, children, className = '', ...props }) {
    return (
        <select
            {...props}
            className={`input ${error ? 'border-rose-300 focus:border-rose-500' : ''} ${className}`}
        >
            {children}
        </select>
    );
}

export function Textarea({ error, className = '', ...props }) {
    return (
        <textarea
            {...props}
            className={`input ${error ? 'border-rose-300 focus:border-rose-500' : ''} ${className}`}
        />
    );
}

export function Checkbox({ label, description, ...props }) {
    return (
        <label className="flex cursor-pointer items-start gap-2.5">
            <input
                type="checkbox"
                {...props}
                className="mt-0.5 h-4 w-4 rounded border-slate-300 text-primary focus:ring-primary/30"
            />
            <span>
                <span className="block text-sm font-semibold text-ink">{label}</span>
                {description && <span className="block text-xs text-slate-500">{description}</span>}
            </span>
        </label>
    );
}

/** Grouped section inside a long form. */
export function FormSection({ title, description, children, className = '' }) {
    return (
        <section className={`glass-card p-5 sm:p-6 ${className}`}>
            <h3 className="font-display text-base font-bold text-ink">{title}</h3>
            {description && <p className="mt-0.5 text-sm text-slate-500">{description}</p>}
            <div className="mt-4 grid gap-4 sm:grid-cols-2">{children}</div>
        </section>
    );
}

/** Sticky action bar for long forms (Save / Cancel). */
export function FormActions({ children }) {
    return <div className="sticky bottom-0 -mx-4 mt-6 flex flex-col gap-2 border-t border-slate-100 bg-white/95 px-4 py-3 backdrop-blur sm:flex-row sm:justify-end sm:px-0">{children}</div>;
}

/** Options for <Select> from a [{value,label}] list. */
export function options(list, placeholder) {
    return [
        ...(placeholder ? [{ value: '', label: placeholder }] : []),
        ...list.map((item) => ({ value: item.value, label: item.label })),
    ];
}
