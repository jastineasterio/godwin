import { usePage } from '@inertiajs/react';

/**
 * Centralized school-logo component — God-Win Daycare & Nursery School.
 *
 * Uses the actual official logo (public/images/godwin-logo.png) instead of
 * an inline SVG monogram. Aspect ratio is preserved via object-contain so the
 * logo is never stretched, distorted, or cropped.
 *
 * @param {Object} props
 * @param {'sm' | 'md' | 'lg'} props.size - logo size variant:
 *   sm = 36px (sidebar), md = 40px (header/footer, default), lg = 56px (login/feature)
 * @param {boolean} props.dark - whether the logo sits on a dark background
 * @param {string} props.className - extra tailwind classes
 */
export function Logo({ dark = false, className = '', imgClassName = '', size = 'md', wordmark = true }) {
    const { school } = usePage().props ?? {};
    const name = school?.name ?? 'God-Win Daycare & Nursery School';
    const short = school?.short ?? 'God-Win';

    const sizeClasses = {
        sm: 'w-9 h-9',      // 36px — sidebar
        md: 'w-10 h-10',    // 40px — header / footer
        lg: 'w-14 h-14',    // 56px — login / feature areas
        xl: 'w-40 h-40',    // 160px — home identity section
    }[size] ?? 'w-10 h-10';

    return (
        <span className={`flex items-center gap-2.5 ${className}`}>
            <img
                src="/images/godwin-logo.png"
                alt={`${name} logo`}
                loading="eager"
                decoding="async"
                className={`shrink-0 rounded-2xl border border-white/20 bg-white/10 shadow-lg object-contain p-0.5 max-w-full max-h-full ${sizeClasses} ${imgClassName}`}
            />

            {wordmark && (
                <span className="leading-tight">
                    <span className={`block font-display text-lg font-extrabold ${dark ? 'text-white' : 'text-ink'}`}>
                        {short}
                    </span>
                    <span className={`block text-[10px] font-bold uppercase tracking-widest ${dark ? 'text-white/70' : 'text-primary'}`}>
                        Daycare &amp; Nursery
                    </span>
                </span>
            )}
        </span>
    );
}
