import { useState } from 'react';
import { router, useForm, usePage } from '@inertiajs/react';
import { Play, RefreshCw, Trash2, Upload } from 'lucide-react';
import DashboardLayout from '../../../Layouts/DashboardLayout';
import { Flash } from '../../../Components/ui';
import { ConfirmDialog, Modal } from '../../../Components/modal';
import { PageHeader, PrimaryAction } from '../../../Components/page';

/** 15 MB in bytes, mirrored from the backend validation. */
const MAX_FILE_SIZE = 15 * 1024 * 1024;

const ACCEPTED_TYPES = 'video/mp4,video/webm';

function formatBytes(bytes) {
    if (!bytes) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

export default function SettingsVideo({ video }) {
    const { flash } = usePage().props;
    const [showForm, setShowForm] = useState(false);
    const [confirmRemove, setConfirmRemove] = useState(false);

    const blank = { video: null };
    const form = useForm(blank);

    const hasVideo = !!video;

    const openUpload = () => {
        form.reset();
        form.setData('video', null);
        form.clearErrors();
        setShowForm(true);
    };

    const submit = (e) => {
        e.preventDefault();
        const file = form.data.video;
        if (!file) return;

        // Frontend size validation (early user feedback, backend is authoritative)
        if (file.size > MAX_FILE_SIZE) {
            form.errors.video = `File too large. Maximum size is ${formatBytes(MAX_FILE_SIZE)}.`;
            return;
        }

        const method = hasVideo ? 'put' : 'post';
        form[method]('/settings/video', {
            preserveScroll: true,
            onSuccess: () => setShowForm(false),
        });
    };

    const executeRemove = () => {
        router.delete('/settings/video', {
            preserveScroll: true,
            onSuccess: () => setConfirmRemove(false),
        });
    };

    return (
        <DashboardLayout title="School Introduction Video" subtitle="Manage the intro video shown on the public website.">
            <Flash className="mb-5" />
            {flash?.success && (
                <div className="mb-5 flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">
                    {flash.success}
                </div>
            )}
            {flash?.error && (
                <div className="mb-5 flex items-center gap-2 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-800">
                    {flash.error}
                </div>
            )}

            <PageHeader
                title="Introduction Video"
                subtitle={hasVideo ? 'Visitors can watch the school introduction video on the public homepage.' : 'No video has been uploaded yet. Upload one to display it on the public homepage.'}
                action={
                    <PrimaryAction onClick={openUpload} icon={Upload}>
                        {hasVideo ? 'Replace Video' : 'Upload Video'}
                    </PrimaryAction>
                }
            />

            {/* Current video preview panel */}
            <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div className="flex flex-col lg:flex-row lg:items-start lg:gap-8">
                    <div className="flex-1 min-w-0">
                        {hasVideo ? (
                            <>
                                <div className="mb-4 aspect-video w-full rounded-xl border border-slate-200 bg-slate-900 overflow-hidden">
                                    <video
                                        src={video.url}
                                        controls
                                        preload="metadata"
                                        className="h-full w-full object-contain"
                                        poster=""
                                    />
                                </div>
                                <div className="grid grid-cols-1 gap-2 sm:grid-cols-2 text-sm">
                                    <div className="rounded-lg bg-slate-50 px-3 py-2">
                                        <span className="block text-xs font-semibold text-slate-500">File name</span>
                                        <span className="text-ink">{video.original_name}</span>
                                    </div>
                                    <div className="rounded-lg bg-slate-50 px-3 py-2">
                                        <span className="block text-xs font-semibold text-slate-500">MIME type</span>
                                        <span className="text-ink">{video.mime_type}</span>
                                    </div>
                                    <div className="rounded-lg bg-slate-50 px-3 py-2">
                                        <span className="block text-xs font-semibold text-slate-500">Size</span>
                                        <span className="text-ink">{formatBytes(video.file_size)}</span>
                                    </div>
                                    <div className="rounded-lg bg-slate-50 px-3 py-2">
                                        <span className="block text-xs font-semibold text-slate-500">Status</span>
                                        <span className="text-emerald-700 font-semibold">Published (visible on public site)</span>
                                    </div>
                                </div>

                                <div className="mt-6 flex gap-3">
                                    <button
                                        type="button"
                                        onClick={openUpload}
                                        className="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                                    >
                                        <RefreshCw className="h-4 w-4" /> Replace Video
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => setConfirmRemove(true)}
                                        className="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-rose-600 transition hover:bg-rose-50"
                                    >
                                        <Trash2 className="h-4 w-4" /> Remove Video
                                    </button>
                                </div>
                            </>
                        ) : (
                            <div className="h-64 flex flex-col items-center justify-center rounded-xl border-2 border-dashed border-slate-200 bg-slate-50/50 text-center">
                                <span className="grid h-14 w-14 place-items-center rounded-full bg-slate-200 text-slate-400 mb-3">
                                    <Play className="h-6 w-6" />
                                </span>
                                <h3 className="font-semibold text-ink">No introduction video yet</h3>
                                <p className="mt-1 text-sm text-slate-500">
                                    Upload a school introduction video to display it on the public homepage.
                                </p>
                                <button
                                    type="button"
                                    onClick={openUpload}
                                    className="mt-4 btn-primary"
                                >
                                    <Upload className="h-4 w-4 mr-2" /> Upload Video
                                </button>
                            </div>
                        )}
                    </div>

                    {hasVideo && (
                        <aside className="lg:w-72 flex flex-col gap-4">
                            <div className="rounded-xl border border-slate-200 bg-slate-50 p-4">
                                <h4 className="text-sm font-bold text-ink mb-3">Supported formats</h4>
                                <ul className="space-y-2 text-xs text-slate-600">
                                    <li className="flex items-center gap-2">
                                        <span className="text-emerald-600">✓</span> MP4 (video/mp4)
                                    </li>
                                    <li className="flex items-center gap-2">
                                        <span className="text-emerald-600">✓</span> WebM (video/webm)
                                    </li>
                                </ul>
                                <h4 className="text-sm font-bold text-ink mb-3 mt-4">Upload limits</h4>
                                <ul className="space-y-2 text-xs text-slate-600">
                                    <li className="flex items-center gap-2">
                                        <span className="text-emerald-600">✓</span> Max {formatBytes(MAX_FILE_SIZE)}
                                    </li>
                                    <li className="flex items-center gap-2">
                                        <span className="text-emerald-600">✓</span> Admin-only management
                                    </li>
                                </ul>
                            </div>
                            <p className="text-xs text-slate-500 leading-relaxed">
                                The active video is shown on the public website between the
                                school identity pillars and the programs section. Only one video
                                can be active at a time.
                            </p>
                        </aside>
                    )}
                </div>
            </div>

            {/* Upload / replace form modal */}
            <Modal
                open={showForm}
                onClose={() => setShowForm(false)}
                title={hasVideo ? 'Replace Introduction Video' : 'Upload Introduction Video'}
                subtitle={hasVideo
                    ? 'The new video will replace the current one and remain the only active video.'
                    : 'Upload an MP4 or WebM video up to 15 MB.'}
                size="lg"
                footer={
                    <>
                        <button type="button" onClick={() => setShowForm(false)} className="btn-ghost">
                            Cancel
                        </button>
                        <button
                            type="submit"
                            form="video-form"
                            disabled={form.processing}
                            className="btn-primary"
                        >
                            {form.processing ? 'Uploading…' : hasVideo ? 'Replace Video' : 'Upload Video'}
                        </button>
                    </>
                }
            >
                <form id="video-form" onSubmit={submit} className="grid gap-4">
                    <div className="rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <div className="flex items-center gap-3 mb-2">
                            <Upload className="h-5 w-5 text-primary" />
                            <span className="text-sm font-semibold text-ink">Select video file</span>
                        </div>
                        <input
                            type="file"
                            accept={ACCEPTED_TYPES}
                            onChange={(e) => {
                                const file = e.target.files?.[0] ?? null;
                                form.setData('video', file);
                                if (file && file.size > MAX_FILE_SIZE) {
                                    form.errors.video = `File too large. Maximum size is ${formatBytes(MAX_FILE_SIZE)}.`;
                                } else {
                                    delete form.errors.video;
                                }
                            }}
                            className={`block w-full text-sm text-slate-500 file:mr-3 file:rounded-lg file:border-0 file:bg-primary file:px-3 file:py-1.5 file:text-xs file:font-bold file:text-white hover:file:bg-primary/90 cursor-pointer ${form.errors.video ? 'border-rose-300 focus:border-rose-500' : ''}`}
                        />
                        {form.errors.video && (
                            <p className="mt-1.5 text-xs font-semibold text-rose-600">{form.errors.video}</p>
                        )}
                        <p className="mt-2 text-xs text-slate-500">
                            MP4 or WebM only · maximum {formatBytes(MAX_FILE_SIZE)}
                        </p>
                    </div>
                </form>
            </Modal>

            {/* Remove confirmation dialog */}
            <ConfirmDialog
                open={confirmRemove}
                onClose={() => setConfirmRemove(false)}
                onConfirm={executeRemove}
                title="Remove introduction video?"
                message="The video file will be deleted from storage and removed from the public website. This cannot be undone."
                confirmLabel="Remove"
                processing={form.processing}
            />
        </DashboardLayout>
    );
}
