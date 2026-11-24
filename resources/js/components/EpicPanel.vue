<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import type {
    EpicDropTarget,
    EpicFocus,
    EpicSegment,
    EpicSummary,
} from '@/lib/epics';
import { canDropOnEpic, groupEpicsByProject } from '@/lib/epics';
import { show } from '@/routes/issues';
import type { Issue } from '@/types';

const props = withDefaults(
    defineProps<{
        epics: EpicSummary[];
        grouped: boolean;
        focus: EpicFocus;
        /** The card being dragged; only the desktop panel takes drops. */
        dragging?: Issue | null;
    }>(),
    { dragging: null },
);

const showAll = defineModel<boolean>('showAll', { required: true });

const emit = defineEmits<{
    toggle: [focus: number | 'none'];
    hover: [focus: EpicFocus];
    drop: [target: EpicDropTarget];
}>();

const { t } = useI18n();

const groups = computed(() =>
    props.grouped
        ? groupEpicsByProject(props.epics)
        : [{ key: '', name: '', epics: props.epics }],
);

const dropOver = ref<number | 'none' | null>(null);

watch(
    () => props.dragging,
    () => (dropOver.value = null),
);

function targetKey(target: EpicDropTarget): number | 'none' {
    return target === 'none' ? 'none' : target.id;
}

function accepts(target: EpicDropTarget): boolean {
    return props.dragging !== null && canDropOnEpic(props.dragging, target);
}

function dropClass(target: EpicDropTarget): string {
    if (props.dragging === null) {
        return '';
    }

    if (!accepts(target)) {
        return 'opacity-40';
    }

    return dropOver.value === targetKey(target)
        ? 'bg-primary/10 ring-2 ring-primary ring-inset'
        : 'ring-1 ring-primary/40 ring-inset';
}

// Only a valid row cancels dragenter/dragover, so the browser itself refuses
// the rest.
function onDragOver(event: DragEvent, target: EpicDropTarget) {
    if (!accepts(target)) {
        return;
    }

    event.preventDefault();
    dropOver.value = targetKey(target);
}

function onDragLeave(event: DragEvent, target: EpicDropTarget) {
    // Moving between the row's own children fires dragleave on the row too.
    const row = event.currentTarget as HTMLElement;

    if (row.contains(event.relatedTarget as Node | null)) {
        return;
    }

    if (dropOver.value === targetKey(target)) {
        dropOver.value = null;
    }
}

function onDrop(event: DragEvent, target: EpicDropTarget) {
    if (!accepts(target)) {
        return;
    }

    event.preventDefault();
    dropOver.value = null;
    emit('drop', target);
}

function segmentTitle(segment: EpicSegment): string {
    const label =
        segment.key === 'other' ? t('board.otherStates') : segment.label;

    return `${label}: ${segment.count}`;
}
</script>

<template>
    <div class="flex min-h-0 flex-col">
        <div class="min-h-0 flex-1 overflow-y-auto p-2">
            <p
                v-if="epics.length === 0"
                class="px-2 py-4 text-center text-xs text-muted-foreground/70"
            >
                {{ showAll ? $t('board.noEpics') : $t('board.noOpenEpics') }}
            </p>

            <section
                v-for="group in groups"
                :key="group.key"
                class="flex flex-col gap-0.5"
            >
                <h3
                    v-if="group.name"
                    class="truncate px-2 pt-2 pb-1 text-[11px] font-medium tracking-wide text-muted-foreground uppercase"
                >
                    {{ group.name }}
                </h3>

                <div
                    v-for="epic in group.epics"
                    :key="epic.id"
                    class="relative rounded-lg transition-colors"
                    :class="[
                        focus === epic.id ? 'bg-accent' : 'hover:bg-accent/50',
                        epic.archived ? 'opacity-60' : '',
                        dropClass(epic),
                    ]"
                    @mouseenter="emit('hover', epic.id)"
                    @mouseleave="emit('hover', null)"
                    @dragenter="onDragOver($event, epic)"
                    @dragover="onDragOver($event, epic)"
                    @dragleave="onDragLeave($event, epic)"
                    @drop="onDrop($event, epic)"
                >
                    <button
                        type="button"
                        class="flex w-full flex-col gap-1.5 rounded-lg px-2 py-2 text-left focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        :aria-pressed="focus === epic.id"
                        @click="emit('toggle', epic.id)"
                    >
                        <span class="flex min-w-0 items-center gap-2">
                            <span
                                class="size-2.5 shrink-0 rounded-full"
                                :style="{ backgroundColor: epic.color }"
                            />
                            <span
                                class="truncate text-sm font-medium tracking-tight"
                                :title="epic.title"
                            >
                                {{ epic.title }}
                            </span>
                            <!-- Reserves the room the identifier link sits in. -->
                            <span
                                class="invisible ml-auto shrink-0 pl-2 font-mono text-[11px]"
                                aria-hidden="true"
                            >
                                {{ epic.identifier }}
                            </span>
                        </span>

                        <span class="flex items-center gap-2 pl-4.5">
                            <span
                                class="flex h-1.5 flex-1 gap-px overflow-hidden rounded-full bg-muted"
                                :title="
                                    $t('issue.subDone', {
                                        done: epic.done,
                                        total: epic.total,
                                    })
                                "
                            >
                                <span
                                    v-for="segment in epic.segments"
                                    :key="segment.key"
                                    class="h-full"
                                    :class="segment.colorClass"
                                    :style="{
                                        flexGrow: segment.count,
                                        backgroundColor:
                                            segment.color ?? undefined,
                                    }"
                                    :title="segmentTitle(segment)"
                                />
                            </span>
                            <span
                                class="shrink-0 text-[11px] text-muted-foreground tabular-nums"
                            >
                                {{ epic.done }}/{{ epic.total }}
                            </span>
                        </span>
                    </button>

                    <Link
                        :href="show({ issue: epic.identifier })"
                        class="absolute top-2 right-2 font-mono text-[11px] text-muted-foreground hover:text-foreground hover:underline"
                    >
                        {{ epic.identifier }}
                    </Link>
                </div>
            </section>

            <button
                type="button"
                class="mt-1 flex w-full items-center gap-2 rounded-lg px-2 py-2 text-left text-sm text-muted-foreground transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                :class="[
                    focus === 'none' ? 'bg-accent' : 'hover:bg-accent/50',
                    dropClass('none'),
                ]"
                :aria-pressed="focus === 'none'"
                @click="emit('toggle', 'none')"
                @mouseenter="emit('hover', 'none')"
                @mouseleave="emit('hover', null)"
                @dragenter="onDragOver($event, 'none')"
                @dragover="onDragOver($event, 'none')"
                @dragleave="onDragLeave($event, 'none')"
                @drop="onDrop($event, 'none')"
            >
                <span
                    class="size-2.5 shrink-0 rounded-full border border-dashed border-muted-foreground/60"
                />
                {{ $t('board.noEpic') }}
            </button>
        </div>

        <Label
            class="flex shrink-0 items-center gap-2 border-t px-3 py-2.5 text-xs font-normal text-muted-foreground"
        >
            <Switch v-model="showAll" />
            {{ $t('board.showAllEpics') }}
        </Label>
    </div>
</template>
