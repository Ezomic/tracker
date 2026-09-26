import { onMounted, onUnmounted, readonly, ref } from 'vue';

/**
 * Whether snag's reporting widget is on the page, and how to open it.
 *
 * The widget lives in a closed shadow root, so there is no element to click and no object to
 * call. Its whole public surface is two event names and a flag on the window, which is what this
 * wraps: see SNAG-84.
 */

const OPEN_EVENT = 'snag:open';

const READY_EVENT = 'snag:ready';

interface SnagWindow extends Window {
    __snagReady?: boolean;
}

export function useSnag() {
    const ready = ref(false);

    const markReady = (): void => {
        ready.value = true;
    };

    onMounted(() => {
        // The widget loads deferred and this component may mount either side of it, so read the
        // flag for the case where it got here first and listen for the case where it did not.
        if ((window as SnagWindow).__snagReady === true) {
            markReady();

            return;
        }

        document.addEventListener(READY_EVENT, markReady, { once: true });
    });

    onUnmounted(() => {
        document.removeEventListener(READY_EVENT, markReady);
    });

    const open = (): void => {
        document.dispatchEvent(new CustomEvent(OPEN_EVENT));
    };

    return { ready: readonly(ready), open };
}
