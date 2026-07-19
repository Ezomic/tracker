<script setup lang="ts">
import {
    BookText,
    ChevronDown,
    ExternalLink,
    FolderGit2,
    Globe,
} from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { ProjectLinks } from '@/types';

const props = defineProps<{
    links: ProjectLinks;
}>();

const hasPrimary = computed(
    () => props.links.production !== null || props.links.docs !== null,
);

const hasAny = computed(() => hasPrimary.value || props.links.repos.length > 0);
</script>

<template>
    <DropdownMenu v-if="hasAny">
        <DropdownMenuTrigger as-child>
            <Button
                variant="outline"
                size="sm"
                class="h-7 gap-1.5 text-muted-foreground"
            >
                <ExternalLink class="size-3.5" />
                {{ $t('projects.links') }}
                <ChevronDown class="size-3.5 opacity-70" />
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="w-56">
            <DropdownMenuItem v-if="links.production" as-child>
                <a
                    :href="links.production"
                    target="_blank"
                    rel="noopener noreferrer"
                >
                    <Globe />
                    <span>{{ $t('projects.production') }}</span>
                    <ExternalLink class="ml-auto size-3 opacity-50" />
                </a>
            </DropdownMenuItem>
            <DropdownMenuItem v-if="links.docs" as-child>
                <a :href="links.docs" target="_blank" rel="noopener noreferrer">
                    <BookText />
                    <span>{{ $t('projects.documentation') }}</span>
                    <ExternalLink class="ml-auto size-3 opacity-50" />
                </a>
            </DropdownMenuItem>

            <template v-if="links.repos.length">
                <DropdownMenuSeparator v-if="hasPrimary" />
                <DropdownMenuLabel class="text-xs text-muted-foreground">
                    {{ $t('projects.repositories') }}
                </DropdownMenuLabel>
                <DropdownMenuItem
                    v-for="repo in links.repos"
                    :key="repo.url"
                    as-child
                >
                    <a
                        :href="repo.url"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        <FolderGit2 />
                        <span class="truncate">{{ repo.name }}</span>
                    </a>
                </DropdownMenuItem>
            </template>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
