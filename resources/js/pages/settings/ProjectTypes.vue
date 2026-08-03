<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { GripVertical, Plus, Star, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { destroy, index, store, update } from '@/routes/project-types';

interface Lane {
    id: number | null;
    name: string;
    category: string;
    color: string;
    isDefault: boolean;
}

interface ProjectTypeData {
    id: number;
    name: string;
    description: string | null;
    isDefault: boolean;
    projectsCount: number;
    states: Lane[];
}

interface EditableType {
    id: number | null;
    name: string;
    description: string;
    isDefault: boolean;
    projectsCount: number;
    states: Lane[];
}

const props = defineProps<{
    types: ProjectTypeData[];
    categories: string[];
    canManage: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Project types', href: index() }],
    },
});

const { t } = useI18n();

function toEditable(type: ProjectTypeData): EditableType {
    return {
        id: type.id,
        name: type.name,
        description: type.description ?? '',
        isDefault: type.isDefault,
        projectsCount: type.projectsCount,
        states: type.states.map((lane) => ({ ...lane })),
    };
}

const editable = ref<EditableType[]>(props.types.map(toEditable));

function addType() {
    editable.value.push({
        id: null,
        name: '',
        description: '',
        isDefault: false,
        projectsCount: 0,
        states: [
            {
                id: null,
                name: 'Backlog',
                category: 'backlog',
                color: '#9ca3af',
                isDefault: true,
            },
            {
                id: null,
                name: 'In progress',
                category: 'started',
                color: '#d85a30',
                isDefault: false,
            },
            {
                id: null,
                name: 'Done',
                category: 'completed',
                color: '#1d9e75',
                isDefault: false,
            },
        ],
    });
}

function addLane(type: EditableType) {
    type.states.push({
        id: null,
        name: '',
        category: 'started',
        color: '#378add',
        isDefault: type.states.length === 0,
    });
}

function removeLane(type: EditableType, index: number) {
    type.states.splice(index, 1);

    if (!type.states.some((lane) => lane.isDefault) && type.states[0]) {
        type.states[0].isDefault = true;
    }
}

function makeDefaultLane(type: EditableType, lane: Lane) {
    type.states.forEach((each) => (each.isDefault = each === lane));
}

const dragType = ref<EditableType | null>(null);
const dragIndex = ref<number | null>(null);

function onLaneDragStart(type: EditableType, index: number) {
    dragType.value = type;
    dragIndex.value = index;
}

function onLaneDrop(type: EditableType, index: number) {
    if (
        dragType.value !== type ||
        dragIndex.value === null ||
        dragIndex.value === index
    ) {
        return;
    }

    const [moved] = type.states.splice(dragIndex.value, 1);
    type.states.splice(index, 0, moved);
    dragIndex.value = null;
    dragType.value = null;
}

function save(type: EditableType) {
    const payload = {
        name: type.name,
        description: type.description || null,
        states: type.states.map((lane) => ({
            id: lane.id,
            name: lane.name,
            category: lane.category,
            color: lane.color,
            isDefault: lane.isDefault,
        })),
    };

    if (type.id === null) {
        router.post(store.url(), payload, { preserveScroll: true });
    } else {
        router.patch(update.url({ projectType: type.id }), payload, {
            preserveScroll: true,
        });
    }
}

function removeType(type: EditableType, position: number) {
    if (type.id === null) {
        editable.value.splice(position, 1);

        return;
    }

    if (!window.confirm(t('projectTypes.confirmDelete'))) {
        return;
    }

    router.delete(destroy.url({ projectType: type.id }), {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="$t('projectTypes.title')" />

    <h1 class="sr-only">{{ $t('projectTypes.title') }}</h1>

    <div class="space-y-6">
        <div class="flex items-start justify-between gap-4">
            <Heading
                variant="small"
                :title="$t('projectTypes.title')"
                :description="$t('projectTypes.description')"
            />
            <Button
                v-if="canManage"
                type="button"
                variant="outline"
                size="sm"
                @click="addType"
            >
                <Plus class="size-4" />
                {{ $t('projectTypes.newType') }}
            </Button>
        </div>

        <p
            v-if="editable.length === 0"
            class="rounded-lg border border-border p-8 text-center text-sm text-muted-foreground"
        >
            {{ $t('projectTypes.empty') }}
        </p>

        <div
            v-for="(type, position) in editable"
            :key="type.id ?? `new-${position}`"
            class="space-y-4 rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border"
        >
            <div class="flex items-center gap-2">
                <Input
                    v-model="type.name"
                    :placeholder="$t('projectTypes.namePlaceholder')"
                    class="max-w-xs font-medium"
                    :disabled="!canManage"
                />
                <span
                    v-if="type.isDefault"
                    class="inline-flex items-center gap-1 rounded-full bg-primary/10 px-2 py-0.5 text-[11px] font-medium text-primary"
                >
                    <Star class="size-3" />
                    {{ $t('projectTypes.default') }}
                </span>
                <span
                    v-if="type.projectsCount > 0"
                    class="text-xs text-muted-foreground"
                >
                    {{
                        $t('projectTypes.inUse', { count: type.projectsCount })
                    }}
                </span>
                <Button
                    v-if="
                        canManage && !type.isDefault && type.projectsCount === 0
                    "
                    type="button"
                    variant="ghost"
                    size="sm"
                    class="ml-auto text-destructive hover:text-destructive"
                    @click="removeType(type, position)"
                >
                    <Trash2 class="size-4" />
                    {{ $t('projectTypes.deleteType') }}
                </Button>
            </div>

            <Input
                v-model="type.description"
                :placeholder="$t('projectTypes.descriptionPlaceholder')"
                :disabled="!canManage"
            />

            <div class="space-y-2">
                <Label class="text-xs text-muted-foreground">{{
                    $t('projectTypes.lanes')
                }}</Label>
                <div
                    v-for="(lane, laneIndex) in type.states"
                    :key="lane.id ?? `new-lane-${laneIndex}`"
                    class="flex items-center gap-2 rounded-lg border border-border bg-background p-2"
                    :draggable="canManage"
                    @dragstart="onLaneDragStart(type, laneIndex)"
                    @dragover.prevent
                    @drop="onLaneDrop(type, laneIndex)"
                >
                    <GripVertical
                        class="size-4 shrink-0 cursor-grab text-muted-foreground"
                    />
                    <input
                        v-model="lane.color"
                        type="color"
                        class="size-7 shrink-0 cursor-pointer rounded border border-border bg-transparent"
                        :disabled="!canManage"
                    />
                    <Input
                        v-model="lane.name"
                        :placeholder="$t('projectTypes.lanePlaceholder')"
                        class="h-8 flex-1"
                        :disabled="!canManage"
                    />
                    <select
                        v-model="lane.category"
                        class="h-8 rounded-md border border-input bg-transparent px-2 text-sm"
                        :disabled="!canManage"
                    >
                        <option
                            v-for="category in categories"
                            :key="category"
                            :value="category"
                        >
                            {{ $t(`statusCategory.${category}`) }}
                        </option>
                    </select>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        class="size-8"
                        :class="
                            lane.isDefault
                                ? 'text-primary'
                                : 'text-muted-foreground'
                        "
                        :title="$t('projectTypes.defaultLane')"
                        :disabled="!canManage"
                        @click="makeDefaultLane(type, lane)"
                    >
                        <Star
                            class="size-4"
                            :fill="lane.isDefault ? 'currentColor' : 'none'"
                        />
                    </Button>
                    <Button
                        v-if="canManage && type.states.length > 1"
                        type="button"
                        variant="ghost"
                        size="icon"
                        class="size-8 text-muted-foreground hover:text-destructive"
                        @click="removeLane(type, laneIndex)"
                    >
                        <Trash2 class="size-4" />
                    </Button>
                </div>

                <Button
                    v-if="canManage"
                    type="button"
                    variant="ghost"
                    size="sm"
                    @click="addLane(type)"
                >
                    <Plus class="size-4" />
                    {{ $t('projectTypes.addLane') }}
                </Button>
            </div>

            <div v-if="canManage" class="flex justify-end">
                <Button type="button" size="sm" @click="save(type)">
                    {{ $t('projectTypes.save') }}
                </Button>
            </div>
        </div>
    </div>
</template>
