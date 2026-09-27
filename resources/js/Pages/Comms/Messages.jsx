import { useEffect, useRef, useState } from 'react';
import { router, useForm } from '@inertiajs/react';
import { MessageSquare, Send } from 'lucide-react';
import DashboardLayout from '../../Layouts/DashboardLayout';
import { Badge, EmptyState } from '../../Components/ui';
import { Modal } from '../../Components/modal';
import { Field, Select, Textarea } from '../../Components/form';
import { PageHeader } from '../../Components/page';

const initials = (name = '') =>
    name.split(' ').map((part) => part[0]).slice(0, 2).join('');

/**
 * DIRECT MESSAGING — parent ↔ teacher conversations.
 * Contacts are server-filtered (parents only see their children's teachers).
 */
export default function Messages({ conversations, active, contacts }) {
    const [composeOpen, setComposeOpen] = useState(false);

    const composeForm = useForm({ user_id: contacts[0]?.id ?? '', subject: '', body: '' });
    const replyForm = useForm({ body: '' });

    const endRef = useRef(null);

    // Keep the newest message in view
    useEffect(() => {
        endRef.current?.scrollIntoView({ behavior: 'smooth', block: 'end' });
    }, [active?.messages?.length]);

    const sendReply = (event) => {
        event.preventDefault();
        replyForm.post(`/messages/${active.id}`, {
            preserveScroll: true,
            onSuccess: () => replyForm.reset(),
        });
    };

    const startConversation = (event) => {
        event.preventDefault();
        composeForm.post('/messages', {
            onSuccess: () => {
                setComposeOpen(false);
                composeForm.reset();
            },
        });
    };

    return (
        <DashboardLayout title="Messages" subtitle="Direct messages with teachers and parents.">
            <PageHeader
                title="Messages"
                subtitle={`${conversations.length} conversation(s)`}
                action={
                    <button type="button" onClick={() => setComposeOpen(true)} className="btn-primary">
                        <MessageSquare className="h-4 w-4" /> New Message
                    </button>
                }
            />

            <div className="grid gap-5 lg:grid-cols-3">
                {/* ---------------- Conversation list ---------------- */}
                <div className="glass-card p-3">
                    <div className="max-h-[32rem] space-y-1 overflow-y-auto">
                        {conversations.length === 0 ? (
                            <EmptyState title="No conversations yet" hint="Start a message with a teacher or parent." />
                        ) : (
                            conversations.map((conversation) => {
                                const isActive = active?.id === conversation.id;

                                return (
                                    <button
                                        key={conversation.id}
                                        type="button"
                                        onClick={() =>
                                            router.get('/messages', { conversation: conversation.id },
                                                { preserveState: true, preserveScroll: true })
                                        }
                                        className={`flex w-full items-center gap-3 rounded-xl p-3 text-left transition ${
                                            isActive ? 'bg-primary text-white' : 'hover:bg-canvas'
                                        }`}
                                    >
                                        <span
                                            className={`grid h-10 w-10 shrink-0 place-items-center rounded-xl text-xs font-extrabold ${
                                                isActive ? 'bg-white/20' : 'bg-secondary/10 text-secondary'
                                            }`}
                                        >
                                            {initials(conversation.other?.name)}
                                        </span>
                                        <span className="min-w-0 flex-1">
                                            <span className="flex items-center justify-between gap-2">
                                                <span className="truncate text-sm font-bold">
                                                    {conversation.other?.name ?? 'Unknown'}
                                                </span>
                                                {conversation.unread > 0 && <Badge tone="primary">{conversation.unread}</Badge>}
                                            </span>
                                            <span className="block truncate text-xs opacity-70">
                                                {conversation.last_message?.body ?? 'No messages yet'}
                                            </span>
                                        </span>
                                    </button>
                                );
                            })
                        )}
                    </div>
                </div>

                {/* ---------------- Active thread ---------------- */}
                <div className="glass-card flex max-h-[32rem] flex-col lg:col-span-2">
                    {!active ? (
                        <EmptyState
                            title="Select a conversation"
                            hint="Pick a thread on the left, or start a new message."
                        />
                    ) : (
                        <>
                            <header className="flex items-center gap-3 border-b border-slate-100 p-4">
                                <span className="grid h-10 w-10 place-items-center rounded-xl bg-primary/10 text-xs font-extrabold text-primary">
                                    {initials(active.other?.name)}
                                </span>
                                <div className="min-w-0">
                                    <p className="truncate font-display text-sm font-bold text-ink">
                                        {active.other?.name}
                                    </p>
                                    <p className="text-xs capitalize text-slate-500">
                                        {String(active.other?.role ?? '').replace('_', ' ')}
                                    </p>
                                </div>
                            </header>

                            <div className="flex-1 space-y-3 overflow-y-auto bg-canvas p-4">
                                {active.messages.map((message) => (
                                    <div
                                        key={message.id}
                                        className={`flex ${message.is_mine ? 'justify-end' : 'justify-start'}`}
                                    >
                                        <div
                                            className={`max-w-[80%] rounded-2xl px-4 py-2.5 text-sm shadow-sm ${
                                                message.is_mine
                                                    ? 'rounded-br-sm bg-primary text-white'
                                                    : 'rounded-bl-sm bg-white text-ink'
                                            }`}
                                        >
                                            <p className="whitespace-pre-line">{message.body}</p>
                                            <p
                                                className={`mt-1 text-right text-[10px] ${
                                                    message.is_mine ? 'text-white/70' : 'text-slate-400'
                                                }`}
                                            >
                                                {message.time}
                                            </p>
                                        </div>
                                    </div>
                                ))}
                                <div ref={endRef} />
                            </div>

                            <form onSubmit={sendReply} className="flex items-end gap-2 border-t border-slate-100 p-3">
                                <textarea
                                    rows={1}
                                    className="input max-h-32 flex-1 resize-none"
                                    placeholder="Type a reply…"
                                    value={replyForm.data.body}
                                    onChange={(e) => replyForm.setData('body', e.target.value)}
                                    onKeyDown={(e) => {
                                        if (e.key === 'Enter' && !e.shiftKey) {
                                            e.preventDefault();
                                            sendReply(e);
                                        }
                                    }}
                                />
                                <button
                                    type="submit"
                                    disabled={replyForm.processing || !replyForm.data.body}
                                    className="btn-primary h-[42px] px-4"
                                >
                                    <Send className="h-4 w-4" />
                                </button>
                            </form>
                        </>
                    )}
                </div>
            </div>

            {/* ---------------- Compose modal ---------------- */}
            <Modal
                open={composeOpen}
                onClose={() => setComposeOpen(false)}
                title="New Message"
                subtitle="You can only message parents or teachers connected to your children."
                footer={
                    <>
                        <button type="button" onClick={() => setComposeOpen(false)} className="btn-ghost">Cancel</button>
                        <button type="button" onClick={startConversation} disabled={composeForm.processing}
                            className="btn-primary">
                            {composeForm.processing ? 'Sending…' : 'Send Message'}
                        </button>
                    </>
                }
            >
                <form onSubmit={startConversation} className="space-y-4">
                    <Field label="Recipient" required error={composeForm.errors.user_id}>
                        <Select
                            value={composeForm.data.user_id}
                            onChange={(e) => composeForm.setData('user_id', e.target.value)}
                        >
                            {contacts.map((contact) => (
                                <option key={contact.id} value={contact.id}>
                                    {contact.name} — {contact.role}
                                </option>
                            ))}
                        </Select>
                    </Field>

                    <Field label="Subject" error={composeForm.errors.subject} required={false}>
                        <input
                            className="input"
                            value={composeForm.data.subject ?? ''}
                            onChange={(e) => composeForm.setData('subject', e.target.value)}
                            placeholder="Optional subject"
                        />
                    </Field>

                    <Field label="Message" required error={composeForm.errors.body}>
                        <Textarea
                            rows={4}
                            value={composeForm.data.body}
                            onChange={(e) => composeForm.setData('body', e.target.value)}
                            placeholder="Type your message…"
                        />
                    </Field>
                </form>
            </Modal>
        </DashboardLayout>
    );
}