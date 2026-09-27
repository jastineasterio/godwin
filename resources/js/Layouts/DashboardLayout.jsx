import { useState } from 'react';
import { AnimatePresence, motion } from 'framer-motion';
import { Link, router, usePage } from '@inertiajs/react';
import {
    Bell,
    ChevronDown,
    LogOut,
    Menu,
    PanelLeftClose,
    PanelLeftOpen,
    Search,
    X,
} from 'lucide-react';
import { Flash } from '../Components/ui';
import { Logo } from './PublicLayout';
import { NAV_BY_ROLE, ROLE_LABELS } from './navigation';

/* ===========================================================================
 * Sidebar body — shared between the desktop rail and the mobile drawer
 * ======================================================================== */

function SidebarNav({ onNavigate }) {
    const { auth, url } = usePage().props;
    const role = auth.user?.role ?? 'parent';
    const items = NAV_BY_ROLE[role] ?? NAV_BY_ROLE.parent;

    return (
        <nav className="flex-1 space-y-1 overflow-y-auto px-3 py-4 scrollbar-thin">
            {items.map((item, i) => {
                // `alias` items share another page's URL, so they never highlight
                const active = !item.alias && item.href && url === item.href;
                const Icon = item.icon;

                if (!item.href) {
                    return (
                        <div
                            key={`${item.label}-${i}`}
                            className="flex cursor-not-allowed items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold text-slate-400"
                            title="Coming in the next release"
                        >
                            <Icon className="h-[18px] w-[18px] shrink-0" />
                            <span className="flex-1 truncate">{item.label}</span>
                            <span className="badge bg-slate-100 text-slate-400">Soon</span>
                        </div>
                    );
                }

                return (
                    <Link
                        key={item.label}
                        href={item.href}
                        onClick={onNavigate}
                        className={`flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-bold transition ${
                            active
                                ? 'bg-primary text-white shadow-lg shadow-primary/25'
                                : 'text-slate-600 hover:bg-primary/5 hover:text-primary'
                        }`}
                    >
                        <Icon className="h-[18px] w-[18px] shrink-0" strokeWidth={2.2} />
                        <span className="flex-1 truncate">{item.label}</span>
                    </Link>
                );
            })}
        </nav>
    );
}

/** Brand block + close button used at the top of both sidebar variants. */
function SidebarBrand({ onClose }) {
    return (
        <div className="flex items-center justify-between border-b border-slate-100 px-4 py-4">
            <Logo />
            {onClose && (
                <button
                    type="button"
                    onClick={onClose}
                    className="grid h-9 w-9 place-items-center rounded-xl bg-slate-100 text-slate-500"
                    aria-label="Close sidebar"
                >
                    <X className="h-4 w-4" />
                </button>
            )}
        </div>
    );
}

/* ===========================================================================
 * Header — mobile toggle, desktop collapse, search, notifications,
 * and the user profile dropdown (role label + logout).
 * ======================================================================== */

function DashboardHeader({ onOpenDrawer, collapsed, onToggleCollapse }) {
    const { auth, routes } = usePage().props;
    const user = auth.user;
    const [menuOpen, setMenuOpen] = useState(false);
    const [notificationsOpen, setNotificationsOpen] = useState(false);

    const initials = (user?.name ?? '?')
        .split(' ')
        .map((part) => part[0])
        .slice(0, 2)
        .join('')
        .toUpperCase();

    return (
        <header className="sticky top-0 z-30 border-b border-white/60 bg-white/80 backdrop-blur-xl">
            <div className="flex h-16 items-center gap-2 px-4 sm:px-6">
                {/* Mobile drawer toggle */}
                <button
                    type="button"
                    onClick={onOpenDrawer}
                    className="grid h-10 w-10 place-items-center rounded-xl border border-slate-200 text-slate-600 lg:hidden"
                    aria-label="Open navigation"
                >
                    <Menu className="h-5 w-5" />
                </button>

                {/* Desktop collapse toggle */}
                <button
                    type="button"
                    onClick={onToggleCollapse}
                    className="hidden h-10 w-10 place-items-center rounded-xl border border-slate-200 text-slate-500 transition hover:border-primary/40 hover:text-primary lg:grid"
                    aria-label={collapsed ? 'Expand sidebar' : 'Collapse sidebar'}
                >
                    {collapsed ? (
                        <PanelLeftOpen className="h-5 w-5" />
                    ) : (
                        <PanelLeftClose className="h-5 w-5" />
                    )}
                </button>

                {/* Search (desktop only) */}
                <label className="relative ml-1 hidden max-w-md flex-1 md:block">
                    <Search className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                    <input
                        type="search"
                        placeholder="Search students, invoices, announcements…"
                        className="input pl-10"
                    />
                </label>

                <div className="ml-auto flex items-center gap-2">
                    {/* Notifications */}
                    <div className="relative">
                        <button
                            type="button"
                            onClick={() => {
                                setNotificationsOpen((v) => !v);
                                setMenuOpen(false);
                            }}
                            className="relative grid h-10 w-10 place-items-center rounded-xl border border-slate-200 text-slate-600 transition hover:border-primary/40 hover:text-primary"
                            aria-label="Notifications"
                        >
                            <Bell className="h-5 w-5" />
                            <span className="absolute right-2 top-2 h-2 w-2 rounded-full bg-primary ring-2 ring-white" />
                        </button>

                        <AnimatePresence>
                            {notificationsOpen && (
                                <motion.div
                                    initial={{ opacity: 0, y: 8, scale: 0.97 }}
                                    animate={{ opacity: 1, y: 0, scale: 1 }}
                                    exit={{ opacity: 0, y: 8, scale: 0.97 }}
                                    transition={{ duration: 0.16 }}
                                    className="glass-card absolute right-0 top-12 w-72 overflow-hidden p-0"
                                >
                                    <p className="border-b border-slate-100 px-4 py-3 text-xs font-bold uppercase tracking-wider text-slate-400">
                                        Notifications
                                    </p>
                                    <div className="px-4 py-6 text-center text-sm text-slate-500">
                                        You&apos;re all caught up!
                                    </div>
                                </motion.div>
                            )}
                        </AnimatePresence>
                    </div>

                    {/* Profile dropdown */}
                    <div className="relative">
                        <button
                            type="button"
                            onClick={() => {
                                setMenuOpen((v) => !v);
                                setNotificationsOpen(false);
                            }}
                            className="flex items-center gap-2.5 rounded-xl border border-slate-200 py-1.5 pl-1.5 pr-2.5 transition hover:border-primary/40"
                        >
                            <span className="grid h-8 w-8 place-items-center rounded-lg bg-primary text-xs font-extrabold text-white">
                                {initials}
                            </span>
                            <span className="hidden text-left sm:block">
                                <span className="block max-w-[140px] truncate text-xs font-bold text-ink">
                                    {user?.name}
                                </span>
                                <span className="block text-[10px] font-bold uppercase tracking-wider text-primary">
                                    {ROLE_LABELS[user?.role] ?? user?.role}
                                </span>
                            </span>
                            <ChevronDown className="hidden h-4 w-4 text-slate-400 sm:block" />
                        </button>

                        <AnimatePresence>
                            {menuOpen && (
                                <motion.div
                                    initial={{ opacity: 0, y: 8, scale: 0.97 }}
                                    animate={{ opacity: 1, y: 0, scale: 1 }}
                                    exit={{ opacity: 0, y: 8, scale: 0.97 }}
                                    transition={{ duration: 0.16 }}
                                    className="glass-card absolute right-0 top-12 w-56 overflow-hidden p-0"
                                >
                                    <div className="border-b border-slate-100 px-4 py-3">
                                        <p className="truncate text-sm font-bold text-ink">{user?.name}</p>
                                        <p className="truncate text-xs text-slate-500">{user?.email}</p>
                                        <span className="badge mt-1.5 bg-primary/10 text-primary">
                                            {ROLE_LABELS[user?.role] ?? user?.role}
                                        </span>
                                    </div>
                                    <Link
                                        href={routes.home}
                                        className="block px-4 py-2.5 text-sm font-semibold text-slate-600 transition hover:bg-slate-50"
                                        onClick={() => setMenuOpen(false)}
                                    >
                                        View Public Website
                                    </Link>
                                    <button
                                        type="button"
                                        onClick={() => router.post(routes.logout)}
                                        className="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm font-semibold text-rose-600 transition hover:bg-rose-50"
                                    >
                                        <LogOut className="h-4 w-4" />
                                        Log Out
                                    </button>
                                </motion.div>
                            )}
                        </AnimatePresence>
                    </div>
                </div>
            </div>
        </header>
    );
}

/* ===========================================================================
 * Main shell — collapsible desktop sidebar, mobile drawer, page transitions
 * ======================================================================== */

export default function DashboardLayout({ children, title, subtitle }) {
    const [drawerOpen, setDrawerOpen] = useState(false);
    const [collapsed, setCollapsed] = useState(false);

    return (
        <div className="flex min-h-screen bg-canvas">
            {/* ---------- Desktop sidebar (collapsible rail) ---------------- */}
            <motion.aside
                animate={{ width: collapsed ? 76 : 264 }}
                transition={{ type: 'tween', duration: 0.25, ease: [0.22, 1, 0.36, 1] }}
                className="sticky top-0 hidden h-screen shrink-0 flex-col border-r border-slate-200/70 bg-white lg:flex"
            >
                <div className={`border-b border-slate-100 px-4 py-4 ${collapsed ? 'flex justify-center px-0' : ''}`}>
                    {collapsed ? (
                        <button
                            type="button"
                            onClick={() => setCollapsed(false)}
                            className="grid h-11 w-11 place-items-center rounded-2xl bg-primary font-display text-lg font-extrabold text-white shadow-lg shadow-primary/30"
                            aria-label="Expand sidebar"
                        >
                            G
                        </button>
                    ) : (
                        <Logo />
                    )}
                </div>

                {collapsed ? (
                    <CollapsedNav />
                ) : (
                    <SidebarNav />
                )}

                {!collapsed && (
                    <div className="border-t border-slate-100 p-3">
                        <button
                            type="button"
                            onClick={() => setCollapsed(true)}
                            className="flex w-full items-center gap-2 rounded-xl px-3 py-2 text-xs font-bold text-slate-400 transition hover:bg-slate-50 hover:text-primary"
                        >
                            <PanelLeftClose className="h-4 w-4" />
                            Collapse menu
                        </button>
                    </div>
                )}
            </motion.aside>

            {/* ---------- Mobile drawer ------------------------------------ */}
            <AnimatePresence>
                {drawerOpen && (
                    <>
                        <motion.div
                            initial={{ opacity: 0 }}
                            animate={{ opacity: 1 }}
                            exit={{ opacity: 0 }}
                            onClick={() => setDrawerOpen(false)}
                            className="fixed inset-0 z-40 bg-ink/60 backdrop-blur-sm lg:hidden"
                        />
                        <motion.aside
                            initial={{ x: '-100%' }}
                            animate={{ x: 0 }}
                            exit={{ x: '-100%' }}
                            transition={{ type: 'tween', duration: 0.25, ease: [0.22, 1, 0.36, 1] }}
                            className="fixed inset-y-0 left-0 z-50 flex w-[82%] max-w-xs flex-col bg-white shadow-2xl lg:hidden"
                        >
                            <SidebarBrand onClose={() => setDrawerOpen(false)} />
                            <SidebarNav onNavigate={() => setDrawerOpen(false)} />
                        </motion.aside>
                    </>
                )}
            </AnimatePresence>

            {/* ---------- Main column -------------------------------------- */}
            <div className="flex min-w-0 flex-1 flex-col">
                <DashboardHeader
                    onOpenDrawer={() => setDrawerOpen(true)}
                    collapsed={collapsed}
                    onToggleCollapse={() => setCollapsed((v) => !v)}
                />

                <main className="flex-1 px-4 py-6 sm:px-6 lg:px-8">
                    {(title || subtitle) && (
                        <div className="mb-6">
                            {title && (
                                <h1 className="font-display text-xl font-bold text-ink sm:text-2xl">
                                    {title}
                                </h1>
                            )}
                            {subtitle && (
                                <p className="mt-1 text-sm text-slate-500">{subtitle}</p>
                            )}
                        </div>
                    )}

                    <Flash className="mb-5" />

                    {/* Inertia page transition */}
                    <motion.div
                        initial={{ opacity: 0, y: 10 }}
                        animate={{ opacity: 1, y: 0 }}
                        transition={{ duration: 0.3, ease: 'easeOut' }}
                    >
                        {children}
                    </motion.div>
                </main>

                <footer className="border-t border-slate-200/70 px-4 py-4 text-center text-xs font-semibold text-slate-400 sm:px-6 lg:text-left">
                    © {new Date().getFullYear()} God-Win Daycare &amp; Nursery School · Guiding Every Child in
                    Goodness and Righteousness.
                </footer>
            </div>
        </div>
    );
}

/** Icon-only nav used when the desktop sidebar is collapsed. */
function CollapsedNav() {
    const { auth } = usePage().props;
    const items = NAV_BY_ROLE[auth.user?.role] ?? NAV_BY_ROLE.parent;

    return (
        <nav className="flex-1 space-y-1 overflow-y-auto px-3 py-4">
            {items.map((item, i) => {
                const Icon = item.icon;

                if (!item.href) {
                    return (
                        <div
                            key={`${item.label}-${i}`}
                            title={`${item.label} — coming soon`}
                            className="grid h-10 w-full cursor-not-allowed place-items-center rounded-xl text-slate-300"
                        >
                            <Icon className="h-[18px] w-[18px]" />
                        </div>
                    );
                }

                return (
                    <a
                        key={item.label}
                        href={item.href}
                        title={item.label}
                        className="grid h-10 w-full place-items-center rounded-xl text-slate-500 transition hover:bg-primary/5 hover:text-primary"
                    >
                        <Icon className="h-[18px] w-[18px]" strokeWidth={2.2} />
                    </a>
                );
            })}
        </nav>
    );
}