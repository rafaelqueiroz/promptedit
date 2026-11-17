<script setup lang="ts">
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import type { MediaStatus } from '@/types';

const props = defineProps<{ status: MediaStatus; label: string }>();

const classes = computed(
    () =>
        ({
            queued: 'bg-secondary text-secondary-foreground',
            processing: 'bg-amber-500/15 text-amber-700 dark:text-amber-400',
            succeeded:
                'bg-emerald-500/15 text-emerald-700 dark:text-emerald-400',
            failed: 'bg-red-500/10 text-red-600 dark:text-red-400',
        })[props.status],
);
</script>

<template>
    <Badge variant="outline" :class="['gap-1.5 border-transparent', classes]">
        <span
            v-if="status === 'queued' || status === 'processing'"
            class="size-1.5 animate-pulse rounded-full bg-current"
        />
        {{ label }}
    </Badge>
</template>
