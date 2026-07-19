<script setup lang="ts">
import { Plus, X } from '@lucide/vue';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

const props = defineProps<{
    modelValue: string[];
}>();

const repos = ref<string[]>(
    props.modelValue.length ? [...props.modelValue] : [''],
);

function addRepo() {
    repos.value.push('');
}

function removeRepo(index: number) {
    repos.value.splice(index, 1);

    if (repos.value.length === 0) {
        repos.value.push('');
    }
}
</script>

<template>
    <div class="grid gap-2">
        <div
            v-for="(repo, index) in repos"
            :key="index"
            class="flex items-center gap-2"
        >
            <Input
                v-model="repos[index]"
                name="github_repos[]"
                placeholder="owner/repo"
                class="w-64"
            />
            <Button
                type="button"
                variant="ghost"
                size="icon"
                class="shrink-0 text-muted-foreground"
                :aria-label="$t('common.removeRepo')"
                @click="removeRepo(index)"
            >
                <X class="size-4" />
            </Button>
        </div>
        <Button
            type="button"
            variant="outline"
            size="sm"
            class="w-fit"
            @click="addRepo"
        >
            <Plus class="size-4" />
            {{ $t('common.addRepo') }}
        </Button>
    </div>
</template>
