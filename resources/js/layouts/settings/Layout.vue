<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { toUrl } from '@/lib/utils';
import { edit as editAppearance } from '@/routes/appearance';
import { index as indexCategories } from '@/routes/categories';
import { index as indexLabels } from '@/routes/labels';
import { index as indexMembers } from '@/routes/members';
import { edit as editProfile } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import { index as indexTemplates } from '@/routes/templates';
import type { NavItem } from '@/types';

interface NavGroup {
    title: string;
    items: NavItem[];
}

const page = usePage();

const navGroups = computed<NavGroup[]>(() =>
    [
        {
            title: 'Account',
            items: [
                { title: 'Profile', href: editProfile() },
                { title: 'Security', href: editSecurity() },
                { title: 'Appearance', href: editAppearance() },
            ],
        },
        {
            title: 'Organization',
            items: [
                ...(page.props.currentOrganization?.canManage
                    ? [{ title: 'Members', href: indexMembers() }]
                    : []),
                // Templates and labels are the org's shared library, hidden
                // from guests.
                ...(page.props.currentOrganization?.canViewLibrary
                    ? [
                          { title: 'Categories', href: indexCategories() },
                          { title: 'Labels', href: indexLabels() },
                          { title: 'Templates', href: indexTemplates() },
                      ]
                    : []),
            ],
        },
    ].filter((group) => group.items.length > 0),
);

const { isCurrentOrParentUrl } = useCurrentUrl();
</script>

<template>
    <div class="px-4 py-6">
        <Heading
            title="Settings"
            description="Manage your account and organization settings"
        />

        <div class="flex flex-col lg:flex-row lg:space-x-12">
            <aside class="w-full max-w-xl lg:w-48">
                <nav class="flex flex-col space-y-4" aria-label="Settings">
                    <div
                        v-for="group in navGroups"
                        :key="group.title"
                        class="flex flex-col space-y-1"
                    >
                        <p
                            class="px-3 py-1 text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            {{ group.title }}
                        </p>
                        <Button
                            v-for="item in group.items"
                            :key="toUrl(item.href)"
                            variant="ghost"
                            :class="[
                                'w-full justify-start',
                                { 'bg-muted': isCurrentOrParentUrl(item.href) },
                            ]"
                            as-child
                        >
                            <Link :href="item.href">
                                <component :is="item.icon" class="h-4 w-4" />
                                {{ item.title }}
                            </Link>
                        </Button>
                    </div>
                </nav>
            </aside>

            <Separator class="my-6 lg:hidden" />

            <div class="flex-1 md:max-w-2xl">
                <section class="max-w-xl space-y-12">
                    <slot />
                </section>
            </div>
        </div>
    </div>
</template>
