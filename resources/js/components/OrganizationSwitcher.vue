<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { Building2, ChevronsUpDown, Check } from '@lucide/vue';
import { computed } from 'vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { SidebarMenuButton } from '@/components/ui/sidebar';
import { switchMethod } from '@/routes/organizations';
import type { OrganizationSummary } from '@/types';

const page = usePage();

const current = computed<OrganizationSummary | null>(
    () => page.props.currentOrganization ?? null,
);
const organizations = computed<OrganizationSummary[]>(
    () => page.props.organizations ?? [],
);

function select(organization: OrganizationSummary) {
    if (organization.id === current.value?.id) {
        return;
    }

    router.put(switchMethod(organization.slug).url);
}
</script>

<template>
    <DropdownMenu v-if="current">
        <DropdownMenuTrigger as-child>
            <SidebarMenuButton class="text-muted-foreground">
                <Building2 />
                <span class="truncate">{{ current.name }}</span>
                <ChevronsUpDown
                    v-if="organizations.length > 1"
                    class="ml-auto size-3.5 group-data-[collapsible=icon]:hidden"
                />
            </SidebarMenuButton>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="start" class="w-56">
            <DropdownMenuItem
                v-for="organization in organizations"
                :key="organization.id"
                @select="select(organization)"
            >
                <Building2 class="size-4 text-muted-foreground" />
                <span class="truncate">{{ organization.name }}</span>
                <Check
                    v-if="organization.id === current.id"
                    class="ml-auto size-4"
                />
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
