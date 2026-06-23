<script setup lang="ts">
/**
 * Youth Ministry — a bespoke public page for the Church of Pentecost SaaS platform.
 *
 * Stays inside the platform shell: same navbar, footer, layout, fonts (DM Serif
 * Display + Inter) and Church of Pentecost palette. Energy comes from clean
 * typography, generous spacing, and restrained motion (Stripe / Apple / Linear) —
 * not from flashy effects or off-brand colour.
 *
 * Palette (locked): Dark Blue #1E5AA8 (brand-600) = primary + headings,
 * Light Blue #5AA9E6 (brand-400) = accents, Red #D62828 = minimal (live cue),
 * Gold #F2C94C = rare (testimonial stars), White = main background.
 */
import { ref, computed, onMounted, onBeforeUnmount, reactive } from 'vue'
import { Link } from '@inertiajs/vue3'
import PublicLayout from '@/Layouts/PublicLayout.vue'
import EventCard from '@/Components/Cards/EventCard.vue'
import { useChurch } from '@/composables/useChurch'
import {
    ArrowRight, ChevronDown, Flame, BookOpen, Heart, Globe,
    Sparkles, Users, Star, Quote, CalendarDays, MapPin,
} from 'lucide-vue-next'

type PublicEvent = {
    id: number
    title: string
    date?: string
    time?: string
    location?: string | null
    category?: string | null
    image?: string | null
    start_at?: string | null
}

const props = defineProps<{
    leader?: string | null
    youthName?: string | null
    events?: PublicEvent[]
    nextGathering?: string | null
}>()

const { church } = useChurch()

const leaderName = computed(() => props.leader || 'our youth pastor')
const leaderInitials = computed(() =>
    (props.leader || 'Youth Ministry')
        .split(/\s+/).slice(0, 2).map(w => w[0]?.toUpperCase() ?? '').join('')
)

/* ── Hero headline — gentle word fade (no flashy motion) ────────────────────── */
// Short, single-word options only — different lengths must never wrap to a second
// line on mobile (that would make the hero height jump each cycle).
const rotatingWords = ['alive', 'ablaze', 'free', 'sent']
const wordIndex = ref(0)
let wordTimer: number | undefined

/* ── Events — real upcoming feed, with a curated fallback so it's never empty ── */
const fallbackEvents: PublicEvent[] = [
    { id: -1, title: 'Friday Night Worship', date: 'This Friday', time: '6:00 PM', location: 'Main Auditorium', category: 'Worship', image: null },
    { id: -2, title: 'Youth Conference 2026', date: 'Next month', time: 'All day', location: 'Central Temple', category: 'Conference', image: null },
    { id: -3, title: 'City Outreach Saturday', date: 'Last Sat / month', time: '9:00 AM', location: 'City Centre', category: 'Outreach', image: null },
]
const usingFallback = computed(() => !props.events || props.events.length === 0)
const eventList = computed<PublicEvent[]>(() => (usingFallback.value ? fallbackEvents : props.events!))

/* ── Live countdown ─ soonest real event, else next Friday 6 PM ─────────────── */
function nextFridayEvening(): Date {
    const d = new Date()
    d.setHours(18, 0, 0, 0)
    const day = d.getDay()
    let delta = (5 - day + 7) % 7
    if (delta === 0 && Date.now() > d.getTime()) delta = 7
    d.setDate(d.getDate() + delta)
    return d
}
// The soonest upcoming event that has a real date — drives BOTH the countdown
// clock and its label so they always refer to the same event.
const nextEvent = computed(() => (props.events ?? []).find(e => e.start_at) ?? null)
const target = computed(() => {
    if (props.nextGathering) return new Date(props.nextGathering)
    if (nextEvent.value?.start_at) return new Date(nextEvent.value.start_at)
    return nextFridayEvening()
})
const countdownTitle = computed(() => nextEvent.value?.title ?? 'Friday Night Worship')
const nowTs = ref(Date.now())
let clockTimer: number | undefined
const countdown = computed(() => {
    const diff = Math.max(0, target.value.getTime() - nowTs.value)
    const s = Math.floor(diff / 1000)
    return {
        days:    Math.floor(s / 86400),
        hours:   Math.floor((s % 86400) / 3600),
        minutes: Math.floor((s % 3600) / 60),
        seconds: s % 60,
    }
})
const pad = (n: number) => String(n).padStart(2, '0')

/* ── About / mission pillars ───────────────────────────────────────────────── */
const pillars = [
    { icon: Flame,    title: 'Worship',      body: 'Honest, wholehearted worship that lifts His name above everything.' },
    { icon: BookOpen, title: 'Discipleship', body: 'Real Scripture and real conversation that shape who you become.' },
    { icon: Heart,    title: 'Belonging',    body: 'A family where you are known, missed when away, and never alone.' },
    { icon: Globe,    title: 'Mission',      body: 'Faith with feet — serving our city and carrying hope to the nations.' },
]

/* ── Animated statistics — count up when scrolled into view ─────────────────── */
const stats = reactive([
    { label: 'Youth members', target: 250, suffix: '+', display: 0 },
    { label: 'Events a year',  target: 40,  suffix: '+', display: 0 },
    { label: 'Youth leaders',  target: 18,  suffix: '',  display: 0 },
    { label: 'Active programs', target: 6,  suffix: '',  display: 0 },
])
const statsEl = ref<HTMLElement | null>(null)
let statsStarted = false
function runCounters() {
    const duration = 1500
    const start = performance.now()
    const ease = (t: number) => 1 - Math.pow(1 - t, 3)
    const tick = (n: number) => {
        const p = Math.min(1, (n - start) / duration)
        const e = ease(p)
        stats.forEach(s => { s.display = Math.round(s.target * e) })
        if (p < 1) requestAnimationFrame(tick)
    }
    requestAnimationFrame(tick)
}

/* ── Gallery — filterable grid with hover zoom ─────────────────────────────── */
type Shot = { id: number; cat: string; label: string; icon: any; tall?: boolean }
const categories = ['All', 'Worship', 'Outreach', 'Camp', 'Hangouts']
const activeCat = ref('All')
const gallery: Shot[] = [
    { id: 1, cat: 'Worship',  label: 'Friday Night Worship',  icon: Flame, tall: true },
    { id: 2, cat: 'Hangouts', label: 'After-Service Hangs',   icon: Users },
    { id: 3, cat: 'Outreach', label: 'City Outreach',         icon: Heart },
    { id: 4, cat: 'Camp',     label: 'Summer Camp',           icon: Sparkles, tall: true },
    { id: 5, cat: 'Worship',  label: 'Band Rehearsal',        icon: Sparkles },
    { id: 6, cat: 'Hangouts', label: 'Game Night',            icon: Star },
    { id: 7, cat: 'Outreach', label: 'Community Serve Day',   icon: Globe },
    { id: 8, cat: 'Camp',     label: 'Bonfire & Testimonies', icon: Flame },
]
const filteredGallery = computed(() =>
    activeCat.value === 'All' ? gallery : gallery.filter(g => g.cat === activeCat.value)
)
const tileGradient = (id: number) => {
    // Brand-only blues — keeps the gallery on-palette and demo-proof (no broken images).
    const angles = [135, 200, 115, 160, 95, 220, 145, 180]
    const a = angles[id % angles.length]
    return `linear-gradient(${a}deg, #0c2648 0%, #1e5aa8 55%, #5aa9e6 120%)`
}

/* ── Testimonies ───────────────────────────────────────────────────────────── */
const testimonies = [
    { name: 'Ama Boateng',   role: 'Age 19 · Worship team',   quote: 'I came once for a friend and never left. This is where I learned that faith could actually be the most exciting part of my life.' },
    { name: 'Kwadwo Mensah', role: 'Age 22 · Media team',     quote: 'The leaders genuinely know me. I walked in shy and unsure — now I serve every week and lead my own small group.' },
    { name: 'Efua Asante',   role: 'Age 17 · First-timer',    quote: 'Honestly? I expected boring. Instead I found real friends, real worship, and a God who actually meets me here.' },
]
const initials = (name: string) => name.split(/\s+/).slice(0, 2).map(w => w[0]?.toUpperCase()).join('')

onMounted(() => {
    wordTimer = window.setInterval(() => {
        wordIndex.value = (wordIndex.value + 1) % rotatingWords.length
    }, 2600)
    clockTimer = window.setInterval(() => { nowTs.value = Date.now() }, 1000)

    if (statsEl.value && 'IntersectionObserver' in window) {
        const io = new IntersectionObserver((entries) => {
            entries.forEach((e) => {
                if (e.isIntersecting && !statsStarted) {
                    statsStarted = true
                    runCounters()
                    io.disconnect()
                }
            })
        }, { threshold: 0.35 })
        io.observe(statsEl.value)
    } else {
        stats.forEach(s => { s.display = s.target })
    }
})
onBeforeUnmount(() => {
    if (wordTimer) clearInterval(wordTimer)
    if (clockTimer) clearInterval(clockTimer)
})
</script>

<template>
    <PublicLayout
        title="Youth Ministry"
        :description="`The Youth Ministry of ${church.name} — a movement of young people fully alive in God.`"
    >
        <!-- ══════════════════ 1 · HERO ══════════════════ -->
        <section class="youth-hero relative overflow-hidden">
            <div class="orb orb--1" aria-hidden="true"></div>
            <div class="orb orb--2" aria-hidden="true"></div>
            <div class="hero-grid" aria-hidden="true"></div>

            <div class="relative z-10 mx-auto max-w-7xl px-6 lg:px-8 min-h-[calc(100vh-4rem)] flex flex-col justify-center py-20">
                <div class="flex items-center gap-3 mb-7 reveal">
                    <span class="h-px w-10 bg-brand-400 shrink-0"></span>
                    <span class="text-xs font-semibold tracking-[0.3em] uppercase text-brand-300">
                        Youth Ministry · {{ church.name }}
                    </span>
                </div>

                <h1 class="font-serif font-normal text-white leading-[0.95] tracking-tight reveal reveal-delay-1
                           text-[2.85rem] sm:text-6xl lg:text-[5.75rem] max-w-4xl">
                    A generation fully
                    <span class="block">
                        <Transition name="word" mode="out-in">
                            <span :key="wordIndex" class="gradient-text-light italic inline-block">{{ rotatingWords[wordIndex] }}.</span>
                        </Transition>
                    </span>
                </h1>

                <p class="mt-7 text-lg sm:text-xl text-white/70 leading-relaxed max-w-2xl reveal reveal-delay-2">
                    Real faith, real friendships, real purpose. We're young people chasing
                    God with everything we have — and there's a place in it that's been
                    waiting for you.
                </p>

                <div class="mt-9 flex flex-col sm:flex-row sm:items-center gap-3.5 reveal reveal-delay-3">
                    <Link href="/contact"
                          class="group inline-flex items-center justify-center gap-2 rounded-full bg-white text-brand-700 font-semibold px-7 py-3.5 text-base
                                 hover:bg-brand-50 transition-all duration-200 hover:-translate-y-0.5 shadow-sm">
                        Join Youth Ministry
                        <ArrowRight class="w-4 h-4 group-hover:translate-x-0.5 transition-transform" />
                    </Link>
                    <a href="#events"
                       class="inline-flex items-center justify-center gap-2 rounded-full border border-white/25 text-white font-semibold px-7 py-3.5 text-base
                              hover:bg-white/10 hover:border-white/40 transition-all duration-200">
                        See upcoming events
                    </a>
                </div>

                <div class="mt-12 flex flex-wrap items-center gap-x-7 gap-y-3 reveal reveal-delay-4">
                    <div v-for="(f, i) in ['Fridays · 6:00 PM', 'Ages 13 – 30', 'Everyone welcome']" :key="i"
                         class="flex items-center gap-2.5">
                        <span class="w-1.5 h-1.5 rounded-full bg-brand-400 shrink-0"></span>
                        <span class="text-sm font-medium text-white/80">{{ f }}</span>
                    </div>
                </div>
            </div>

            <a href="#about" class="hero-cue absolute bottom-7 left-1/2 -translate-x-1/2 z-10 flex flex-col items-center gap-1.5 text-white/40 hover:text-white/70 transition-colors">
                <span class="text-[10px] font-semibold tracking-[0.3em] uppercase">Scroll</span>
                <ChevronDown class="w-4 h-4" />
            </a>
        </section>

        <!-- ══════════════════ 2 · ABOUT / MISSION ══════════════════ -->
        <section id="about" class="bg-white">
            <div class="mx-auto max-w-7xl px-6 lg:px-8 py-20 md:py-28">
                <div class="grid lg:grid-cols-12 gap-12 lg:gap-16 items-start">
                    <div class="lg:col-span-5 reveal">
                        <div class="flex items-center gap-3 mb-6">
                            <span class="h-px w-8 bg-brand-300 shrink-0"></span>
                            <span class="text-xs font-semibold tracking-[0.25em] uppercase text-brand-500">Our mission</span>
                        </div>
                        <h2 class="font-serif text-4xl sm:text-5xl text-neutral-900 leading-[1.05]">
                            Raising a generation that
                            <span class="gradient-text italic">lives for more.</span>
                        </h2>
                        <p class="mt-7 text-lg text-neutral-500 leading-relaxed max-w-md">
                            We exist to help every young person encounter God, grow in real
                            community, discover their gifts, and carry hope into their schools,
                            campuses, and city. This is more than a Friday meeting — it's a family
                            and a movement.
                        </p>
                        <div class="mt-8">
                            <Link href="/about"
                                  class="inline-flex items-center gap-2 text-brand-600 font-semibold hover:text-brand-700 transition-colors">
                                More about {{ church.name }}
                                <ArrowRight class="w-4 h-4" />
                            </Link>
                        </div>
                    </div>

                    <div class="lg:col-span-7 grid sm:grid-cols-2 gap-4">
                        <div v-for="(p, i) in pillars" :key="p.title"
                             class="group bg-white border border-neutral-100 rounded-2xl p-7 hover:shadow-elevated hover:-translate-y-1 transition-all duration-300 reveal"
                             :class="`reveal-delay-${Math.min(i + 1, 4)}`">
                            <div class="w-12 h-12 rounded-xl bg-brand-50 border border-brand-100 flex items-center justify-center mb-5 group-hover:bg-brand-600 transition-colors duration-300">
                                <component :is="p.icon" class="w-5 h-5 text-brand-600 group-hover:text-white transition-colors duration-300" />
                            </div>
                            <h3 class="font-semibold text-lg text-neutral-900 mb-2 group-hover:text-brand-600 transition-colors">{{ p.title }}</h3>
                            <p class="text-sm text-neutral-500 leading-relaxed">{{ p.body }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ══════════════════ 3 · ANIMATED STATS ══════════════════ -->
        <section ref="statsEl" class="bg-neutral-50 border-y border-neutral-100">
            <div class="mx-auto max-w-7xl px-6 lg:px-8 py-16 md:py-20">
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-6 lg:gap-4">
                    <div v-for="(s, i) in stats" :key="i"
                         class="text-center reveal" :class="`reveal-delay-${Math.min(i + 1, 4)}`">
                        <div class="font-serif text-5xl sm:text-6xl text-brand-600 leading-none tabular-nums">
                            {{ s.display }}<span class="text-brand-400">{{ s.suffix }}</span>
                        </div>
                        <div class="mt-3 text-sm text-neutral-500 font-medium">{{ s.label }}</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ══════════════════ 4 · EVENTS (+ countdown) ══════════════════ -->
        <section id="events" class="bg-white">
            <div class="mx-auto max-w-7xl px-6 lg:px-8 py-20 md:py-28">
                <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-6 mb-10 reveal">
                    <div>
                        <div class="flex items-center gap-3 mb-5">
                            <span class="h-px w-8 bg-brand-300 shrink-0"></span>
                            <span class="text-xs font-semibold tracking-[0.25em] uppercase text-brand-500">What's on</span>
                        </div>
                        <h2 class="font-serif text-4xl sm:text-5xl text-neutral-900 leading-tight">Come and be part of it.</h2>
                    </div>
                    <Link href="/events" class="inline-flex items-center gap-2 text-brand-600 font-semibold hover:text-brand-700 transition-colors shrink-0">
                        View all events <ArrowRight class="w-4 h-4" />
                    </Link>
                </div>

                <!-- Compact countdown to the next gathering -->
                <div class="mb-10 rounded-2xl bg-brand-600 overflow-hidden reveal">
                    <div class="px-6 sm:px-8 py-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-5">
                        <div>
                            <div class="inline-flex items-center gap-2 mb-2">
                                <span class="relative flex h-2 w-2">
                                    <span class="absolute inline-flex h-full w-full rounded-full opacity-75 animate-ping" style="background:#D62828"></span>
                                    <span class="relative inline-flex rounded-full h-2 w-2" style="background:#D62828"></span>
                                </span>
                                <span class="text-[11px] font-semibold tracking-[0.18em] uppercase text-white/70">Counting down to</span>
                            </div>
                            <p class="font-serif text-2xl text-white leading-tight">{{ countdownTitle }}</p>
                        </div>
                        <div class="flex items-center gap-2.5 sm:gap-3">
                            <div v-for="unit in [
                                    { v: countdown.days, l: 'Days' },
                                    { v: countdown.hours, l: 'Hrs' },
                                    { v: countdown.minutes, l: 'Min' },
                                    { v: countdown.seconds, l: 'Sec' },
                                ]" :key="unit.l"
                                 class="w-14 sm:w-16 rounded-xl bg-white/10 border border-white/15 px-2 py-2.5 text-center">
                                <div class="font-serif text-2xl sm:text-3xl text-white tabular-nums leading-none">{{ pad(unit.v) }}</div>
                                <div class="mt-1 text-[9px] font-semibold tracking-[0.15em] uppercase text-white/55">{{ unit.l }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Event cards (reuses the shared EventCard component) -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                    <div v-for="(ev, i) in eventList" :key="ev.id"
                         class="reveal" :class="`reveal-delay-${Math.min(i + 1, 4)}`">
                        <EventCard :event="(ev as any)" :href="usingFallback ? '/events' : undefined" />
                    </div>
                </div>
            </div>
        </section>

        <!-- ══════════════════ 5 · MEDIA / GALLERY ══════════════════ -->
        <section class="bg-neutral-50 border-y border-neutral-100">
            <div class="mx-auto max-w-7xl px-6 lg:px-8 py-20 md:py-28">
                <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-6 mb-10 reveal">
                    <div>
                        <div class="flex items-center gap-3 mb-5">
                            <span class="h-px w-8 bg-brand-300 shrink-0"></span>
                            <span class="text-xs font-semibold tracking-[0.25em] uppercase text-brand-500">In the moment</span>
                        </div>
                        <h2 class="font-serif text-4xl sm:text-5xl text-neutral-900 leading-tight">Life, lately.</h2>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button v-for="cat in categories" :key="cat" @click="activeCat = cat"
                                class="px-4 py-2 rounded-full text-sm font-medium transition-all duration-200"
                                :class="activeCat === cat
                                    ? 'bg-brand-600 text-white shadow-sm'
                                    : 'bg-white text-neutral-500 border border-neutral-200 hover:border-brand-300 hover:text-brand-600'">
                            {{ cat }}
                        </button>
                    </div>
                </div>

                <TransitionGroup name="gallery" tag="div"
                                 class="grid grid-cols-2 lg:grid-cols-4 auto-rows-[9.5rem] gap-4">
                    <div v-for="shot in filteredGallery" :key="shot.id"
                         class="group relative rounded-2xl overflow-hidden shadow-card"
                         :class="shot.tall ? 'row-span-2' : 'row-span-1'"
                         :style="{ backgroundImage: tileGradient(shot.id) }">
                        <div class="absolute inset-0 group-hover:scale-105 transition-transform duration-500"
                             :style="{ backgroundImage: tileGradient(shot.id) }" aria-hidden="true"></div>
                        <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent" aria-hidden="true"></div>
                        <component :is="shot.icon" class="absolute top-4 left-4 w-6 h-6 text-white/45 group-hover:text-white transition-colors duration-300" />
                        <div class="absolute bottom-0 inset-x-0 p-4">
                            <span class="text-[10px] font-semibold tracking-[0.16em] uppercase text-brand-200">{{ shot.cat }}</span>
                            <p class="text-sm font-semibold text-white leading-snug">{{ shot.label }}</p>
                        </div>
                    </div>
                </TransitionGroup>
            </div>
        </section>

        <!-- ══════════════════ 6 · TESTIMONIES ══════════════════ -->
        <section class="bg-white">
            <div class="mx-auto max-w-7xl px-6 lg:px-8 py-20 md:py-28">
                <div class="max-w-2xl mb-12 reveal">
                    <div class="flex items-center gap-3 mb-5">
                        <span class="h-px w-8 bg-brand-300 shrink-0"></span>
                        <span class="text-xs font-semibold tracking-[0.25em] uppercase text-brand-500">In their words</span>
                    </div>
                    <h2 class="font-serif text-4xl sm:text-5xl text-neutral-900 leading-tight">Stories from our young people.</h2>
                </div>

                <div class="grid md:grid-cols-3 gap-5">
                    <figure v-for="(t, i) in testimonies" :key="t.name"
                            class="flex flex-col bg-white border border-neutral-100 rounded-2xl p-7 hover:shadow-elevated hover:-translate-y-1 transition-all duration-300 reveal"
                            :class="`reveal-delay-${Math.min(i + 1, 4)}`">
                        <!-- Gold stars — a rare, intentional highlight -->
                        <div class="flex items-center gap-0.5 mb-4">
                            <Star v-for="n in 5" :key="n" class="w-4 h-4" style="color:#F2C94C;fill:#F2C94C" />
                        </div>
                        <blockquote class="text-neutral-600 leading-relaxed flex-1">“{{ t.quote }}”</blockquote>
                        <figcaption class="flex items-center gap-3 mt-6 pt-6 border-t border-neutral-100">
                            <div class="w-11 h-11 rounded-full gradient-brand flex items-center justify-center text-white text-sm font-semibold shrink-0">
                                {{ initials(t.name) }}
                            </div>
                            <div>
                                <p class="font-semibold text-neutral-900 text-sm">{{ t.name }}</p>
                                <p class="text-xs text-neutral-400">{{ t.role }}</p>
                            </div>
                        </figcaption>
                    </figure>
                </div>

                <!-- Leader feature quote -->
                <div class="mt-12 rounded-3xl bg-brand-50 border border-brand-100 p-8 sm:p-12 reveal">
                    <Quote class="w-9 h-9 text-brand-300 mb-5" />
                    <blockquote class="font-serif text-2xl sm:text-3xl text-neutral-900 leading-snug max-w-3xl">
                        This generation isn't the church of tomorrow. They're
                        <span class="gradient-text italic">the church right now</span> — and they're just getting started.
                    </blockquote>
                    <div class="flex items-center gap-3 mt-7">
                        <div class="w-11 h-11 rounded-full gradient-brand flex items-center justify-center text-white text-sm font-semibold shrink-0">
                            {{ leaderInitials }}
                        </div>
                        <div>
                            <p class="font-semibold text-neutral-900 text-sm capitalize">{{ leaderName }}</p>
                            <p class="text-xs text-neutral-400">Youth Ministry · {{ church.name }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ══════════════════ 7 · CALL TO ACTION ══════════════════ -->
        <section class="bg-white">
            <div class="mx-auto max-w-7xl px-6 lg:px-8 pb-24 md:pb-32">
                <div class="relative gradient-brand rounded-[2rem] overflow-hidden px-8 sm:px-14 py-16 md:py-20 text-center">
                    <div class="cta-grid" aria-hidden="true"></div>
                    <div class="relative z-10 max-w-2xl mx-auto reveal">
                        <Sparkles class="w-8 h-8 text-white/85 mx-auto mb-6" />
                        <h2 class="font-serif text-4xl sm:text-5xl lg:text-6xl text-white leading-[1.05] mb-5">
                            Your place is already here.
                        </h2>
                        <p class="text-white/85 text-lg leading-relaxed mb-9 max-w-lg mx-auto">
                            Come as you are this Friday — bring a friend. Discover what your life
                            looks like when it's fully alive in God.
                        </p>
                        <div class="flex flex-col sm:flex-row items-center justify-center gap-3.5">
                            <Link href="/contact"
                                  class="group inline-flex items-center justify-center gap-2 rounded-full bg-white text-brand-700 font-semibold px-8 py-4 text-base
                                         hover:bg-brand-50 shadow-lg transition-all duration-200 hover:-translate-y-0.5">
                                Join Youth Ministry
                                <ArrowRight class="w-4 h-4 group-hover:translate-x-0.5 transition-transform" />
                            </Link>
                            <Link href="/ministries"
                                  class="inline-flex items-center justify-center gap-2 rounded-full border border-white/40 text-white font-semibold px-8 py-4 text-base
                                         hover:bg-white/10 transition-all duration-200">
                                Explore other ministries
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </PublicLayout>
</template>

<style scoped>
/* ── Hero — near-black with Church-of-Pentecost blue glow (matches Home hero) ── */
.youth-hero {
    background-color: #0a1326;
    background-image:
        radial-gradient(at 18% 22%, rgba(30, 90, 168, 0.34) 0px, transparent 55%),
        radial-gradient(at 82% 80%, rgba(90, 169, 230, 0.16) 0px, transparent 55%);
}

/* Subtle, slow floating glow — not parallax, not distracting */
.orb {
    position: absolute;
    border-radius: 9999px;
    filter: blur(80px);
    opacity: 0.45;
    pointer-events: none;
}
.orb--1 {
    width: 28rem; height: 28rem; top: -7rem; right: -5rem;
    background: radial-gradient(circle, rgba(90, 169, 230, 0.5), transparent 70%);
    animation: drift 22s ease-in-out infinite;
}
.orb--2 {
    width: 24rem; height: 24rem; bottom: -8rem; left: -6rem;
    background: radial-gradient(circle, rgba(30, 90, 168, 0.55), transparent 70%);
    animation: drift 26s ease-in-out infinite reverse;
}
@keyframes drift {
    0%, 100% { transform: translate(0, 0); }
    50%      { transform: translate(1.5rem, -1.25rem); }
}

/* Faint Linear-style grid for depth */
.hero-grid {
    position: absolute; inset: 0; pointer-events: none;
    background-image:
        linear-gradient(rgba(255, 255, 255, 0.03) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255, 255, 255, 0.03) 1px, transparent 1px);
    background-size: 60px 60px;
    mask-image: radial-gradient(ellipse 80% 70% at 50% 40%, #000 30%, transparent 82%);
    -webkit-mask-image: radial-gradient(ellipse 80% 70% at 50% 40%, #000 30%, transparent 82%);
}
.cta-grid {
    position: absolute; inset: 0; pointer-events: none;
    background-image:
        linear-gradient(rgba(255, 255, 255, 0.08) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255, 255, 255, 0.08) 1px, transparent 1px);
    background-size: 40px 40px;
    mask-image: radial-gradient(ellipse 90% 90% at 50% 50%, #000 10%, transparent 75%);
    -webkit-mask-image: radial-gradient(ellipse 90% 90% at 50% 50%, #000 10%, transparent 75%);
}

/* Gentle hero-word fade (no 3D / flip) */
.word-enter-active, .word-leave-active { transition: opacity 0.4s ease, transform 0.4s cubic-bezier(0.16, 1, 0.3, 1); }
.word-enter-from { opacity: 0; transform: translateY(0.35em); }
.word-leave-to   { opacity: 0; transform: translateY(-0.35em); }

/* Soft scroll cue */
.hero-cue { animation: cue 2.4s ease-in-out infinite; }
@keyframes cue {
    0%, 100% { transform: translate(-50%, 0); }
    50%      { transform: translate(-50%, 5px); }
}

/* Gallery filter reflow */
.gallery-move, .gallery-enter-active, .gallery-leave-active { transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1); }
.gallery-enter-from, .gallery-leave-to { opacity: 0; transform: scale(0.92); }
.gallery-leave-active { position: absolute; }

/* Respect reduced-motion */
@media (prefers-reduced-motion: reduce) {
    .orb, .hero-cue, .animate-ping { animation: none !important; }
    .word-enter-active, .word-leave-active { transition: opacity 0.3s ease; }
    .word-enter-from, .word-leave-to { transform: none; }
}
</style>
