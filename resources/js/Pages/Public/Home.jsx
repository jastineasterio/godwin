import { useEffect, useRef, useState } from 'react';
import { AnimatePresence, motion } from 'framer-motion';
import { Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    Baby,
    BookOpenCheck,
    CalendarDays,
    Eye,
    GraduationCap,
    HandHeart,
    Heart,
    MapPin,
    Phone,
    ShieldCheck,
    Sparkles,
} from 'lucide-react';
import PublicLayout from '../../Layouts/PublicLayout';
import { SectionHeading } from '../../Components/ui';

/* ===========================================================================
 * Hero slider — auto-rotating brand banners with CTA buttons
 * ======================================================================== */

const FALLBACK_SLIDES = [
    {
        title: 'Welcome to God-Win Daycare & Nursery School',
        subtitle: 'Guiding Every Child in Goodness and Righteousness.',
        badge: 'Admission 2026 Open',
        gradient: 'from-[#013157] via-[#01579B] to-[#0288D1]',
    },
    {
        title: 'Where Little Hearts Grow in God\u2019s Love',
        subtitle: 'Tunafundisha watoto kuanzia miaka 2-5 · Kisasani Medeli, Dodoma',
        badge: 'Ages 2 – 5 Years',
        gradient: 'from-[#01579B] via-[#0288D1] to-[#039BE5]',
    },
    {
        title: 'Safe, Loving & Holistic Care Every Day',
        subtitle: 'Spiritual, physical, and educational development — in a safe environment.',
        badge: 'Daycare · Nursery · KG1 · KG2',
        gradient: 'from-[#0288D1] via-[#039BE5] to-[#4FC3F7]',
    },
];

function HeroSlider({ banners }) {
    const slides = banners?.length
        ? banners.map((b) => ({
            title: b.title,
            subtitle: b.subtitle ?? '',
            badge: b.cta_label ?? 'Admission Open',
            image: b.image_path,
            gradient: 'from-[#013157] via-[#01579B] to-[#0288D1]',
        }))
        : FALLBACK_SLIDES;

    const [index, setIndex] = useState(0);
    const timer = useRef(null);

    useEffect(() => {
        timer.current = setInterval(() => setIndex((i) => (i + 1) % slides.length), 6000);

        return () => clearInterval(timer.current);
    }, [slides.length]);

    const slide = slides[index];

    return (
        <section className="relative isolate overflow-hidden">
            <AnimatePresence mode="wait">
                <motion.div
                    key={index}
                    initial={{ opacity: 0, scale: 1.04 }}
                    animate={{ opacity: 1, scale: 1 }}
                    exit={{ opacity: 0 }}
                    transition={{ duration: 0.7, ease: 'easeOut' }}
                    className={`absolute inset-0 bg-gradient-to-br ${slide.gradient}`}
                >
                    {slide.image && <img src={slide.image} alt="" className="h-full w-full object-cover opacity-30" />}
                    <span className="absolute -left-20 top-10 h-64 w-64 rounded-full bg-white/10 blur-2xl" />
                    <span className="absolute -right-16 bottom-0 h-72 w-72 rounded-full bg-accent/20 blur-2xl" />
                </motion.div>
            </AnimatePresence>

            <div className="relative mx-auto flex min-h-[440px] max-w-7xl flex-col items-center justify-center px-4 py-20 text-center text-white sm:min-h-[520px] sm:px-6 lg:min-h-[600px]">
                <motion.span
                    key={`badge-${index}`}
                    initial={{ opacity: 0, y: 12 }}
                    animate={{ opacity: 1, y: 0 }}
                    transition={{ delay: 0.1 }}
                    className="badge bg-accent text-ink shadow-lg"
                >
                    <Sparkles className="h-3.5 w-3.5" />
                    {slide.badge}
                </motion.span>

                <motion.h1
                    key={`title-${index}`}
                    initial={{ opacity: 0, y: 18 }}
                    animate={{ opacity: 1, y: 0 }}
                    transition={{ delay: 0.2, duration: 0.5 }}
                    className="mt-5 max-w-4xl text-balance font-display text-3xl font-extrabold leading-tight drop-shadow-sm sm:text-5xl lg:text-6xl"
                >
                    {slide.title}
                </motion.h1>

                <motion.p
                    key={`sub-${index}`}
                    initial={{ opacity: 0, y: 18 }}
                    animate={{ opacity: 1, y: 0 }}
                    transition={{ delay: 0.32, duration: 0.5 }}
                    className="mt-4 max-w-2xl text-balance text-sm font-medium text-white/90 sm:text-lg"
                >
                    {slide.subtitle}
                </motion.p>

                <motion.div
                    key={`cta-${index}`}
                    initial={{ opacity: 0, y: 18 }}
                    animate={{ opacity: 1, y: 0 }}
                    transition={{ delay: 0.44, duration: 0.5 }}
                    className="mt-8 flex flex-col gap-3 sm:flex-row"
                >
                    <Link href="/apply" className="btn-accent px-7 py-3.5 text-base shadow-xl">
                        Apply for Admission 2026
                        <ArrowRight className="h-4 w-4" />
                    </Link>
                    <a
                        href="#programs"
                        className="btn border-2 border-white/80 bg-transparent px-7 py-3.5 text-base text-white backdrop-blur hover:bg-white/10"
                    >
                        Learn More
                    </a>
                </motion.div>

                <div className="mt-10 flex gap-2">
                    {slides.map((_, i) => (
                        <button
                            key={i}
                            type="button"
                            onClick={() => setIndex(i)}
                            aria-label={`Go to slide ${i + 1}`}
                            className={`h-2 rounded-full transition-all duration-300 ${
                                i === index ? 'w-8 bg-accent' : 'w-2 bg-white/50 hover:bg-white/80'
                            }`}
                        />
                    ))}
                </div>
            </div>
        </section>
    );
}

/* ===========================================================================
 * Vision / Mission / Motto cards
 * ======================================================================== */

function PillarCards({ school }) {
    const pillars = [
        {
            icon: Eye,
            label: 'Our Vision',
            text: school.vision,
            tone: 'bg-primary/10 text-primary',
            ring: 'bg-primary',
        },
        {
            icon: Heart,
            label: 'Our Mission',
            text: school.mission,
            tone: 'bg-secondary/10 text-secondary',
            ring: 'bg-secondary',
        },
        {
            icon: ShieldCheck,
            label: 'Our Motto',
            text: school.motto,
            tone: 'bg-accent/25 text-amber-700',
            ring: 'bg-accent',
        },
    ];

    return (
        <section id="about" className="mx-auto max-w-7xl scroll-mt-24 px-4 py-16 sm:px-6 lg:py-20">
            <SectionHeading
                eyebrow="Who We Are"
                title="Vision, Mission & Motto"
                description="Three promises that shape everything we do at God-Win Daycare & Nursery School."
            />

            <div className="grid gap-6 md:grid-cols-3">
                {pillars.map((pillar, i) => (
                    <motion.article
                        key={pillar.label}
                        initial={{ opacity: 0, y: 24 }}
                        whileInView={{ opacity: 1, y: 0 }}
                        viewport={{ once: true, margin: '-60px' }}
                        transition={{ delay: i * 0.12, duration: 0.5 }}
                        className="glass-card relative overflow-hidden p-6 text-center transition-shadow hover:shadow-card-hover"
                    >
                        <span className={`absolute inset-x-0 top-0 h-1.5 ${pillar.ring}`} />
                        <span className={`mx-auto grid h-16 w-16 place-items-center rounded-2xl ${pillar.tone}`}>
                            <pillar.icon className="h-8 w-8" strokeWidth={2.2} />
                        </span>
                        <h3 className="mt-4 font-display text-lg font-bold text-ink">{pillar.label}</h3>
                        <p className="mt-3 text-sm leading-relaxed text-slate-600">{pillar.text}</p>
                    </motion.article>
                ))}
            </div>
        </section>
    );
}

/* ===========================================================================
 * Our Programs (interactive cards + gold admissions CTA tile)
 * ======================================================================== */

const PROGRAMS = [
    {
        icon: Baby,
        title: 'Daycare & Infant Care',
        age: 'Ages 2 – 3',
        text: 'Gentle, attentive care for our youngest learners — nap time, feeding, play and first steps in a nurturing atmosphere.',
        tone: 'bg-primary/10 text-primary',
    },
    {
        icon: GraduationCap,
        title: 'Early Childhood / Nursery School',
        age: 'Ages 3 – 5',
        text: 'Structured KG1 & KG2 readiness covering phonics, numeracy, creativity and school routines that prepare children for primary school.',
        tone: 'bg-secondary/10 text-secondary',
    },
    {
        icon: Sparkles,
        title: 'Spiritual & Moral Character Building',
        age: 'Everyday',
        text: 'Bible-based stories, prayers and character lessons that raise children who are morally upright, God-loving and self-aware.',
        tone: 'bg-accent/25 text-amber-700',
    },
    {
        icon: HandHeart,
        title: 'Holistic Physical & Educational Development',
        age: 'Whole child',
        text: 'Balanced nutrition, outdoor play, music, movement and academics — nurturing spiritual, physical and educational growth together.',
        tone: 'bg-emerald-100 text-emerald-700',
    },
    {
        icon: ShieldCheck,
        title: 'Safe & Loving Environment',
        age: 'Peace of mind',
        text: 'A secure, loving campus where every child is known by name and parents rest assured their little one is protected.',
        tone: 'bg-rose-100 text-rose-700',
    },
];

function ProgramsSection() {
    return (
        <section id="programs" className="scroll-mt-24 bg-white py-16 lg:py-20">
            <div className="mx-auto max-w-7xl px-4 sm:px-6">
                <SectionHeading
                    eyebrow="What We Offer"
                    title="Our Programs"
                    description="Five pillars of early-years education for children aged 2 to 5 years."
                />

                <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    {PROGRAMS.map((program, i) => (
                        <motion.article
                            key={program.title}
                            initial={{ opacity: 0, y: 24 }}
                            whileInView={{ opacity: 1, y: 0 }}
                            viewport={{ once: true, margin: '-60px' }}
                            transition={{ delay: (i % 3) * 0.1, duration: 0.45 }}
                            className="glass-card group p-6 transition-all duration-300 hover:-translate-y-1 hover:shadow-card-hover"
                        >
                            <span className={`grid h-14 w-14 place-items-center rounded-2xl ${program.tone}`}>
                                <program.icon className="h-7 w-7" strokeWidth={2.2} />
                            </span>
                            <h3 className="mt-4 font-display text-base font-bold text-ink sm:text-lg">
                                {program.title}
                            </h3>
                            <span className="badge mt-2 bg-slate-100 text-slate-500">{program.age}</span>
                            <p className="mt-3 text-sm leading-relaxed text-slate-600">{program.text}</p>
                            <span className="mt-4 inline-flex items-center gap-1 text-sm font-bold text-primary opacity-0 transition-opacity group-hover:opacity-100">
                                Learn more →
                            </span>
                        </motion.article>
                    ))}

                    {/* Gold CTA tile completes the 3-column grid */}
                    <motion.aside
                        initial={{ opacity: 0, y: 24 }}
                        whileInView={{ opacity: 1, y: 0 }}
                        viewport={{ once: true }}
                        transition={{ duration: 0.45 }}
                        className="flex flex-col justify-center rounded-2xl bg-gradient-to-br from-accent to-amber-500 p-6 text-ink shadow-card"
                    >
                        <h3 className="font-display text-xl font-extrabold">
                            Nafasi za Masomo Mwaka 2026
                        </h3>
                        <p className="mt-2 text-sm font-medium text-ink/80">
                            Applications are open! Give your child a faith-filled start today.
                        </p>
                        <Link href="/apply" className="btn-primary mt-5 w-full">
                            Apply Now
                            <ArrowRight className="h-4 w-4" />
                        </Link>
                    </motion.aside>
                </div>
            </div>
        </section>
    );
}

/* ===========================================================================
 * News & Events
 * ======================================================================== */

function NewsSection({ news, events }) {
    return (
        <section id="news" className="mx-auto max-w-7xl scroll-mt-24 px-4 py-16 sm:px-6 lg:py-20">
            <SectionHeading
                eyebrow="Stay Updated"
                title="News & Events"
                description="Latest announcements and upcoming activities from our school family."
            />

            <div className="grid gap-6 lg:grid-cols-5">
                {/* News list (3 cols) */}
                <div className="space-y-4 lg:col-span-3">
                    {news?.length ? (
                        news.map((post, i) => (
                            <motion.article
                                key={post.id}
                                initial={{ opacity: 0, y: 18 }}
                                whileInView={{ opacity: 1, y: 0 }}
                                viewport={{ once: true }}
                                transition={{ delay: i * 0.08, duration: 0.4 }}
                                className="glass-card flex items-start gap-4 p-5 transition-shadow hover:shadow-card-hover"
                            >
                                <span className="grid h-12 w-12 shrink-0 place-items-center rounded-xl bg-primary/10 text-primary">
                                    <BookOpenCheck className="h-6 w-6" />
                                </span>
                                <div className="min-w-0">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <span className="badge bg-secondary/10 text-secondary capitalize">
                                            {post.type}
                                        </span>
                                        <time className="text-xs font-semibold text-slate-400">
                                            {new Date(post.published_at).toLocaleDateString('en-GB', {
                                                day: 'numeric',
                                                month: 'short',
                                                year: 'numeric',
                                            })}
                                        </time>
                                    </div>
                                    <h3 className="mt-1.5 font-display text-base font-bold text-ink">
                                        {post.title}
                                    </h3>
                                    {post.excerpt && (
                                        <p className="mt-1 line-clamp-2 text-sm text-slate-500">{post.excerpt}</p>
                                    )}
                                </div>
                            </motion.article>
                        ))
                    ) : (
                        <div className="glass-card p-8 text-center text-sm text-slate-500">
                            No news published yet — check back soon!
                        </div>
                    )}
                </div>

                {/* Upcoming events (2 cols) */}
                <div className="glass-card p-5 lg:col-span-2">
                    <h3 className="flex items-center gap-2 font-display text-base font-bold text-ink">
                        <CalendarDays className="h-5 w-5 text-secondary" />
                        Upcoming Events
                    </h3>
                    <ul className="mt-4 space-y-3">
                        {events?.length ? (
                            events.map((event) => (
                                <li key={event.id} className="flex items-start gap-3 rounded-xl bg-canvas p-3">
                                    <span
                                        className="grid h-11 w-11 shrink-0 place-items-center rounded-lg text-center text-white"
                                        style={{ backgroundColor: event.color || '#01579B' }}
                                    >
                                        <span className="text-[10px] font-extrabold leading-none">
                                            {new Date(event.start_date).getDate()}
                                            <br />
                                            {new Date(event.start_date)
                                                .toLocaleString('en', { month: 'short' })
                                                .toUpperCase()}
                                        </span>
                                    </span>
                                    <div className="min-w-0">
                                        <p className="truncate text-sm font-bold text-ink">{event.title}</p>
                                        <p className="truncate text-xs text-slate-500">
                                            {event.location || 'School campus'}
                                        </p>
                                    </div>
                                </li>
                            ))
                        ) : (
                            <li className="rounded-xl bg-canvas p-4 text-center text-sm text-slate-500">
                                No upcoming events scheduled.
                            </li>
                        )}
                    </ul>
                </div>
            </div>
        </section>
    );
}

/* ===========================================================================
 * Location, contact details & Google Maps
 * ======================================================================== */

function ContactSection({ school }) {
    const mapSrc = `https://www.google.com/maps?q=${encodeURIComponent(school.location.full)}&output=embed`;

    return (
        <section id="contact" className="scroll-mt-24 bg-white py-16 lg:py-20">
            <div className="mx-auto max-w-7xl px-4 sm:px-6">
                <SectionHeading
                    eyebrow="Visit Us"
                    title="Location & Contact"
                    description="Conveniently located in Medeli, Dodoma — near Njedengwa Railway Grounds."
                />

                <div className="grid gap-6 lg:grid-cols-2">
                    {/* Contact details */}
                    <div className="glass-card p-6 sm:p-8">
                        <h3 className="font-display text-lg font-bold text-ink">{school.name}</h3>
                        <p className="mt-2 text-sm font-semibold text-primary">{school.motto}</p>

                        <ul className="mt-6 space-y-4 text-sm">
                            <li className="flex items-start gap-3">
                                <span className="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-primary/10 text-primary">
                                    <MapPin className="h-5 w-5" />
                                </span>
                                <span>
                                    <span className="block font-bold text-ink">Our Location</span>
                                    <span className="text-slate-500">{school.location.full}</span>
                                </span>
                            </li>
                            <li className="flex items-start gap-3">
                                <span className="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-secondary/10 text-secondary">
                                    <Phone className="h-5 w-5" />
                                </span>
                                <span>
                                    <span className="block font-bold text-ink">Call / WhatsApp</span>
                                    <span className="mt-1 flex flex-wrap gap-2">
                                        {school.phones.map((phone) => (
                                            <a
                                                key={phone}
                                                href={`tel:${phone}`}
                                                className="badge bg-canvas text-slate-600 transition hover:bg-primary hover:text-white"
                                            >
                                                {phone}
                                            </a>
                                        ))}
                                    </span>
                                </span>
                            </li>
                        </ul>

                        <div className="mt-6 rounded-xl bg-accent/15 p-4 text-sm font-semibold text-ink">
                            🎓 {school.slogan}
                        </div>

                        <Link href="/apply" className="btn-primary mt-6 w-full">
                            Apply for Admission {school.admissions_year}
                            <ArrowRight className="h-4 w-4" />
                        </Link>
                    </div>

                    {/* Google Maps embed */}
                    <div className="glass-card overflow-hidden p-0">
                        <iframe
                            src={mapSrc}
                            title="God-Win Daycare & Nursery School location"
                            className="h-80 w-full border-0 sm:h-full sm:min-h-[380px]"
                            loading="lazy"
                            allowFullScreen
                            referrerPolicy="no-referrer-when-downgrade"
                        />
                    </div>
                </div>
            </div>
        </section>
    );
}

/* ===========================================================================
 * Page composition
 * ======================================================================== */

export default function Home({ banners, news, events }) {
    const { school } = usePage().props;

    // Support /#section deep links from the nav on an already-loaded page
    useEffect(() => {
        if (window.location.hash) {
            document.querySelector(window.location.hash)?.scrollIntoView({ behavior: 'smooth' });
        }
    }, []);

    return (
        <PublicLayout>
            <HeroSlider banners={banners} />
            <PillarCards school={school} />
            <ProgramsSection />
            <NewsSection news={news} events={events} />
            <ContactSection school={school} />
        </PublicLayout>
    );
}
