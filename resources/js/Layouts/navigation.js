import {
    Baby,
    Banknote,
    BarChart3,
    Bell,
    BookOpen,
    CalendarDays,
    ClipboardCheck,
    FileText,
    GraduationCap,
    Home,
    LayoutDashboard,
    Megaphone,
    MessageSquare,
    Settings,
    ShieldCheck,
    Sparkles,
    Users,
    UsersRound,
} from 'lucide-react';

/**
 * Role-aware sidebar navigation.
 *
 * `href` items are live Phase-3 module routes. Items without `href` are
 * modules outside the Phase-3 scope and render as disabled "Soon" placeholders
 * rather than dead links.
 */
export const NAV_BY_ROLE = {
    admin: [
        { label: 'Overview', href: '/dashboard', icon: LayoutDashboard },
        { label: 'Students', href: '/students', icon: Users },
        { label: 'Attendance', href: '/attendance', icon: ClipboardCheck },
        { label: 'Assessments', href: '/assessments', icon: BookOpen },
        { label: 'Invoices & Fees', href: '/invoices', icon: Banknote },
        { label: 'Announcements', href: '/announcements', icon: Bell },
        { label: 'Messages', href: '/messages', icon: MessageSquare },
        { label: 'Staff & Users', icon: UsersRound, soon: true },
        { label: 'Audit Logs', href: '/dashboard', icon: ShieldCheck, alias: true },
        { label: 'Settings', icon: Settings, soon: true },
    ],
    senior_pastor: [
        { label: 'Overview', href: '/dashboard', icon: Home },
        { label: 'Broadcasts', href: '/announcements', icon: Megaphone },
        { label: 'Messages', href: '/messages', icon: MessageSquare },
        { label: 'Character Notes', href: '/students', icon: Sparkles, alias: true },
        { label: 'Reports', href: '/dashboard', icon: BarChart3, alias: true },
    ],
    head_of_school: [
        { label: 'Overview', href: '/dashboard', icon: Home },
        { label: 'Students', href: '/students', icon: GraduationCap },
        { label: 'Attendance', href: '/attendance', icon: ClipboardCheck },
        { label: 'Assessments', href: '/assessments', icon: BookOpen },
        { label: 'Invoices & Fees', href: '/invoices', icon: Banknote },
        { label: 'Announcements', href: '/announcements', icon: Bell },
        { label: 'Messages', href: '/messages', icon: MessageSquare },
        { label: 'Timetable', icon: CalendarDays, soon: true },
        { label: 'Events', icon: CalendarDays, soon: true },
    ],
    teacher: [
        { label: 'Overview', href: '/dashboard', icon: Home },
        { label: 'Take Attendance', href: '/attendance', icon: ClipboardCheck },
        { label: 'Assessments', href: '/assessments', icon: BookOpen },
        { label: 'Students', href: '/students', icon: Users },
        { label: 'Messages', href: '/messages', icon: MessageSquare },
        { label: 'Homework', icon: FileText, soon: true },
        { label: 'My Classes', icon: Baby, soon: true },
    ],
    accountant: [
        { label: 'Overview', href: '/dashboard', icon: Home },
        { label: 'Invoices', href: '/invoices', icon: FileText },
        { label: 'Generate Invoice', href: '/invoices/create', icon: Banknote },
        { label: 'Fee Structures', href: '/fee-structures', icon: Banknote },
        { label: 'Messages', href: '/messages', icon: MessageSquare },
        { label: 'Reports', href: '/dashboard', icon: BarChart3, alias: true },
    ],
    parent: [
        { label: 'Overview', href: '/dashboard', icon: Home },
        { label: 'Fee Statements', href: '/dashboard', icon: Banknote, alias: true },
        { label: 'Messages', href: '/messages', icon: MessageSquare },
        { label: 'Announcements', href: '/announcements', icon: Bell },
        { label: 'Assessment Results', href: '/dashboard', icon: BookOpen, alias: true },
        { label: 'Homework Tracker', icon: FileText, soon: true },
        { label: 'School Calendar', icon: CalendarDays, soon: true },
    ],
};

/** Human labels for the six RBAC roles. */
export const ROLE_LABELS = {
    admin: 'Administrator',
    senior_pastor: 'Senior Pastor',
    head_of_school: 'Head of School',
    teacher: 'Teacher',
    accountant: 'Accountant',
    parent: 'Parent / Guardian',
};
