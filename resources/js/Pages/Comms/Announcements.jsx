import { useState } from 'react';
import { router, useForm } from '@inertiajs/react';
import { Eye, EyeOff, Mail, Megaphone, Pencil, Pin, Plus, Trash2 } from 'lucide-react';
import DashboardLayout from '../../Layouts/DashboardLayout';
import { Badge, EmptyState } from '../../Components/ui';
import { ConfirmDialog, Modal } from '../../Components/modal';
import { Checkbox, Field, Select, TextInput, Textarea } from '../../Components/form';
import { PageHeader, PrimaryAction } from '../../Components/page';

const TYPE_TONES = { news: 'secondary', admission: 'accent', event: 'primary', announcement: 'muted' };
const actionBtn = 'grid h-8 w-8 place-items-center rounded-lg text-slate-400 transition';

/** ANNOUNCEMENTS & BROADCASTS — news, admissions and pastoral guidance. */
export default function Announcements({ announcements, types, audiences, contactMessages, filters }) {
    const [editing, setEditing] = useState(null);
    const [deleting, setDeleting] = useState(null);
    const [viewing, setViewing] = useState(null);

    const blank = {
        id: null, title: '', type: 'announcement', audience: 'everyone',
        excerpt: '', body: '', is_pinned: false, is_published: false,
    };

    const form = useForm(blank);

    const openCreate = () => {
        form.setData(blank);
        form.clearErrors();
        setEditing('new');
    };

    const openEdit = (item) => {
        form.setData({
            id: item.id, title: item.title, type: item.type, audience: item.audience,
            excerpt: item.excerpt ?? '', body: item.body ?? '',
            is_pinned: !!item.is_pinned, is_published: !!item.is_published,
        });
        form.clearErrors();
        setEditing(item);
    };

    const submit = (event) => {
        event.preventDefault();

        if (editing === 'new') {
            form.post('/announcements', { preserveScroll: true, onSuccess: () => setEditing(null) });
        } else {
            form.put(`/announcements/${editing.id}`, { preserveScroll: true, onSuccess: () => setEditing(null) });
        }
    };

    const togglePublish = (item) =>
        router.post(`/announcements/${item.id}/toggle-publish`, {}, { preserveScroll: true });

    const apply = (extra = {}) =>
        router.get(
            '/announcements',
            { search: filters.search ?? '', type: filters.type ?? '', audience: filters.audience ?? '', ...extra },
            { preserveState: true, replace: true }
        );

    return (
        <DashboardLayout title="Communications" subtitle="Announcements, broadcasts and website news.">
            <PageHeader
                title="Announcements"
                subtitle="Publishing notifies the selected audience instantly."
                action={<PrimaryAction onClick={openCreate} icon={Plus}>New Announcement</PrimaryAction>}
            />

            <div className="grid gap-6 lg:grid-cols-3">
                {/* ---------------- Announcement list ---------------- */}
                <div className="lg:col-span-2">
                    <div className="mb-4 flex flex-wrap gap-2">
                        <input
                            className="input max-w-xs"
                            placeholder="Search title…"
                            defaultValue={filters.search ?? ''}
                            onChange={(e) => apply({ search: e.target.value })}
                        />
                        <select className="input w-auto" value={filters.type ?? ''}
                            onChange={(e) => apply({ type: e.target.value })}>
                            <option value="">All types</option>
                            {types.map((t) => <option key={t.value} value={t.value}>{t.label}</option>)}
                        </select>
                        <select className="input w-auto" value={filters.audience ?? ''}
                            onChange={(e) => apply({ audience: e.target.value })}>
                            <option value="">All audiences</option>
                            {audiences.map((a) => <option key={a.value} value={a.value}>{a.label}</option>)}
                        </select>
                    </div>

                    <div className="space-y-3">
                        {announcements.data.length === 0 ? (
                            <EmptyState title="No announcements yet" hint="Publish your first notice for parents and staff." />
                        ) : (
                            announcements.data.map((item) => (
                                <article key={item.id} className="glass-card p-4 sm:p-5">
                                    <div className="flex flex-wrap items-start justify-between gap-3">
                                        <div className="min-w-0">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <Badge tone={TYPE_TONES[item.type] ?? 'muted'}>{item.type_label}</Badge>
                                                <Badge tone="secondary">{item.audience_label}</Badge>
                                                {item.is_pinned && <Badge tone="accent"><Pin className="h-3 w-3" /> Pinned</Badge>}
                                                <Badge tone={item.is_published ? 'success' : 'muted'}>
                                                    {item.is_published ? 'Published' : 'Draft'}
                                                </Badge>
                                            </div>
                                            <h3 className="mt-2 font-display text-base font-bold text-ink">{item.title}</h3>
                                            {item.excerpt && <p className="mt-1 text-sm text-slate-500">{item.excerpt}</p>}
                                            <p className="mt-1 text-xs text-slate-400">
                                                {item.author} · {item.published_at ?? item.created_at}
                                            </p>
                                        </div>
                                        <div className="flex items-center gap-1">
                                            <button type="button" onClick={() => setViewing(item)} title="Preview"
                                                className={`${actionBtn} hover:bg-slate-100 hover:text-secondary`}>
                                                <Eye className="h-4 w-4" />
                                            </button>
                                            <button type="button" onClick={() => togglePublish(item)} title="Publish / Unpublish"
                                                className={`${actionBtn} hover:bg-slate-100 hover:text-primary`}>
                                                {item.is_published ? <EyeOff className="h-4 w-4" /> : <Megaphone className="h-4 w-4" />}
                                            </button>
                                            <button type="button" onClick={() => openEdit(item)} title="Edit"
                                                className={`${actionBtn} hover:bg-slate-100 hover:text-primary`}>
                                                <Pencil className="h-4 w-4" />
                                            </button>
                                            <button type="button" onClick={() => setDeleting(item)} title="Delete"
                                                className={`${actionBtn} hover:bg-rose-50 hover:text-rose-600`}>
                                                <Trash2 className="h-4 w-4" />
                                            </button>
                                        </div>
                                    </div>
                                </article>
                            ))
                        )}
                    </div>
                </div>

                {/* ---------------- Website contact inbox ---------------- */}
                <div className="space-y-4">
                    <div className="glass-card p-5">
                        <h3 className="flex items-center gap-2 font-display text-sm font-bold text-ink">
                            <Mail className="h-4 w-4 text-secondary" />
                            Website Enquiries
                        </h3>
                        {contactMessages.length === 0 ? (
                            <p className="mt-3 text-sm text-slate-500">No new enquiries.</p>
                        ) : (
                            <ul className="mt-3 space-y-2.5">
                                {contactMessages.map((message) => (
                                    <li key={message.id} className="rounded-xl bg-canvas p-3">
                                        <div className="flex items-center justify-between gap-2">
                                            <p className="truncate text-sm font-bold text-ink">{message.name}</p>
                                            <span className="shrink-0 text-[10px] font-semibold text-slate-400">
                                                {message.created_at}
                                            </span>
                                        </div>
                                        <p className="truncate text-xs text-secondary">{message.email}</p>
                                        <p className="mt-1 text-xs text-slate-600">{message.message}</p>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>

                    <div className="rounded-2xl bg-gradient-to-br from-accent to-amber-500 p-5">
                        <h3 className="font-display text-sm font-bold text-ink">Broadcast tips</h3>
                        <ul className="mt-2 space-y-1.5 text-xs font-semibold text-ink/80">
                            <li>• Choose a precise audience — parents, staff or teachers.</li>
                            <li>• Published items appear on the public website immediately.</li>
                            <li>• Everyone in the audience gets a bell notification.</li>
                        </ul>
                    </div>
                </div>
            </div>

            {/* ---------------- Create / edit modal ---------------- */}
            <Modal
                open={!!editing}
                onClose={() => setEditing(null)}
                title={editing === 'new' ? 'New Announcement' : 'Edit Announcement'}
                subtitle="Published items notify the chosen audience and appear on the website."
                size="lg"
                footer={
                    <>
                        <button type="button" onClick={() => setEditing(null)} className="btn-ghost">Cancel</button>
                        <button type="button" onClick={submit} disabled={form.processing} className="btn-primary">
                            {form.processing ? 'Saving…' : 'Save Announcement'}
                        </button>
                    </>
                }
            >
                <form onSubmit={submit} className="grid gap-4 sm:grid-cols-2">
                    <Field label="Title" required error={form.errors.title} className="sm:col-span-2">
                        <TextInput
                            value={form.data.title}
                            onChange={(e) => form.setData('title', e.target.value)}
                            placeholder="e.g. Admission 2026 Now Open"
                        />
                    </Field>

                    <Field label="Type" required error={form.errors.type}>
                        <Select value={form.data.type} onChange={(e) => form.setData('type', e.target.value)}>
                            {types.map((t) => <option key={t.value} value={t.value}>{t.label}</option>)}
                        </Select>
                    </Field>

                    <Field label="Audience" required error={form.errors.audience}>
                        <Select value={form.data.audience} onChange={(e) => form.setData('audience', e.target.value)}>
                            {audiences.map((a) => <option key={a.value} value={a.value}>{a.label}</option>)}
                        </Select>
                    </Field>

                    <Field label="Short Excerpt" error={form.errors.excerpt} required={false} className="sm:col-span-2">
                        <TextInput
                            value={form.data.excerpt ?? ''}
                            onChange={(e) => form.setData('excerpt', e.target.value)}
                            placeholder="One-line summary shown on cards and the website"
                        />
                    </Field>

                    <Field label="Full Message" required error={form.errors.body} className="sm:col-span-2">
                        <Textarea
                            rows={5}
                            value={form.data.body ?? ''}
                            onChange={(e) => form.setData('body', e.target.value)}
                            placeholder="Write the full announcement…"
                        />
                    </Field>

                    <Checkbox
                        label="Pin to the top"
                        description="Keeps the announcement above others."
                        checked={!!form.data.is_pinned}
                        onChange={(e) => form.setData('is_pinned', e.target.checked)}
                    />

                    <Checkbox
                        label="Publish now"
                        description="Notifies the audience immediately."
                        checked={!!form.data.is_published}
                        onChange={(e) => form.setData('is_published', e.target.checked)}
                    />
                </form>
            </Modal>

            {/* ---------------- Preview modal ---------------- */}
            <Modal
                open={!!viewing}
                onClose={() => setViewing(null)}
                title={viewing?.title ?? ''}
                subtitle={`${viewing?.type_label ?? ''} · ${viewing?.audience_label ?? ''}`}
                size="lg"
                footer={<button type="button" onClick={() => setViewing(null)} className="btn-ghost">Close</button>}
            >
                {viewing?.excerpt && <p className="text-sm font-semibold text-primary">{viewing.excerpt}</p>}
                <p className="mt-3 whitespace-pre-line text-sm leading-relaxed text-slate-700">{viewing?.body}</p>
            </Modal>

            <ConfirmDialog
                open={!!deleting}
                onClose={() => setDeleting(null)}
                onConfirm={() => {
                    router.delete(`/announcements/${deleting.id}`, { preserveScroll: true });
                    setDeleting(null);
                }}
                title="Delete announcement?"
                message={`"${deleting?.title ?? ''}" will be removed from the website and portal.`}
                confirmLabel="Delete"
            />
        </DashboardLayout>
    );
}
