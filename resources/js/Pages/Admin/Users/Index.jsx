import { useState, useEffect } from 'react';
import { router, useForm, usePage } from '@inertiajs/react';
import { Plus, Search, SlidersHorizontal, Trash2, ShieldCheck, KeyRound } from 'lucide-react';
import DashboardLayout from '../../../Layouts/DashboardLayout';
import { Badge, EmptyState, Flash } from '../../../Components/ui';
import { ConfirmDialog, Modal } from '../../../Components/modal';
import { Field } from '../../../Components/form';
import { PageHeader, Pagination } from '../../../Components/page';

const BLANK_USER = {
    name: '',
    email: '',
    phone: '',
    role: 'teacher',
    status: 'active',
    password: '',
    password_confirmation: '',
};

export default function UsersIndex({
    users,
    roles,
    statuses,
    filters,
    showModal = false,
    editing = null,
}) {
    const { flash } = usePage().props;
    const [search, setSearch] = useState(filters?.search ?? '');
    const [roleFilter, setRoleFilter] = useState(filters?.role ?? '');
    const [statusFilter, setStatusFilter] = useState(filters?.status ?? '');
    const [deleting, setDeleting] = useState(null);
    const [passwordReset, setPasswordReset] = useState(null);
    const [modalOpen, setModalOpen] = useState(showModal ?? false);

    const form = useForm({ ...BLANK_USER });
    const pwForm = useForm({ password: '', password_confirmation: '' });

    useEffect(() => {
        if (editing) {
            form.setData({
                name: editing.name,
                email: editing.email,
                phone: editing.phone,
                role: editing.role,
                status: editing.status,
                password: '',
                password_confirmation: '',
            });
            setModalOpen(true);
        }
    }, [editing]);

    const applyFilters = (extra = {}) => {
        router.get(
            '/users',
            { search, role: roleFilter, status: statusFilter, ...extra },
            { preserveState: true, replace: true }
        );
    };

    const openCreate = () => {
        form.setData({ ...BLANK_USER });
        form.clearErrors();
        setModalOpen(true);
    };

    const submitCreate = (e) => {
        e.preventDefault();
        form.post('/users', {
            preserveScroll: true,
            onSuccess: () => {
                setModalOpen(false);
                form.reset();
            },
        });
    };

    const submitEdit = (e) => {
        e.preventDefault();
        form.put(`/users/${editing.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                setModalOpen(false);
            },
        });
    };

    const confirmDelete = (user) => setDeleting(user);
    const executeDelete = () => {
        router.delete(`/users/${deleting.id}`, {
            preserveScroll: true,
            onSuccess: () => setDeleting(null),
        });
    };

    const openPassword = (user) => {
        pwForm.reset();
        pwForm.clearErrors();
        setPasswordReset({ id: user.id, name: user.name });
    };

    const submitPassword = (e) => {
        e.preventDefault();
        pwForm.patch(`/users/${passwordReset.id}/password`, {
            preserveScroll: true,
            onSuccess: () => setPasswordReset(null),
        });
    };

    const closeModal = () => {
        setModalOpen(false);
        form.reset();
        const path = window.location.pathname;
        if (path !== '/users' && (path.includes('/users/create') || path.includes('/users/'))) {
            router.visit('/users', { preserveScroll: true });
        }
    };

    const handleStatusToggle = (user, newStatus) => {
        router.patch(`/users/${user.id}/status`, { status: newStatus }, {
            preserveScroll: true,
        });
    };

    const statusBadge = (status) => {
        const map = {
            active: 'success',
            inactive: 'muted',
            suspended: 'danger',
        };
        return <Badge tone={map[status] ?? 'muted'}>{status}</Badge>;
    };

    return (
        <DashboardLayout title="Staff" subtitle="User Administration">
            <Flash className="mb-5" />

            <PageHeader
                title={editing ? 'Edit User' : showModal ? 'New User' : 'Staff & Users'}
                subtitle={!editing && !showModal ? 'Manage accounts, roles and access for all system users.' : ''}
                action={
                    !editing && !showModal ? (
                        <button type="button" onClick={openCreate} className="btn-primary inline-flex items-center gap-2">
                            <Plus className="h-4 w-4" /> Add User
                        </button>
                    ) : null
                }
            />

            {/* Filters */}
            {!editing && !showModal && (
                <div className="mb-6 rounded-2xl border border-slate-200 bg-white p-4">
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-4">
                        <label className="block sm:col-span-2">
                            <span className="label">Search</span>
                            <div className="relative">
                                <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                                <input
                                    type="text"
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    onKeyDown={(e) => e.key === 'Enter' && applyFilters()}
                                    placeholder="Search by name, email or phone..."
                                    className="input pl-9"
                                />
                            </div>
                        </label>
                        <label className="block">
                            <span className="label">Role</span>
                            <select value={roleFilter} onChange={(e) => { setRoleFilter(e.target.value); applyFilters(); }} className="input">
                                <option value="">All roles</option>
                                {roles.map((r) => (
                                    <option key={r.value} value={r.value}>{r.label}</option>
                                ))}
                            </select>
                        </label>
                        <label className="block">
                            <span className="label">Status</span>
                            <select value={statusFilter} onChange={(e) => { setStatusFilter(e.target.value); applyFilters(); }} className="input">
                                <option value="">All statuses</option>
                                {statuses.map((s) => (
                                    <option key={s.value} value={s.value}>{s.label}</option>
                                ))}
                            </select>
                        </label>
                    </div>
                </div>
            )}

            {/* Users table */}
            {users && !editing && !showModal && (
                <>
                    {users.data.length === 0 ? (
                        <EmptyState
                            icon={SlidersHorizontal}
                            title="No users found"
                            hint="Try adjusting your search or filters, or create a new user account."
                        />
                    ) : (
                        <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                            <div className="overflow-x-auto">
                                <table className="min-w-full text-left text-sm">
                                    <thead>
                                        <tr className="border-b border-slate-100 bg-slate-50/60">
                                            <th className="px-4 py-3 font-semibold text-slate-500">Name</th>
                                            <th className="px-4 py-3 font-semibold text-slate-500">Email</th>
                                            <th className="px-4 py-3 font-semibold text-slate-500">Phone</th>
                                            <th className="px-4 py-3 font-semibold text-slate-500">Role</th>
                                            <th className="px-4 py-3 font-semibold text-slate-500">Status</th>
                                            <th className="px-4 py-3 font-semibold text-slate-500">Last Login</th>
                                            <th className="px-4 py-3 font-semibold text-slate-500">Created</th>
                                            <th className="px-4 py-3 font-semibold text-slate-500 text-right">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100">
                                        {users.data.map((u) => (
                                            <tr key={u.id} className="transition hover:bg-slate-50/50">
                                                <td className="px-4 py-3 font-semibold text-ink">{u.name}</td>
                                                <td className="px-4 py-3 text-slate-600">{u.email}</td>
                                                <td className="px-4 py-3 text-slate-600">{u.phone ?? '—'}</td>
                                                <td className="px-4 py-3 text-slate-600">{u.role_label}</td>
                                                <td className="px-4 py-3">{statusBadge(u.status)}</td>
                                                <td className="px-4 py-3 text-xs text-slate-500">{u.last_login_at ?? 'Never'}</td>
                                                <td className="px-4 py-3 text-xs text-slate-500">{u.created_at}</td>
                                                <td className="px-4 py-3">
                                                    <div className="flex items-center justify-end gap-1">
                                                        <select
                                                            value={u.status}
                                                            onChange={(e) => handleStatusToggle(u, e.target.value)}
                                                            className="input h-8 rounded-lg px-2 py-1 text-xs"
                                                            title="Change status"
                                                        >
                                                            {statuses.map((s) => (
                                                                <option key={s.value} value={s.value}>{s.label}</option>
                                                            ))}
                                                        </select>
                                                        <button
                                                            type="button"
                                                            onClick={() => router.visit(`/users/${u.id}/edit`)}
                                                            className="grid h-8 w-8 place-items-center rounded-lg text-slate-400 transition hover:bg-primary/10 hover:text-primary"
                                                            title="Edit"
                                                        >
                                                            <ShieldCheck className="h-4 w-4" />
                                                        </button>
                                                        <button
                                                            type="button"
                                                            onClick={() => openPassword(u)}
                                                            className="grid h-8 w-8 place-items-center rounded-lg text-slate-400 transition hover:bg-primary/10 hover:text-primary"
                                                            title="Reset password"
                                                        >
                                                            <KeyRound className="h-4 w-4" />
                                                        </button>
                                                        <button
                                                            type="button"
                                                            onClick={() => confirmDelete(u)}
                                                            className="grid h-8 w-8 place-items-center rounded-lg text-slate-400 transition hover:bg-rose-50 hover:text-rose-600"
                                                            title="Delete"
                                                        >
                                                            <Trash2 className="h-4 w-4" />
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                            {users.last_page > 1 && (
                                <div className="border-t border-slate-100 px-4 py-3">
                                    <Pagination meta={users} onPage={(page) => applyFilters({ page })} />
                                </div>
                            )}
                        </div>
                    )}
                </>
            )}

            {/* Create / Edit Modal */}
            {(showModal || editing) && (
                <Modal
                    open={modalOpen}
                    onClose={closeModal}
                    title={editing ? 'Edit User' : 'Add New User'}
                    subtitle={editing ? 'Update the account details below.' : 'Create a new staff or parent account.'}
                    size="lg"
                    footer={
                        <>
                            <button type="button" onClick={closeModal} className="btn-ghost">
                                Cancel
                            </button>
                            <button
                                type="submit"
                                form={editing ? 'edit-user-form' : 'create-user-form'}
                                disabled={form.processing}
                                className="btn-primary"
                            >
                                {form.processing ? 'Saving…' : editing ? 'Save Changes' : 'Create User'}
                            </button>
                        </>
                    }
                >
                    <form
                        id={editing ? 'edit-user-form' : 'create-user-form'}
                        onSubmit={editing ? submitEdit : submitCreate}
                        className="grid gap-4"
                    >
                        <Field label="Full Name" required error={form.errors.name}>
                            <input type="text" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} className="input" />
                        </Field>

                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <Field label="Email" required error={form.errors.email}>
                                <input type="email" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} className="input" />
                            </Field>
                            <Field label="Phone" error={form.errors.phone}>
                                <input type="tel" value={form.data.phone} onChange={(e) => form.setData('phone', e.target.value)} className="input" />
                            </Field>
                        </div>

                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <Field label="Role" required error={form.errors.role}>
                                <select value={form.data.role} onChange={(e) => form.setData('role', e.target.value)} className="input">
                                    {roles.map((r) => (
                                        <option key={r.value} value={r.value}>{r.label}</option>
                                    ))}
                                </select>
                            </Field>
                            <Field label="Status" required error={form.errors.status}>
                                <select value={form.data.status} onChange={(e) => form.setData('status', e.target.value)} className="input">
                                    {statuses.map((s) => (
                                        <option key={s.value} value={s.value}>{s.label}</option>
                                    ))}
                                </select>
                            </Field>
                        </div>

                        {!editing && (
                            <>
                                <Field label="Password" required error={form.errors.password}>
                                    <input type="password" value={form.data.password} onChange={(e) => form.setData('password', e.target.value)} className="input" />
                                </Field>
                                <Field label="Confirm Password" required error={form.errors.password_confirmation}>
                                    <input type="password" value={form.data.password_confirmation} onChange={(e) => form.setData('password_confirmation', e.target.value)} className="input" />
                                </Field>
                            </>
                        )}

                        {editing && (
                            <p className="text-xs text-slate-400">Leave the password fields empty to keep the current password.</p>
                        )}
                    </form>
                </Modal>
            )}

            {/* Delete confirmation */}
            <ConfirmDialog
                open={!!deleting}
                onClose={() => setDeleting(null)}
                onConfirm={executeDelete}
                title="Delete user?"
                message={`${deleting?.name ?? 'This user'} will be permanently removed. Their data will remain for audit purposes.`}
                confirmLabel="Delete"
            />

            {/* Password reset modal */}
            <Modal
                open={!!passwordReset}
                onClose={() => setPasswordReset(null)}
                title="Reset Password"
                subtitle={`Set a new password for ${passwordReset?.name ?? ''}.`}
                size="md"
                footer={
                    <>
                        <button type="button" onClick={() => setPasswordReset(null)} className="btn-ghost">Cancel</button>
                        <button type="submit" form="pw-reset-form" className="btn-primary">Reset Password</button>
                    </>
                }
            >
                <form id="pw-reset-form" onSubmit={submitPassword} className="grid gap-4">
                    <Field label="New Password" required error={pwForm.errors.password}>
                        <input type="password" value={pwForm.data.password} onChange={(e) => pwForm.setData('password', e.target.value)} className="input" />
                    </Field>
                    <Field label="Confirm New Password" required error={pwForm.errors.password_confirmation}>
                        <input type="password" value={pwForm.data.password_confirmation} onChange={(e) => pwForm.setData('password_confirmation', e.target.value)} className="input" />
                    </Field>
                </form>
            </Modal>
        </DashboardLayout>
    );
}
