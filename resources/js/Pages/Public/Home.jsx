import { useEffect, useRef, useState } from 'react';
import { motion } from 'framer-motion';
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
    Play,
    ShieldCheck,
    Sparkles,
} from 'lucide-react';
import PublicLayout from '../../Layouts/PublicLayout';
import { SectionHeading } from '../../Components/ui';
import { Logo } from '../../Components/Logo';

/* ===========================================================================
 * Hero slider — full-bleed poster images managed via admin CMS
 *
 * Behaviour:
 *   0 posters → static default hero with CTA (no broken image, no errors)
 *   1 poster  → static display (no auto-rotation)
 *   2–4 posters → auto-rotate every 3 seconds with smooth cross-fade
 * ======================================================================== */

const DEFAULT_HERO = {
    title: 'Welcome to God-Win Daycare & Nursery School',
    subtitle: 'Guiding Every Child in Goodness and Righteousness.',
    badge: 'Admission 2026 Open',
    gradient: 'from-[#013157] via-[#01579B] to-[#0288D1]',
};

function HeroSlider({ banners }) {
    const hasBanners = banners && banners.length > 0;

    const slides = hasBanners
        ? banners.map((b) => ({
              id: b.id,
              title: b.title,
              subtitle: b.subtitle ?? '',
              badge: b.cta_label ?? 'Admission Open',
              image: b.image_path,
              link: b.link,
              gradient: 'from-[#013157] via-[#01579B] to-[#0288D1]',
          }))
        : [{ ...DEFAULT_HERO, isDefault: true }];

    const [index, setIndex] = useState(0);
    const [imgLoaded, setImgLoaded] = useState(false);
    const [imgError, setImgError] = useState(false);
    const timerRef = useRef(null);
    const currentSlide = slides[index];
    const shouldRotate = hasBanners && slides.length > 1;

    useEffect(() => {
        setImgLoaded(false);
        setImgError(false);

        if (timerRef.current) clearInterval(timerRef.current);

        if (shouldRotate) {
            timerRef.current = setInterval(() => {
                setIndex((i) => (i + 1) % slides.length);
            }, 9000);
        }

        return () => {
            if (timerRef.current) clearInterval(timerRef.current);
        };
    }, [slides.length, shouldRotate]);

    const showImage = !currentSlide.isDefault && currentSlide.image && !imgError;
    const bgGradient = `bg-gradient-to-br ${currentSlide.gradient}`;

    return (
        <section className={`relative isolate overflow-hidden ${bgGradient}`}>
            {/* ---- Background image layer (cross-faded) ---- */}
            {showImage && (
                <img
                    key={currentSlide.id ?? currentSlide.image}
                    src={currentSlide.image}
                    alt=""
                    onLoad={() => setImgLoaded(true)}
                    onError={() => setImgError(true)}
                    loading={index === 0 ? 'eager' : 'lazy'}
                    className={`absolute inset-0 h-full w-full object-cover transition-opacity duration-700 ${
                        imgLoaded ? 'opacity-40' : 'opacity-0'
                    }`}
                />
            )}

            {/* Fallback decorative blobs when no poster image is shown */}
            {!showImage && (
                <>
                    <span className="absolute -left-20 top-10 h-64 w-64 rounded-full bg-white/10 blur-2xl" />
                    <span className="absolute -right-16 bottom-0 h-72 w-72 rounded-full bg-accent/20 blur-2xl" />
                </>
            )}

            {/* ---- Content overlay ---- */}
            <div className="relative mx-auto flex min-h-[440px] max-w-7xl flex-col items-center justify-center px-4 py-20 text-center text-white sm:min-h-[520px] sm:px-6 lg:min-h-[600px]">
                <motion.span
                    key={`badge-${index}`}
                    initial={{ opacity: 0, y: 12 }}
                    animate={{ opacity: 1, y: 0 }}
                    transition={{ delay: 0.1 }}
                    className="badge bg-accent text-ink shadow-lg"
                >
                    <Sparkles className="h-3.5 w-3.5" />
                    {currentSlide.badge}
                </motion.span>

                <motion.h1
                    key={`title-${index}`}
                    initial={{ opacity: 0, y: 18 }}
                    animate={{ opacity: 1, y: 0 }}
                    transition={{ delay: 0.2, duration: 0.5 }}
                    className="mt-5 max-w-4xl text-balance font-display text-3xl font-extrabold leading-tight drop-shadow-sm sm:text-5xl lg:text-6xl"
                >
                    {currentSlide.title}
                </motion.h1>

                {currentSlide.subtitle && (
                    <motion.p
                        key={`sub-${index}`}
                        initial={{ opacity: 0, y: 18 }}
                        animate={{ opacity: 1, y: 0 }}
                        transition={{ delay: 0.32, duration: 0.5 }}
                        className="mt-4 max-w-2xl text-balance text-sm font-medium text-white/90 sm:text-lg"
                    >
                        {currentSlide.subtitle}
                    </motion.p>
                )}

                {(currentSlide.link || currentSlide.isDefault) && (
                    <motion.div
                        key={`cta-${index}`}
                        initial={{ opacity: 0, y: 18 }}
                        animate={{ opacity: 1, y: 0 }}
                        transition={{ delay: 0.44, duration: 0.5 }}
                        className="mt-8 flex flex-col gap-3 sm:flex-row"
                    >
                        <Link
                            href={currentSlide.link || '/apply'}
                            className="btn-accent px-7 py-3.5 text-base shadow-xl"
                        >
                            {currentSlide.isDefault
                                ? 'Apply for Admission 2026'
                                : currentSlide.cta_label || 'Learn More'}
                            <ArrowRight className="h-4 w-4" />
                        </Link>
                        {currentSlide.isDefault && (
                            <a
                                href="#programs"
                                className="btn border-2 border-white/80 bg-transparent px-7 py-3.5 text-base text-white backdrop-blur hover:bg-white/10"
                            >
                                Learn More
                            </a>
                        )}
                    </motion.div>
                )}

                {/* ---- Pagination dots ---- */}
                {shouldRotate && (
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
                )}
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
 * School identity + introduction video section
 * Positioned between Vision/Mission/Motto and Our Programs.
 *
 * Desktop: logo (left) + video player (right) in two columns.
 * Mobile: logo first, then video underneath in a single column.
 * ======================================================================== */

function IdentitySection({ intro_video }) {
    const hasVideo = !!intro_video;

    return (
        <section id="school-intro" className="scroll-mt-24 py-16 lg:py-20 bg-canvas">
            <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <SectionHeading
                    eyebrow="Our Story"
                    title="Get to Know God-Win"
                    description="Watch our school introduction and see the learning environment we provide for every child."
                />

                <div className="mt-12 lg:mt-16 grid gap-8 lg:gap-12 lg:grid-cols-2 items-start">
                    {/* Left column — school logo */}
                    <div className="flex flex-col items-center justify-center text-center lg:text-left">
                        <div className="w-full max-w-[480px] rounded-2xl border border-slate-200 bg-white shadow-sm p-3 flex items-center justify-center">
                            <Logo size="xl" wordmark={false} imgClassName="w-full h-auto max-w-full! max-h-[270px]! object-contain" />
                        </div>
                    </div>

                    {/* Right column — introduction video */}
                    <div className="flex flex-col items-center justify-center lg:items-start lg:justify-start w-full">
                        <div className="w-full aspect-video lg:aspect-video max-w-lg lg:max-w-none">
                            {hasVideo ? (
                                <video
                                    src={intro_video.url}
                                    controls
                                    preload="metadata"
                                    className="w-full h-full object-cover rounded-xl border border-slate-200 shadow-sm"
                                    poster=""
                                />
                            ) : (
                                <div className="w-full h-full flex flex-col items-center justify-center rounded-xl border-2 border-dashed border-slate-200 bg-slate-50/50">
                                    <div className="grid h-16 w-16 place-items-center rounded-full bg-slate-200 text-slate-400 mb-4">
                                        <Play className="h-8 w-8" />
                                    </div>
                                    <h3 className="font-semibold text-ink">No introduction video yet</h3>
                                    <p className="mt-1 text-sm text-slate-500 text-center">
                                        The school introduction video will appear here once
                                        uploaded by the administrator.
                                    </p>
                                </div>
                            )}
                        </div>
                        <p className="mt-3 text-xs text-slate-500 text-center lg:text-left">
                            {hasVideo
                                ? intro_video.original_name
                                : 'HTML5 video player — play, pause, volume, seek, fullscreen'}
                        </p>
                    </div>
                </div>
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

export default function Home({ banners, news, events, intro_video }) {
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
            <IdentitySection intro_video={intro_video} />
            <ProgramsSection />
            <NewsSection news={news} events={events} />
            <ContactSection school={school} />
        </PublicLayout>
    );
}
