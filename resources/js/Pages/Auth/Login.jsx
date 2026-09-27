import { useForm, usePage } from '@inertiajs/react';
import { motion } from 'framer-motion';
import { AlertTriangle, LockKeyhole, Mail } from 'lucide-react';
import { Logo } from '../../Layouts/PublicLayout';

/* ===========================================================================
 * Staff / Parent sign-in (students NEVER log in).
 * ======================================================================== */

export default function Login({ status, canResetPassword }) {
    const { routes, school, errors } = usePage().props;
    const { data, setData, post, processing } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const submit = (event) => {
        event.preventDefault();
        post(routes.login);
    };

    return (
        <div className="relative flex min-h-screen items-center justify-center overflow-hidden bg-ink px-4 py-10">
            {/* Brand background */}
            <span className="absolute -left-32 -top-32 h-96 w-96 rounded-full bg-primary/40 blur-3xl" />
            <span className="absolute -bottom-40 -right-24 h-[28rem] w-[28rem] rounded-full bg-secondary/40 blur-3xl" />
            <span className="absolute left-1/2 top-1/3 h-56 w-56 -translate-x-1/2 rounded-full bg-accent/20 blur-3xl" />

            <motion.div
                initial={{ opacity: 0, y: 24, scale: 0.97 }}
                animate={{ opacity: 1, y: 0, scale: 1 }}
                transition={{ duration: 0.4, ease: 'easeOut' }}
                className="relative w-full max-w-md"
            >
                <div className="rounded-3xl border border-white/10 bg-white p-7 shadow-2xl sm:p-9">
                    <div className="flex flex-col items-center text-center">
                        <Logo />
                        <h1 className="mt-6 font-display text-2xl font-extrabold text-ink">
                            Welcome back
                        </h1>
                        <p className="mt-1.5 text-sm text-slate-500">
                            Sign in to your {school.short} dashboard
                        </p>
                    </div>

                    {(status || errors?.email) && (
                        <div className="mt-5 flex items-start gap-2 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-800">
                            <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" />
                            <span>{errors?.email ?? status}</span>
                        </div>
                    )}

                    <form onSubmit={submit} className="mt-6 space-y-4">
                        <label className="block">
                            <span className="label">Email address</span>
                            <span className="relative block">
                                <Mail className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                                <input
                                    type="email"
                                    required
                                    autoComplete="email"
                                    value={data.email}
                                    onChange={(e) => setData('email', e.target.value)}
                                    className="input pl-10"
                                    placeholder="you@godwin.ac.tz"
                                />
                            </span>
                        </label>

                        <label className="block">
                            <span className="label">Password</span>
                            <span className="relative block">
                                <LockKeyhole className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                                <input
                                    type="password"
                                    required
                                    autoComplete="current-password"
                                    value={data.password}
                                    onChange={(e) => setData('password', e.target.value)}
                                    className="input pl-10"
                                    placeholder="••••••••"
                                />
                            </span>
                        </label>

                        <div className="flex items-center justify-between text-sm">
                            <label className="flex cursor-pointer items-center gap-2 font-semibold text-slate-600">
                                <input
                                    type="checkbox"
                                    checked={data.remember}
                                    onChange={(e) => setData('remember', e.target.checked)}
                                    className="h-4 w-4 rounded border-slate-300 text-primary focus:ring-primary/30"
                                />
                                Remember me
                            </label>
                            {canResetPassword && (
                                <a href="#" className="font-bold text-primary hover:underline">
                                    Forgot password?
                                </a>
                            )}
                        </div>

                        <button type="submit" disabled={processing} className="btn-primary w-full py-3">
                            {processing ? 'Signing in…' : 'Sign In'}
                        </button>
                    </form>

                    <p className="mt-6 rounded-xl bg-canvas p-3 text-center text-xs font-semibold leading-relaxed text-slate-500">
                        Students do not have accounts. Parents, teachers and staff sign in above —
                        contact the school office for credentials.
                    </p>
                </div>

                <a
                    href={routes.home}
                    className="mt-5 block text-center text-sm font-bold text-white/70 transition hover:text-accent"
                >
                    ← Back to {school.name}
                </a>
            </motion.div>
        </div>
    );
}