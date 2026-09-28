import { motion } from 'framer-motion';
import { AlertTriangle, X } from 'lucide-react';

/* ===========================================================================
 * MODAL — used by payment entry, parent linking, delete confirmations…
 * Mobile-first: bottom-sheet on phones, centred dialog from `sm` up.
 * ======================================================================== */

export function Modal({ open, onClose, title, subtitle, children, footer, size = 'md' }) {
    if (!open) return null;

    const widths = { sm: 'sm:max-w-sm', md: 'sm:max-w-lg', lg: 'sm:max-w-2xl' };

    return (
        <div className="fixed inset-0 z-50 flex items-end justify-center sm:items-center">
            <motion.div
                initial={{ opacity: 0 }}
                animate={{ opacity: 1 }}
                exit={{ opacity: 0 }}
                onClick={onClose}
                className="absolute inset-0 bg-ink/60 backdrop-blur-sm"
            />

            <motion.div
                initial={{ opacity: 0, y: 40, scale: 0.98 }}
                animate={{ opacity: 1, y: 0, scale: 1 }}
                transition={{ type: 'tween', duration: 0.25, ease: [0.22, 1, 0.36, 1] }}
                role="dialog"
                aria-modal="true"
                className={`relative max-h-[92vh] w-full overflow-y-auto rounded-t-3xl bg-white p-5 shadow-2xl sm:rounded-2xl sm:p-6 ${widths[size]}`}
            >
                <header className="mb-4 flex items-start justify-between gap-3">
                    <div>
                        <h3 className="font-display text-lg font-bold text-ink">{title}</h3>
                        {subtitle && <p className="mt-0.5 text-sm text-slate-500">{subtitle}</p>}
                    </div>
                    <button
                        type="button"
                        onClick={onClose}
                        className="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-slate-100 text-slate-500 transition hover:bg-slate-200"
                        aria-label="Close"
                    >
                        <X className="h-4 w-4" />
                    </button>
                </header>

                {children}

                {footer && <div className="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">{footer}</div>}
            </motion.div>
        </div>
    );
}

/* ===========================================================================
 * CONFIRM DIALOG — destructive actions (delete student, void invoice…)
 * ======================================================================== */

export function ConfirmDialog({ open, onClose, onConfirm, title, message, confirmLabel = 'Delete', processing = false }) {
    return (
        <Modal
            open={open}
            onClose={onClose}
            title={title}
            size="sm"
            footer={
                <>
                    <button type="button" onClick={onClose} className="btn-ghost">
                        Cancel
                    </button>
                    <button
                        type="button"
                        onClick={onConfirm}
                        disabled={processing}
                        className="btn bg-rose-600 text-white hover:bg-rose-700"
                    >
                        {processing ? 'Working…' : confirmLabel}
                    </button>
                </>
            }
        >
            <div className="flex items-start gap-3 rounded-xl bg-rose-50 p-4 text-sm text-rose-800">
                <AlertTriangle className="mt-0.5 h-5 w-5 shrink-0" />
                <span>{message}</span>
            </div>
        </Modal>
    );
}
