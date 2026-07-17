<script setup lang="ts">
import { nextTick, onMounted, ref, watch } from 'vue';

const model = defineModel<string>({ default: '' });

const el = ref<HTMLTextAreaElement | null>(null);

// Collapse first, then grow to the content: measuring scrollHeight against the
// current height would only ever ratchet upwards.
function resize() {
    const node = el.value;

    if (node === null) {
        return;
    }

    node.style.height = 'auto';
    node.style.height = `${node.scrollHeight}px`;
}

onMounted(() => nextTick(resize));

// Covers programmatic changes (reset-on-success, prop-driven updates), which
// don't fire an input event.
watch(model, () => nextTick(resize));
</script>

<template>
    <textarea
        ref="el"
        v-model="model"
        class="resize-none overflow-hidden"
        @input="resize"
    />
</template>
