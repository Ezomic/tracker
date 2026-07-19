<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { ref } from 'vue';
import ProjectsController from '@/actions/App/Http/Controllers/ProjectsController';
import ArchiveDurationSelect from '@/components/ArchiveDurationSelect.vue';
import ColorSwatches from '@/components/ColorSwatches.vue';
import InputError from '@/components/InputError.vue';
import RepoInputs from '@/components/RepoInputs.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { Project, ProjectCategory } from '@/types';

const props = defineProps<{
    project: Project;
    palette: string[];
    usedColors?: string[];
    categories?: ProjectCategory[];
}>();

const color = ref(props.project.color);
// A raw <textarea> ignores :default-value (Vue doesn't map it to the
// defaultValue DOM property), so bind the initial value via v-model to keep
// the field populated — otherwise saving would submit a blank description.
const description = ref(props.project.description ?? '');
</script>

<template>
    <Dialog>
        <DialogTrigger as-child>
            <Button variant="outline" size="sm">{{ $t('common.edit') }}</Button>
        </DialogTrigger>
        <DialogContent>
            <Form
                v-bind="ProjectsController.update.form({ project: project.id })"
                :options="{ preserveScroll: true }"
                class="space-y-6"
                v-slot="{ errors, processing }"
            >
                <DialogHeader>
                    <DialogTitle>{{ $t('projects.editProject') }}</DialogTitle>
                    <DialogDescription>
                        {{
                            $t('projects.editProjectDescription', {
                                key: project.keyLocked
                                    ? ''
                                    : $t('projects.orKey'),
                            })
                        }}
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-2">
                    <Label :for="`name-${project.id}`">{{
                        $t('common.name')
                    }}</Label>
                    <Input
                        :id="`name-${project.id}`"
                        name="name"
                        :default-value="project.name"
                        required
                    />
                    <InputError :message="errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label :for="`description-${project.id}`">{{
                        $t('common.description')
                    }}</Label>
                    <textarea
                        :id="`description-${project.id}`"
                        v-model="description"
                        name="description"
                        rows="2"
                        :placeholder="$t('newIssue.descriptionPlaceholder')"
                        class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30"
                    />
                    <InputError :message="errors.description" />
                </div>

                <div class="grid gap-2">
                    <Label>{{ $t('projects.githubRepos') }}</Label>
                    <RepoInputs
                        :key="project.id"
                        :model-value="project.githubRepos"
                    />
                    <InputError :message="errors.github_repos" />
                </div>

                <div class="grid gap-2">
                    <Label :for="`production_url-${project.id}`">
                        {{ $t('projects.productionUrl') }}
                    </Label>
                    <Input
                        :id="`production_url-${project.id}`"
                        name="production_url"
                        type="url"
                        :default-value="project.productionUrl ?? ''"
                        placeholder="https://example.com"
                    />
                    <InputError :message="errors.production_url" />
                </div>

                <div
                    v-if="categories && categories.length > 0"
                    class="grid gap-2"
                >
                    <Label :for="`category-${project.id}`">{{
                        $t('projects.category')
                    }}</Label>
                    <Select
                        name="category_id"
                        :default-value="
                            project.categoryId === null
                                ? ''
                                : String(project.categoryId)
                        "
                    >
                        <SelectTrigger
                            :id="`category-${project.id}`"
                            class="w-full"
                        >
                            <SelectValue :placeholder="$t('common.none')" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="">{{
                                $t('common.none')
                            }}</SelectItem>
                            <SelectItem
                                v-for="category in categories"
                                :key="category.id"
                                :value="String(category.id)"
                            >
                                {{ '  '.repeat(category.depth)
                                }}{{ category.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="errors.category_id" />
                </div>

                <div class="grid gap-2">
                    <Label>{{ $t('projects.autoArchive') }}</Label>
                    <ArchiveDurationSelect
                        :key="project.id"
                        :model-value="project.archiveAfterDays"
                    />
                    <InputError :message="errors.archive_after_days" />
                </div>

                <div class="grid gap-2">
                    <Label>{{ $t('common.color') }}</Label>
                    <input type="hidden" name="color" :value="color" />
                    <ColorSwatches
                        v-model="color"
                        :palette="palette"
                        :used="usedColors"
                        :current="project.color"
                    />
                </div>

                <div class="grid gap-2">
                    <Label :for="`key-${project.id}`">{{
                        $t('projects.key')
                    }}</Label>
                    <Input
                        :id="`key-${project.id}`"
                        name="key"
                        :default-value="project.key"
                        :disabled="project.keyLocked"
                        maxlength="10"
                        pattern="[A-Z]{2,10}"
                        class="uppercase"
                    />
                    <InputError :message="errors.key" />
                    <p
                        v-if="project.keyLocked"
                        class="text-sm text-muted-foreground"
                    >
                        {{ $t('projects.keyLocked') }}
                    </p>
                </div>

                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button variant="secondary">{{
                            $t('common.cancel')
                        }}</Button>
                    </DialogClose>
                    <Button type="submit" :disabled="processing">
                        {{ $t('common.save') }}
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
