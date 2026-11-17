import { useState } from 'react';
import { Link, router } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import DashboardLayout from '../../Layouts/DashboardLayout';
import { Badge, Card, EmptyState } from '../../Components/ui';
import { ConfirmDialog, Modal } from '../../Components/modal';
import { PrimaryAction } from '../../Components/page';

export default function EventsIndex({ events, canManage, audiences }) {
    const [deleting, setDeleting] = useState(null);

    return (
        <DashboardLayout title="Communications" subtitle="School events and calendar.">
            <div className="mb-6 flex flex-wrap items-start justify-between gap-3">
                <div className="min-w-0">
                    <h1 className="font-display text-xl font-extrabold text-ink sm:text-2xl">Events</h1>
                    <p className="mt-1 text-sm text-slate-500">{events?.total ?? 0} event(s)</p>
                </div>
                {canManage && (
                    <Link href="/events/create" className="btn-primary">
                        <Plus className="h-4 w-4" />
                        Create Event
                    </Link>
                )}
            </div>

            <div className="space-y-4">
                {events?.data?.length === 0 ? (
                    <Card>
                        <EmptyState title="No events found" hint="Create an event to get started." />
                    </Card>
                ) : (
                    events?.data?.map((event) => (
                        <Card key={event.id} delay={0}>
                            <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div className="min-w-0 flex-1">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <h3 className="font-display text-base font-bold text-ink sm:text-lg">{event.title}</h3>
                                        {!event.is_published && (
                                            <Badge tone="muted">Draft</Badge>
                                        )}
                                        <Badge tone={event.is_upcoming ? 'success' : 'muted'}>
                                            {event.is_upcoming ? 'Upcoming' : 'Past'}
                                        </Badge>
                                    </div>
                                    {event.description && (
                                        <p className="mt-1.5 text-sm text-slate-600 line-clamp-2">{event.description}</p>
                                    )}
                                    <div className="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs font-semibold text-slate-500">
                                        <span className="capitalize">{event.audience_label}</span>
                                        <span>{event.start_date}{event.end_date && event.end_date !== event.start_date ? ` – ${event.end_date}` : ''}</span>
                                        {event.start_time && <span>{event.start_time}{event.end_time ? ` – ${event.end_time}` : ''}</span>}
                                        {event.location && <span>{event.location}</span>}
                                        <span>By {event.creator}</span>
                                    </div>
                                </div>
                                {canManage && (
                                    <div className="flex items-center gap-2 sm:shrink-0">
                                        <Link
                                            href={`/events/${event.id}/edit`}
                                            className="btn-ghost px-3 py-1.5 text-xs"
                                        >
                                            Edit
                                        </Link>
                                        <button
                                            type="button"
                                            onClick={() => setDeleting(event)}
                                            className="btn bg-rose-50 px-3 py-1.5 text-xs text-rose-600 hover:bg-rose-100"
                                        >
                                            <Trash2 className="h-3.5 w-3.5" />
                                        </button>
                                    </div>
                                )}
                            </div>
                        </Card>
                    ))
                )}
            </div>

            {events?.last_page > 1 && (
                <div className="mt-5 flex items-center justify-between">
                    <p className="text-xs font-semibold text-slate-500">
                        Showing {((events.current_page ?? 1) - 1) * (events.per_page ?? 15) + 1}–
                        {Math.min((events.current_page ?? 1) * (events.per_page ?? 15), events.total ?? 0)} of {events.total ?? 0}
                    </p>
                    <div className="flex items-center gap-1">
                        <button
                            type="button"
                            disabled={(events.current_page ?? 1) <= 1}
                            onClick={() => router.get('/events', { page: (events.current_page ?? 1) - 1 }, { preserveState: true })}
                            className="grid h-9 w-9 place-items-center rounded-xl border border-slate-200 text-slate-600 disabled:opacity-40"
                        >
                            ←
                        </button>
                        <span className="px-3 text-xs font-bold text-ink">
                            {events.current_page ?? 1} / {events.last_page ?? 1}
                        </span>
                        <button
                            type="button"
                            disabled={(events.current_page ?? 1) >= (events.last_page ?? 1)}
                            onClick={() => router.get('/events', { page: (events.current_page ?? 1) + 1 }, { preserveState: true })}
                            className="grid h-9 w-9 place-items-center rounded-xl border border-slate-200 text-slate-600 disabled:opacity-40"
                        >
                            →
                        </button>
                    </div>
                </div>
            )}

            <ConfirmDialog
                open={!!deleting}
                onClose={() => setDeleting(null)}
                onConfirm={() => {
                    router.delete(`/events/${deleting.id}`, { preserveScroll: true });
                    setDeleting(null);
                }}
                title="Delete event?"
                message={`"${deleting?.title ?? 'This event'}" will be permanently removed.`}
                confirmLabel="Delete"
            />
        </DashboardLayout>
    );
}
