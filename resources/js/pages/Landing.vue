<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Command, Folder, Kanban, Moon, Server, SunMedium } from '@lucide/vue';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Button } from '@/components/ui/button';
import { useAppearance } from '@/composables/useAppearance';
import { dashboard, login } from '@/routes';

const { t } = useI18n();
const { resolvedAppearance, updateAppearance } = useAppearance();

const isDark = computed(() => resolvedAppearance.value === 'dark');

function toggleAppearance() {
    updateAppearance(isDark.value ? 'light' : 'dark');
}

const projects = [
    { key: 'SHOP', dot: 'bg-primary' },
    { key: 'BILLR', dot: 'bg-emerald-500' },
    { key: 'THI', dot: 'bg-sky-500' },
];

const tickets = [
    { id: 'SHOP-31', dot: 'bg-amber-400', title: 'Stripe webhook retries' },
    {
        id: 'SHOP-30',
        dot: 'bg-emerald-500',
        title: 'Checkout empty-cart state',
    },
    {
        id: 'SHOP-29',
        dot: 'bg-muted-foreground/50',
        title: 'Backfill legacy invoices',
    },
];

const features = computed(() => [
    {
        icon: Folder,
        title: t('landing.featureProjectsTitle'),
        body: t('landing.featureProjectsBody'),
    },
    {
        icon: Kanban,
        title: t('landing.featureBoardTitle'),
        body: t('landing.featureBoardBody'),
    },
    {
        icon: Command,
        title: t('landing.featureCommandTitle'),
        body: t('landing.featureCommandBody'),
    },
    {
        icon: SunMedium,
        title: t('landing.featureThemeTitle'),
        body: t('landing.featureThemeBody'),
    },
]);
</script>

<template>
    <Head :title="$t('landing.headTitle')" />

    <div class="min-h-screen bg-background text-foreground">
        <header
            class="sticky top-0 z-10 border-b border-border/60 bg-background/80 backdrop-blur"
        >
            <div
                class="mx-auto flex h-16 max-w-5xl items-center justify-between px-6"
            >
                <div class="flex items-center gap-2.5">
                    <div
                        class="flex size-8 items-center justify-center rounded-lg bg-primary"
                    >
                        <AppLogoIcon class="size-5 text-white" />
                    </div>
                    <span class="text-[15px] font-semibold">tracker</span>
                </div>
                <nav class="flex items-center gap-1.5">
                    <a
                        href="#features"
                        class="hidden rounded-md px-3 py-2 text-sm text-muted-foreground transition-colors hover:text-foreground sm:inline-block"
                    >
                        {{ $t('landing.features') }}
                    </a>
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
            <section class="py-20 text-center sm:py-28">
                <span
                    class="inline-flex items-center rounded-full bg-primary/10 px-3 py-1 text-xs font-medium text-primary"
                >
                    {{ $t('landing.badge') }}
                </span>
                <h1
                    class="mx-auto mt-5 max-w-2xl text-4xl font-semibold tracking-tight text-balance sm:text-5xl"
                >
                    {{ $t('landing.heroTitle') }}
                </h1>
                <p
                    class="mx-auto mt-4 max-w-xl text-base text-pretty text-muted-foreground sm:text-lg"
                >
                    {{ $t('landing.heroBody') }}
                </p>
                <div
                    class="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row"
                >
                    <Button :as-child="true" size="lg">
                        <Link :href="login()">{{
                            $t('landing.openTracker')
                        }}</Link>
                    </Button>
                    <Button :as-child="true" variant="outline" size="lg">
                        <a href="#features">{{ $t('landing.seeWorkflow') }}</a>
                    </Button>
                </div>
            </section>

            <section class="pb-8">
                <div
                    class="overflow-hidden rounded-xl border border-border bg-card shadow-sm"
                >
                    <div
                        class="flex items-center gap-2 border-b border-border bg-muted/40 px-4 py-2.5"
                    >
                        <span class="size-2.5 rounded-full bg-primary/60" />
                        <span class="size-2.5 rounded-full bg-amber-400/60" />
                        <span class="size-2.5 rounded-full bg-emerald-500/60" />
                        <span
                            class="ml-2 font-mono text-xs text-muted-foreground"
                        >
                            tracker.thijssensoftware.nl
                        </span>
                    </div>
                    <div class="flex">
                        <aside
                            class="hidden w-40 shrink-0 border-r border-border bg-muted/20 p-3 sm:block"
                        >
                            <p
                                class="px-2 pb-2 text-[10px] font-medium tracking-wide text-muted-foreground"
                            >
                                {{ $t('landing.projectsLabel') }}
                            </p>
                            <div
                                v-for="(project, i) in projects"
                                :key="project.key"
                                class="flex items-center gap-2 rounded-md px-2 py-1.5 text-xs"
                                :class="
                                    i === 0
                                        ? 'bg-primary/10 font-medium text-primary'
                                        : 'text-muted-foreground'
                                "
                            >
                                <span
                                    class="size-1.5 rounded-[2px]"
                                    :class="project.dot"
                                />
                                {{ project.key }}
                            </div>
                        </aside>
                        <div class="min-w-0 flex-1 p-4">
                            <div class="mb-2 flex items-center justify-between">
                                <span class="text-sm font-medium">{{
                                    $t('landing.tickets')
                                }}</span>
                                <kbd
                                    class="rounded border border-border px-1.5 py-0.5 font-mono text-[10px] text-muted-foreground"
                                >
                                    ⌘K
                                </kbd>
                            </div>
                            <div
                                v-for="ticket in tickets"
                                :key="ticket.id"
                                class="flex items-center gap-3 border-t border-border py-2.5 text-xs"
                            >
                                <span
                                    class="w-16 shrink-0 font-mono text-muted-foreground"
                                >
                                    {{ ticket.id }}
                                </span>
                                <span
                                    class="size-2 shrink-0 rounded-full"
                                    :class="ticket.dot"
                                />
                                <span class="truncate">{{ ticket.title }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section id="features" class="scroll-mt-20 py-16">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 lg:gap-5">
                    <div
                        v-for="feature in features"
                        :key="feature.title"
                        class="rounded-xl border border-border bg-card p-5"
                    >
                        <component
                            :is="feature.icon"
                            class="size-5 text-primary"
                        />
                        <h3 class="mt-3 text-sm font-medium">
                            {{ feature.title }}
                        </h3>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{ feature.body }}
                        </p>
                    </div>
                </div>
            </section>

            <section class="pb-20">
                <div
                    class="flex flex-col items-center rounded-xl border border-border bg-muted/30 px-6 py-12 text-center"
                >
                    <Server class="size-6 text-primary" />
                    <h2 class="mt-3 text-xl font-semibold">
                        {{ $t('landing.selfHostedTitle') }}
                    </h2>
                    <p class="mt-2 max-w-md text-sm text-muted-foreground">
                        {{ $t('landing.selfHostedBody') }}
                    </p>
                    <Button :as-child="true" size="lg" class="mt-6">
                        <Link :href="login()">{{
                            $t('landing.openTracker')
                        }}</Link>
                    </Button>
                </div>
            </section>
        </main>

        <footer class="border-t border-border">
            <div
                class="mx-auto flex max-w-5xl items-center justify-between px-6 py-6 text-xs text-muted-foreground"
            >
                <span>© {{ new Date().getFullYear() }} Thijssen Software</span>
                <Link
                    :href="login()"
                    class="transition-colors hover:text-foreground"
                >
                    {{ $t('auth.logIn') }}
                </Link>
            </div>
        </footer>
    </div>
</template>
