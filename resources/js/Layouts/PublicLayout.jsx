import { useState } from 'react';
import { AnimatePresence, motion } from 'framer-motion';
import { Link, usePage } from '@inertiajs/react';
import { GraduationCap, MapPin, Menu, Phone, X } from 'lucide-react';
import { Flash } from '../Components/ui';

const NAV_LINKS = [
    { label: 'Home', href: '/' },
    { label: 'About Us', href: '/#about' },
    { label: 'Programs', href: '/#programs' },
    { label: 'Admissions', href: '/#admissions' },
    { label: 'News & Events', href: '/#news' },
    { label: 'Contact Us', href: '/#contact' },
];

/** Brand logo — rounded monogram + wordmark (inline SVG, no asset needed). */
export function Logo({ dark = false, className = '' }) {
    return (
        <span className={`flex items-center gap-2.5 ${className}`}>
            <span className="relative grid h-11 w-11 shrink-0 place-items-center overflow-hidden rounded-2xl bg-primary text-white shadow-lg shadow-primary/30">
                <GraduationCap className="h-6 w-6" strokeWidth={2.3} />
                <span className="absolute -bottom-1.5 -right-1.5 h-4 w-4 rounded-full bg-accent" />
            </span>
            <span className="leading-tight">
                <span className={`block font-display text-lg font-extrabold ${dark ? 'text-white' : 'text-ink'}`}>
                    God-Win
                </span>
                <span className={`block text-[10px] font-bold uppercase tracking-widest ${dark ? 'text-white/70' : 'text-primary'}`}>
                    Daycare &amp; Nursery
                </span>
            </span>
        </span>
    );
}

export default function PublicLayout({ children }) {
    const { school, routes, auth } = usePage().props;
    const [drawerOpen, setDrawerOpen] = useState(false);

    return (
        <div className="flex min-h-screen flex-col bg-canvas">
            {/* ================= Announcement / quick contact bar ============ */}
            <div className="bg-ink text-white">
                <div className="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-2 px-4 py-2 text-xs font-semibold sm:px-6">
                    <div className="flex items-center gap-3">
                        <span className="hidden items-center gap-1.5 text-accent sm:flex">
                            <MapPin className="h-3.5 w-3.5" />
                            {school.location.display}
                        </span>
                        <span className="flex items-center gap-1.5">
                            <Phone className="h-3.5 w-3.5 text-accent" />
                            <a href={`tel:${school.phones[0]}`} className="transition hover:text-accent">
                                {school.phones[0]}
                            </a>
                        </span>
                        <span className="hidden sm:inline">
                            <a href={`tel:${school.phones[1]}`} className="transition hover:text-accent">
                                {school.phones[1]}
                            </a>
                        </span>
                    </div>
                    <span className="badge bg-primary text-white">
                        Nafasi za Masomo Mwaka {school.admissions_year}
                    </span>
                </div>
            </div>

            {/* ================= Sticky glass navigation ==================== */}
            <header className="sticky top-0 z-40 border-b border-white/60 bg-white/85 backdrop-blur-xl">
                <div className="mx-auto flex h-16 max-w-7xl items-center justify-between gap-4 px-4 sm:h-[72px] sm:px-6">
                    <Link href={routes.home} aria-label="Home">
                        <Logo />
                    </Link>

                    <nav className="hidden items-center gap-1 lg:flex">
                        {NAV_LINKS.map((link) => (
                            <a
                                key={link.label}
                                href={link.href}
                                className="rounded-lg px-3 py-2 text-sm font-bold text-slate-600 transition hover:bg-primary/5 hover:text-primary"
                            >
                                {link.label}
                            </a>
                        ))}
                    </nav>

                    <div className="flex items-center gap-2">
                        <Link
                            href={auth.user ? routes.dashboard : routes.login}
                            className="btn-ghost hidden sm:inline-flex"
                        >
                            {auth.user ? 'My Dashboard' : 'Staff Login'}
                        </Link>
                        <Link href={routes.apply} className="btn-primary hidden sm:inline-flex">
                            Apply Now
                        </Link>

                        <button
                            type="button"
                            onClick={() => setDrawerOpen(true)}
                            className="grid h-11 w-11 place-items-center rounded-xl border border-slate-200 text-ink lg:hidden"
                            aria-label="Open menu"
                        >
                            <Menu className="h-5 w-5" />
                        </button>
                    </div>
                </div>
            </header>

            {/* ================= Mobile drawer ============================== */}
            <AnimatePresence>
                {drawerOpen && (
                    <>
                        <motion.div
                            initial={{ opacity: 0 }}
                            animate={{ opacity: 1 }}
                            exit={{ opacity: 0 }}
                            onClick={() => setDrawerOpen(false)}
                            className="fixed inset-0 z-50 bg-ink/60 backdrop-blur-sm lg:hidden"
                        />
                        <motion.aside
                            initial={{ x: '100%' }}
                            animate={{ x: 0 }}
                            exit={{ x: '100%' }}
                            transition={{ type: 'tween', duration: 0.28, ease: [0.22, 1, 0.36, 1] }}
                            className="fixed inset-y-0 right-0 z-50 flex w-[85%] max-w-sm flex-col bg-white shadow-2xl lg:hidden"
                        >
                            <div className="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                                <Logo />
                                <button
                                    type="button"
                                    onClick={() => setDrawerOpen(false)}
                                    className="grid h-10 w-10 place-items-center rounded-xl bg-slate-100 text-slate-600"
                                    aria-label="Close menu"
                                >
                                    <X className="h-5 w-5" />
                                </button>
                            </div>

                            <nav className="flex-1 space-y-1 overflow-y-auto px-4 py-5">
                                {NAV_LINKS.map((link, i) => (
                                    <motion.a
                                        key={link.label}
                                        href={link.href}
                                        onClick={() => setDrawerOpen(false)}
                                        initial={{ opacity: 0, x: 24 }}
                                        animate={{ opacity: 1, x: 0 }}
                                        transition={{ delay: 0.05 + i * 0.05 }}
                                        className="block rounded-xl px-4 py-3 text-sm font-bold text-slate-700 transition hover:bg-primary/5 hover:text-primary"
                                    >
                                        {link.label}
                                    </motion.a>
                                ))}
                            </nav>

                            <div className="space-y-2 border-t border-slate-100 p-5">
                                <Link
                                    href={routes.apply}
                                    onClick={() => setDrawerOpen(false)}
                                    className="btn-primary w-full"
                                >
                                    Apply Now
                                </Link>
                                <Link
                                    href={auth.user ? routes.dashboard : routes.login}
                                    onClick={() => setDrawerOpen(false)}
                                    className="btn-ghost w-full"
                                >
                                    {auth.user ? 'My Dashboard' : 'Staff Login'}
                                </Link>
                                <div className="space-y-1 pt-2 text-xs font-semibold text-slate-500">
                                    {school.phones.map((phone) => (
                                        <p key={phone} className="flex items-center gap-2">
                                            <Phone className="h-3.5 w-3.5 text-primary" />
                                            <a href={`tel:${phone}`} className="hover:text-primary">{phone}</a>
                                        </p>
                                    ))}
                                </div>
                            </div>
                        </motion.aside>
                    </>
                )}
            </AnimatePresence>

            {/* __DRAWER_END__ */}

            {/* ================= Page body ================================== */}
            <main className="flex-1">
                <div className="mx-auto max-w-7xl px-4 pt-4 sm:px-6">
                    <Flash />
                </div>
                {children}
            </main>

            {/* ================= Footer ===================================== */}
            <footer className="mt-16 bg-secondary text-white">
                <div className="mx-auto grid max-w-7xl gap-10 px-4 py-12 sm:grid-cols-2 sm:px-6 lg:grid-cols-4">
                    {/* Brand */}
                    <div className="lg:col-span-2">
                        <Logo dark />
                        <p className="mt-4 max-w-md text-sm leading-relaxed text-white/80">
                            {school.motto}
                        </p>
                        <p className="mt-3 max-w-md text-sm leading-relaxed text-white/70">
                            {school.slogan} — {school.location.full}
                        </p>
                        <div className="mt-4 flex flex-wrap gap-2">
                            {school.phones.map((phone) => (
                                <a
                                    key={phone}
                                    href={`tel:${phone}`}
                                    className="badge bg-white/15 text-white transition hover:bg-accent hover:text-ink"
                                >
                                    {phone}
                                </a>
                            ))}
                        </div>
                    </div>

                    {/* Quick links */}
                    <div>
                        <h4 className="font-display text-sm font-bold uppercase tracking-wider text-accent">
                            Quick Links
                        </h4>
                        <ul className="mt-4 space-y-2 text-sm">
                            {NAV_LINKS.map((link) => (
                                <li key={link.label}>
                                    <a href={link.href} className="text-white/80 transition hover:text-accent">
                                        {link.label}
                                    </a>
                                </li>
                            ))}
                            <li>
                                <a href={routes.apply} className="text-white/80 transition hover:text-accent">
                                    Online Application
                                </a>
                            </li>
                        </ul>
                    </div>

                    {/* Programs */}
                    <div>
                        <h4 className="font-display text-sm font-bold uppercase tracking-wider text-accent">
                            Our Programs
                        </h4>
                        <ul className="mt-4 space-y-2 text-sm text-white/80">
                            <li>Daycare &amp; Infant Care (2–3)</li>
                            <li>Early Childhood / Nursery (3–5)</li>
                            <li>Spiritual &amp; Moral Character Building</li>
                            <li>Holistic Physical &amp; Educational Development</li>
                            <li>Safe &amp; Loving Environment</li>
                        </ul>
                    </div>
                </div>

                <div className="border-t border-white/15">
                    <div className="mx-auto flex max-w-7xl flex-col items-center justify-between gap-2 px-4 py-4 text-xs font-semibold text-white/70 sm:flex-row sm:px-6">
                        <p>
                            © {new Date().getFullYear()} {school.name}. All rights reserved.
                        </p>
                        <p className="text-accent">{school.motto}</p>
                    </div>
                </div>
            </footer>
        </div>
    );
}