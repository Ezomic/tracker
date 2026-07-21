<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Moon, SunMedium } from '@lucide/vue';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import PriorityBars from '@/components/PriorityBars.vue';
import { Button } from '@/components/ui/button';
import { useAppearance } from '@/composables/useAppearance';
import { dashboard, login } from '@/routes';
import type { Issue } from '@/types';

const { t } = useI18n();
const { resolvedAppearance, updateAppearance } = useAppearance();

const isDark = computed(() => resolvedAppearance.value === 'dark');

function toggleAppearance() {
    updateAppearance(isDark.value ? 'light' : 'dark');
}

type Column = {
    status: Issue['status'];
    dot: string;
    count: number;
    tickets: {
        id: string;
        title: string;
        priority: Issue['priority'];
        type?: Issue['type'];
        label?: { name: string; class: string };
        hot?: boolean;
        assignee?: string;
    }[];
};

const columns: Column[] = [
    {
        status: 'backlog',
        dot: 'bg-muted-foreground/50',
        count: 2,
        tickets: [
            {
                id: 'SHOP-45',
                title: 'Address autocomplete at checkout',
                priority: 'medium',
            },
            {
                id: 'SHOP-44',
                title: 'Gift card redemption',
                priority: 'low',
            },
        ],
    },
    {
        status: 'in_progress',
        dot: 'bg-primary',
        count: 2,
        tickets: [
            {
                id: 'SHOP-42',
                title: 'Stripe webhook retries silently fail',
                priority: 'high',
                type: 'fix',
                label: {
                    name: 'backend',
                    class: 'bg-primary/10 text-primary',
                },
                hot: true,
                assignee: 'RT',
            },
            {
                id: 'SHOP-39',
                title: 'Cart total ignores coupons',
                priority: 'medium',
            },
        ],
    },
    {
        status: 'in_review',
        dot: 'bg-sky-500',
        count: 1,
        tickets: [
            {
                id: 'SHOP-37',
                title: 'Saved payment methods',
                priority: 'medium',
                type: 'feature',
                label: {
                    name: 'frontend',
                    class: 'bg-sky-500/15 text-sky-600 dark:text-sky-400',
                },
            },
        ],
    },
    {
        status: 'done',
        dot: 'bg-emerald-500',
        count: 1,
        tickets: [
            {
                id: 'SHOP-31',
                title: 'Checkout empty-cart state',
                priority: 'low',
            },
        ],
    },
];

const features = computed(() => [
    { title: t('landing.f1Title'), body: t('landing.f1Body') },
    { title: t('landing.f2Title'), body: t('landing.f2Body') },
    { title: t('landing.f3Title'), body: t('landing.f3Body'), kbd: '⌘K' },
    { title: t('landing.f4Title'), body: t('landing.f4Body') },
    { title: t('landing.f5Title'), body: t('landing.f5Body') },
    { title: t('landing.f6Title'), body: t('landing.f6Body') },
]);
</script>

<template>
    <Head :title="$t('landing.headTitle')" />

    <div class="min-h-screen bg-background text-foreground">
        <header
            class="sticky top-0 z-20 border-b border-border bg-background/80 backdrop-blur"
        >
            <div
                class="mx-auto flex h-15 max-w-5xl items-center justify-between px-6 py-3"
            >
                <div class="flex items-center gap-2.5">
                    <div
                        class="flex size-7 items-center justify-center rounded-lg bg-primary"
                    >
                        <AppLogoIcon class="size-4 text-white" />
                    </div>
                    <span
                        class="font-mono text-[15px] font-semibold tracking-tight"
                    >
                        tracker
                    </span>
                </div>
                <nav class="flex items-center gap-2">
                    <Button
                        variant="ghost"
                        size="icon-sm"
                        :aria-label="
                            isDark
                                ? $t('landing.switchLight')
                                : $t('landing.switchDark')
                        "
                        @click="toggleAppearance"
                    >
                        <Moon v-if="isDark" class="size-4" />
                        <SunMedium v-else class="size-4" />
                    </Button>
                    <Button
                        v-if="$page.props.auth.user"
                        :as-child="true"
                        size="sm"
                    >
                        <Link :href="dashboard()">{{
                            $t('nav.dashboard')
                        }}</Link>
                    </Button>
                    <Button v-else :as-child="true" size="sm">
                        <Link :href="login()">{{ $t('auth.logIn') }}</Link>
                    </Button>
                </nav>
            </div>
        </header>

        <main class="mx-auto max-w-5xl px-6">
            <section
                class="grid items-center gap-12 py-16 sm:py-20 lg:grid-cols-2 lg:gap-12"
            >
                <div>
                    <p
                        class="font-mono text-xs tracking-[0.16em] text-primary uppercase"
                    >
                        {{ $t('landing.eyebrow') }}
                    </p>
                    <h1
                        class="mt-5 text-5xl font-semibold tracking-tighter text-balance sm:text-6xl"
                    >
                        {{ $t('landing.heroLine1') }}<br />
                        {{ $t('landing.heroLine2Pre')
                        }}<span class="text-primary">{{
                            $t('landing.heroLine2Accent')
                        }}</span>
                    </h1>
                    <p
                        class="mt-5 max-w-md text-base text-pretty text-muted-foreground"
                    >
                        {{ $t('landing.heroBody') }}
                    </p>
                    <div class="mt-8 flex flex-wrap gap-3">
                        <Button :as-child="true" size="lg">
                            <Link :href="login()">{{
                                $t('landing.openTracker')
                            }}</Link>
                        </Button>
                        <Button :as-child="true" variant="outline" size="lg">
                            <a href="#board">{{ $t('landing.seeWorkflow') }}</a>
                        </Button>
                    </div>
                    <p class="mt-5 font-mono text-xs text-muted-foreground/70">
                        {{ $t('landing.underCta') }}
                    </p>
                </div>

                <div
                    class="overflow-hidden rounded-xl border border-border bg-card shadow-xl shadow-black/5 dark:shadow-black/40"
                    role="img"
                    :aria-label="$t('landing.terminalAria')"
                >
                    <div
                        class="flex items-center gap-2 border-b border-border px-3.5 py-3"
                    >
                        <span class="size-2.5 rounded-full bg-primary/70" />
                        <span class="size-2.5 rounded-full bg-amber-400/80" />
                        <span class="size-2.5 rounded-full bg-emerald-500/80" />
                        <span
                            class="ml-2 font-mono text-[11px] text-muted-foreground/70"
                        >
                            ~/projects/shop
                        </span>
                    </div>
                    <div
                        class="flex flex-col gap-[2px] overflow-x-auto p-4.5 font-mono text-[13px] leading-[1.7] whitespace-nowrap"
                    >
                        <div class="flex gap-1.5">
                            <span class="text-primary">$</span>
                            <span>tracker new</span>
                            <span class="text-sky-600 dark:text-sky-400">
                                --project
                            </span>
                            <span class="text-muted-foreground">SHOP</span>
                            <span class="text-muted-foreground/50">\</span>
                        </div>
                        <div class="flex gap-1.5 pl-6">
                            <span class="text-sky-600 dark:text-sky-400">
                                --title
                            </span>
                            <span class="text-muted-foreground">
                                "Stripe webhook retries silently fail"
                            </span>
                            <span class="text-muted-foreground/50">\</span>
                        </div>
                        <div class="flex gap-1.5 pl-6">
                            <span class="text-sky-600 dark:text-sky-400">
                                --priority
                            </span>
                            <span class="text-muted-foreground">high</span>
                            <span class="text-sky-600 dark:text-sky-400">
                                --estimate
                            </span>
                            <span class="text-muted-foreground">"2h"</span>
                        </div>
                        <div class="h-3" />
                        <div class="flex gap-1.5">
                            <span
                                class="text-emerald-600 dark:text-emerald-400"
                            >
                                →
                            </span>
                            <span class="font-semibold">SHOP-42</span>
                            <span class="text-muted-foreground">created</span>
                        </div>
                        <div class="flex gap-1.5 pl-4 text-muted-foreground">
                            <span>branch</span>
                            <span class="font-semibold text-foreground">
                                feature/SHOP-42-stripe-webhook-retries
                            </span>
                        </div>
                        <div class="h-2" />
                        <div class="flex items-center gap-1.5">
                            <span class="text-primary">$</span>
                            <span
                                class="inline-block h-3.5 w-2 animate-pulse bg-primary motion-reduce:animate-none"
                            />
                        </div>
                    </div>
                </div>
            </section>

            <section id="board" class="scroll-mt-20 pb-4">
                <div class="mb-5 flex items-center gap-4">
                    <span
                        class="font-mono text-xs tracking-[0.1em] text-muted-foreground/70 uppercase"
                    >
                        {{ $t('landing.flowLabel') }}
                    </span>
                    <span class="h-px flex-1 bg-border" />
                </div>

                <div
                    class="overflow-hidden rounded-xl border border-border bg-card"
                >
                    <div
                        class="flex items-center gap-2 border-b border-border px-4 py-3"
                    >
                        <span class="size-2.5 rounded-full bg-primary/60" />
                        <span class="size-2.5 rounded-full bg-amber-400/60" />
                        <span class="size-2.5 rounded-full bg-emerald-500/60" />
                        <span
                            class="ml-2 font-mono text-[11px] text-muted-foreground/70"
                        >
                            tracker.thijssensoftware.nl / SHOP
                        </span>
                    </div>
                    <div class="grid lg:grid-cols-4">
                        <div
                            v-for="column in columns"
                            :key="column.status"
                            class="border-b border-border p-3.5 last:border-b-0 lg:border-r lg:border-b-0 lg:last:border-r-0"
                        >
                            <div
                                class="mb-3 flex items-center gap-2 text-xs text-muted-foreground"
                            >
                                <span
                                    class="size-2 rounded-full"
                                    :class="column.dot"
                                />
                                {{ $t(`status.${column.status}`) }}
                                <span
                                    class="ml-auto font-mono text-[11px] text-muted-foreground/60"
                                >
                                    {{ column.count }}
                                </span>
                            </div>
                            <div
                                v-for="ticket in column.tickets"
                                :key="ticket.id"
                                class="mb-2.5 rounded-lg border bg-background p-3 last:mb-0"
                                :class="
                                    ticket.hot
                                        ? 'border-primary shadow-[0_0_0_1px_var(--color-primary)]'
                                        : 'border-border'
                                "
                            >
                                <div class="flex items-center gap-2">
                                    <PriorityBars :priority="ticket.priority" />
                                    <span
                                        class="font-mono text-[11px] text-muted-foreground/70"
                                    >
                                        {{ ticket.id }}
                                    </span>
                                    <span
                                        v-if="ticket.type"
                                        class="ml-auto rounded-full border border-border px-2 py-px text-[10px] text-muted-foreground"
                                    >
                                        {{ $t(`issueType.${ticket.type}`) }}
                                    </span>
                                </div>
                                <p class="mt-2 text-[13px] leading-snug">
                                    {{ ticket.title }}
                                </p>
                                <div
                                    v-if="ticket.label || ticket.assignee"
                                    class="mt-2.5 flex items-center gap-2"
                                >
                                    <span
                                        v-if="ticket.label"
                                        class="rounded-full px-2 py-px text-[10px] font-medium"
                                        :class="ticket.label.class"
                                    >
                                        {{ ticket.label.name }}
                                    </span>
                                    <span
                                        v-if="ticket.assignee"
                                        class="ml-auto flex size-4.5 items-center justify-center rounded-full bg-primary text-[9px] font-semibold text-white"
                                    >
                                        {{ ticket.assignee }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="py-16">
                <div class="overflow-hidden rounded-xl border border-border">
                    <div
                        v-for="(feature, index) in features"
                        :key="feature.title"
                        class="grid items-baseline gap-4 border-b border-border px-5 py-5 last:border-b-0 sm:grid-cols-[3rem_13rem_1fr] sm:px-6"
                    >
                        <span class="font-mono text-xs text-primary">
                            {{ String(index + 1).padStart(2, '0') }}
                        </span>
                        <h3 class="text-[15px] font-semibold tracking-tight">
                            {{ feature.title }}
                            <kbd
                                v-if="feature.kbd"
                                class="ml-1.5 rounded border border-border px-1.5 py-0.5 font-mono text-[11px] text-muted-foreground"
                            >
                                {{ feature.kbd }}
                            </kbd>
                        </h3>
                        <p class="text-sm text-muted-foreground">
                            {{ feature.body }}
                        </p>
                    </div>
                </div>
            </section>

            <section class="pb-20">
                <div
                    class="rounded-2xl border border-border bg-card px-6 py-14 text-center"
                >
                    <h2
                        class="text-3xl font-semibold tracking-tight text-balance sm:text-4xl"
                    >
                        {{ $t('landing.bandLine1') }}<br />
                        {{ $t('landing.bandLine2') }}
                    </h2>
                    <p
                        class="mx-auto mt-4 max-w-md text-[15px] text-muted-foreground"
                    >
                        {{ $t('landing.bandBody') }}
                    </p>
                    <Button :as-child="true" size="lg" class="mt-7">
                        <Link :href="login()">{{
                            $t('landing.openTracker')
                        }}</Link>
                    </Button>
                </div>
            </section>
        </main>

        <footer class="border-t border-border">
            <div
                class="mx-auto flex max-w-5xl flex-col items-center gap-3 px-6 py-6 text-center font-mono text-xs text-muted-foreground/70 sm:flex-row sm:justify-between sm:gap-4 sm:text-left"
            >
                <span>© {{ new Date().getFullYear() }} Thijssen Software</span>
                <span class="order-last sm:order-none">
                    {{ $t('landing.footerSuite') }}
                    <a
                        href="https://id.thijssensoftware.nl"
                        class="text-foreground underline-offset-4 transition-colors hover:text-primary hover:underline"
                    >
                        {{ $t('landing.footerSuiteLink') }}
                    </a>
                </span>
                <span>tracker · self-hosted</span>
            </div>
        </footer>
    </div>
</template>
