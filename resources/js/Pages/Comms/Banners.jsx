import { useState } from 'react';
import { router, useForm } from '@inertiajs/react';
import { Image, Plus, Trash2, Upload } from 'lucide-react';
import DashboardLayout from '../../Layouts/DashboardLayout';
import { Badge, EmptyState } from '../../Components/ui';
import { ConfirmDialog, Modal } from '../../Components/modal';
import { Field } from '../../Components/form';
import { PageHeader, PrimaryAction } from '../../Components/page';
import { Flash } from '../../Components/ui';

/* ------------------------------------------------------------------ */
/* File input primitive (not in the shared form.jsx)                  */
/* ------------------------------------------------------------------ */

function FileInputField({ label, error, required = false, ...props }) {
    return (
        <label className="block">
            <span className="label">
                {label} {required && <span className="text-primary">*</span>}
            </span>
            <input
                type="file"
                accept="image/jpeg,image/png,image/webp"
                {...props}
                className={`input file:mr-3 file:rounded-lg file:border-0 file:bg-primary file:px-3 file:py-1.5 file:text-xs file:font-bold file:text-white hover:file:bg-primary/90 ${error ? 'border-rose-300 focus:border-rose-500' : ''}`}
            />
            {error ? (
                <span className="mt-1 block text-xs font-semibold text-rose-600">{error}</span>
            ) : (
                <span className="mt-1 block text-xs text-slate-400">JPG, PNG or WEBP · max 5 MB</span>
            )}
        </label>
    );
}

/* ------------------------------------------------------------------ */
/* Page                                                                */
/* ------------------------------------------------------------------ */

const SLOT_LABELS = ['Poster 1', 'Poster 2', 'Poster 3', 'Poster 4'];

export default function Banners({ banners, activeCount, maxBanners }) {
    const atMax = activeCount >= maxBanners;
    const [showForm, setShowForm] = useState(false);
    const [deleting, setDeleting] = useState(null);

    const blank = {
        title: '',
        subtitle: '',
        image: null,
        link: '',
        cta_label: '',
    };

    const form = useForm(blank);

    const openCreate = () => {
        form.setData(blank);
        form.clearErrors();
        setShowForm(true);
    };

    const submit = (e) => {
        e.preventDefault();
        form.post('/banners', {
            preserveScroll: true,
            onSuccess: () => setShowForm(false),
        });
    };

    const confirmDelete = (banner) => {
        setDeleting(banner);
    };

    const executeDelete = () => {
        router.delete(`/banners/${deleting.id}`, {
            preserveScroll: true,
            onSuccess: () => setDeleting(null),
        });
    };

    const resolveUrl = (path) => {
        if (!path) return '';
        if (path.startsWith('http://') || path.startsWith('https://')) return path;
        return path.startsWith('storage/') ? `/${path}` : `/storage/${path.replace(/^\//, '')}`;
    };

    return (
        <DashboardLayout title="CMS" subtitle="Home Page Poster Management">
            <Flash className="mb-5" />

            <PageHeader
                title="Home Page Posters"
                subtitle={`Manage the hero carousel on the public website. Maximum ${maxBanners} posters allowed.`}
                action={
                    <PrimaryAction
                        onClick={openCreate}
                        icon={Upload}
                        disabled={atMax}
                    >
                        Upload Poster
                    </PrimaryAction>
                }
            />

            {atMax && (
                <div className="mb-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-800">
                    Maximum of {maxBanners} posters reached. Delete an existing poster before uploading a new one.
                </div>
            )}

            <div className="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
                {Array.from({ length: maxBanners }).map((_, i) => {
                    const banner = banners[i];

                    if (!banner) {
                        return (
                            <div
                                key={`slot-${i}`}
                                className="flex flex-col items-center justify-center gap-3 rounded-2xl border-2 border-dashed border-slate-200 bg-slate-50/50 p-8 text-center"
                            >
                                <span className="grid h-12 w-12 place-items-center rounded-full bg-slate-100 text-slate-400">
                                    <Image className="h-6 w-6" />
                                </span>
                                <div>
                                    <p className="text-sm font-bold text-slate-500">{SLOT_LABELS[i]}</p>
                                    <p className="mt-0.5 text-xs text-slate-400">Empty — upload a poster to fill this slot</p>
                                </div>
                            </div>
                        );
                    }

                    return (
                        <div
                            key={banner.id}
                            className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition-shadow hover:shadow-md"
                        >
                            <div className="relative aspect-[16/9] w-full bg-slate-100">
                                <img
                                    src={resolveUrl(banner.image_url)}
                                    alt={banner.title}
                                    className="h-full w-full object-cover"
                                    loading="lazy"
                                />
                                <span className="absolute left-2.5 top-2.5">
                                    <Badge tone={banner.is_active ? 'success' : 'muted'}>
                                        {banner.is_active ? 'Active' : 'Inactive'}
                                    </Badge>
                                </span>
                                <span className="absolute right-2.5 top-2.5 rounded-lg bg-ink/60 px-2 py-0.5 text-[10px] font-bold text-white">
                                    #{banner.sort_order}
                                </span>
                            </div>

                            <div className="p-4">
                                <h3 className="truncate text-sm font-bold text-ink">{banner.title}</h3>
                                {banner.subtitle && (
                                    <p className="mt-0.5 truncate text-xs text-slate-500">{banner.subtitle}</p>
                                )}
                                {banner.cta_label && (
                                    <p className="mt-1 text-[11px] font-semibold text-primary">CTA: {banner.cta_label}</p>
                                )}

                                <div className="mt-3 flex items-center justify-between gap-2">
                                    <span className="text-[10px] font-semibold text-slate-400">
                                        Slot {banner.sort_order} of {maxBanners}
                                    </span>
                                    <button
                                        type="button"
                                        onClick={() => confirmDelete(banner)}
                                        title="Delete poster"
                                        className="grid h-8 w-8 place-items-center rounded-lg text-slate-400 transition hover:bg-rose-50 hover:text-rose-600"
                                    >
                                        <Trash2 className="h-4 w-4" />
                                    </button>
                                </div>
                            </div>
                        </div>
                    );
                })}
            </div>

            {banners.length === 0 && (
                <EmptyState
                    icon={Image}
                    title="No posters uploaded yet"
                    hint="Upload up to 4 posters to display in the home page carousel."
                />
            )}

            {/* ---------------- Upload form modal ---------------- */}
            <Modal
                open={showForm}
                onClose={() => setShowForm(false)}
                title="Upload Poster"
                subtitle="Add a new poster to the home page carousel."
                size="lg"
                footer={
                    <>
                        <button type="button" onClick={() => setShowForm(false)} className="btn-ghost">
                            Cancel
                        </button>
                        <button type="submit" form="poster-form" disabled={form.processing} className="btn-primary">
                            {form.processing ? 'Uploading…' : 'Upload Poster'}
                        </button>
                    </>
                }
            >
                <form id="poster-form" onSubmit={submit} className="grid gap-4">
                    <Field label="Poster Title" required error={form.errors.title}>
                        <input
                            type="text"
                            value={form.data.title}
                            onChange={(e) => form.setData('title', e.target.value)}
                            placeholder="e.g. Admission 2026 Open"
                            maxLength={150}
                            className="input"
                        />
                    </Field>

                    <Field label="Subtitle" error={form.errors.subtitle}>
                        <input
                            type="text"
                            value={form.data.subtitle}
                            onChange={(e) => form.setData('subtitle', e.target.value)}
                            placeholder="Short description shown below the title (optional)"
                            maxLength={300}
                            className="input"
                        />
                    </Field>

                    <FileInputField
                        label="Poster Image"
                        required
                        error={form.errors.image}
                        onChange={(e) => {
                            const file = e.target.files?.[0] ?? null;
                            form.setData('image', file);
                        }}
                    />

                    <Field label="Link URL" error={form.errors.link}>
                        <input
                            type="url"
                            value={form.data.link}
                            onChange={(e) => form.setData('link', e.target.value)}
                            placeholder="/apply (optional — where the CTA button points)"
                            maxLength={500}
                            className="input"
                        />
                    </Field>

                    <Field label="CTA Label" error={form.errors.cta_label}>
                        <input
                            type="text"
                            value={form.data.cta_label}
                            onChange={(e) => form.setData('cta_label', e.target.value)}
                            placeholder="Apply Now (optional — button text)"
                            maxLength={50}
                            className="input"
                        />
                    </Field>
                </form>
            </Modal>

            {/* ---------------- Delete confirmation dialog ---------------- */}
            <ConfirmDialog
                open={!!deleting}
                onClose={() => setDeleting(null)}
                onConfirm={executeDelete}
                title="Delete poster?"
                message={`"${deleting?.title ?? ''}" will be permanently removed from the home page carousel and its image file will be deleted from storage.`}
                confirmLabel="Delete"
            />
        </DashboardLayout>
    );
}
