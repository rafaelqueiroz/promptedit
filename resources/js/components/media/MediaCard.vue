<script setup lang="ts">
import { ImageOff } from '@lucide/vue';
import { computed, ref } from 'vue';
import MediaStatusBadge from '@/components/media/MediaStatusBadge.vue';
import { Skeleton } from '@/components/ui/skeleton';
import { Spinner } from '@/components/ui/spinner';
import { formatMoney } from '@/lib/money';
import type { MediaItem } from '@/types';

const props = defineProps<{ item: MediaItem }>();

const imageFailed = ref(false);

const isPending = computed(
    () => props.item.status === 'queued' || props.item.status === 'processing',
);

// Until it finishes we only have an estimate; afterwards, the real figure.
const priceLabel = computed(() =>
    props.item.price === null
        ? `≈ ${formatMoney(props.item.estimatedPrice)}`
        : formatMoney(props.item.price),
);

/*
 * Once settled, show what was estimated beside what was charged — but only
 * when the two differ, since repeating the same figure twice is just noise.
 */
const showEstimate = computed(
    () =>
        props.item.price !== null &&
        formatMoney(props.item.estimatedPrice) !==
            formatMoney(props.item.price),
);
</script>

<template>
    <article class="overflow-hidden rounded-xl border bg-card">
        <div class="relative aspect-square bg-muted">
            <div
                v-if="isPending"
                class="flex size-full flex-col items-center justify-center gap-3"
            >
                <Skeleton class="absolute inset-0 rounded-none" />
                <Spinner class="relative size-6 text-muted-foreground" />
                <span
                    class="relative text-xs font-medium text-muted-foreground"
                >
                    {{
                        item.status === 'queued'
                            ? 'Waiting to start…'
                            : 'Generating…'
                    }}
                </span>
            </div>

            <img
                v-else-if="item.imageUrl && !imageFailed"
                :src="item.imageUrl"
                :alt="item.prompt"
                loading="lazy"
                class="size-full object-cover"
                @error="imageFailed = true"
            />

            <div
                v-else
                class="flex size-full flex-col items-center justify-center gap-2 text-muted-foreground"
            >
                <ImageOff class="size-6" />
                <span class="px-4 text-center text-xs">
                    {{
                        item.status === 'failed'
                            ? 'No image was generated'
                            : 'Image is no longer available'
                    }}
                </span>
            </div>
        </div>

        <div class="space-y-2 p-4">
            <div class="flex items-start justify-between gap-2">
                <MediaStatusBadge
                    :status="item.status"
                    :label="item.statusLabel"
                />
                <span class="flex items-baseline gap-1.5 whitespace-nowrap">
                    <span
                        v-if="showEstimate"
                        class="text-xs text-muted-foreground tabular-nums line-through"
                        :title="`Estimated $${item.estimatedPrice}`"
                    >
                        {{ formatMoney(item.estimatedPrice) }}
                    </span>
                    <span
                        class="text-sm font-semibold tabular-nums"
                        :title="
                            item.price === null
                                ? `Estimated $${item.estimatedPrice}`
                                : `Charged $${item.price}`
                        "
                    >
                        {{ priceLabel }}
                    </span>
                </span>
            </div>

            <p class="line-clamp-2 text-sm" :title="item.prompt">
                {{ item.prompt }}
            </p>

            <p
                v-if="item.errorMessage"
                class="text-xs text-red-600 dark:text-red-400"
            >
                {{ item.errorMessage }}
            </p>

            <p class="text-xs text-muted-foreground">
                {{ item.size }} · {{ item.quality }}
                <template v-if="item.status === 'failed'"> · free</template>
            </p>
        </div>
    </article>
</template>
