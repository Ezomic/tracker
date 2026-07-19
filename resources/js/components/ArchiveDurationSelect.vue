<script setup lang="ts">
import { computed, ref } from 'vue';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

const props = defineProps<{
    modelValue: number | null;
}>();

const presets = ['1', '7', '14', '30'];

function initialMode(): string {
    if (props.modelValue === null) {
        return 'never';
    }

    return presets.includes(String(props.modelValue))
        ? String(props.modelValue)
        : 'custom';
}

const mode = ref(initialMode());
const customDays = ref(
    props.modelValue !== null && !presets.includes(String(props.modelValue))
        ? props.modelValue
        : 30,
);

const submitted = computed(() => {
    if (mode.value === 'never') {
        return '';
    }

    if (mode.value === 'custom') {
        return String(customDays.value);
    }

    return mode.value;
});
</script>

<template>
    <div class="grid gap-2">
        <input type="hidden" name="archive_after_days" :value="submitted" />
        <Select v-model="mode">
            <SelectTrigger class="w-full">
                <SelectValue />
            </SelectTrigger>
            <SelectContent>
                <SelectItem value="never">{{
                    $t('archiveDuration.never')
                }}</SelectItem>
                <SelectItem value="1">{{
                    $t('archiveDuration.afterDay')
                }}</SelectItem>
                <SelectItem value="7">{{
                    $t('archiveDuration.afterWeek')
                }}</SelectItem>
                <SelectItem value="14">{{
                    $t('archiveDuration.afterTwoWeeks')
                }}</SelectItem>
                <SelectItem value="30">{{
                    $t('archiveDuration.afterMonth')
                }}</SelectItem>
                <SelectItem value="custom">{{
                    $t('archiveDuration.custom')
                }}</SelectItem>
            </SelectContent>
        </Select>
        <div v-if="mode === 'custom'" class="flex items-center gap-2">
            <Input
                v-model.number="customDays"
                type="number"
                min="1"
                class="w-24"
            />
            <span class="text-sm text-muted-foreground">{{
                $t('archiveDuration.daysUnit')
            }}</span>
        </div>
    </div>
</template>
