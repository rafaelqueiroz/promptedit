<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Sparkles } from '@lucide/vue';
import { computed, onMounted, onUnmounted, watch } from 'vue';
import MediaCard from '@/components/media/MediaCard.vue';
import { Button } from '@/components/ui/button';
import { dashboard, home } from '@/routes';
import type { MediaItem, Paginated } from '@/types';

const props = defineProps<{ media: Paginated<MediaItem> }>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'History', href: dashboard() }] },
});

// Poll only while something is still in flight, so an idle page is silent.
const hasPending = computed(() =>
    props.media.data.some(
        (item) => item.status === 'queued' || item.status === 'processing',
    ),
);

let timer: ReturnType<typeof setInterval> | null = null;

function stop() {
    if (timer !== null) {
        clearInterval(timer);
        timer = null;
    }
}

// Started on mount rather than during setup: timers have no meaning during
// SSR, and starting one there would run on the server.
onMounted(() => {
    watch(
        hasPending,
        (pending) => {
            stop();

            if (pending) {
                timer = setInterval(() => {
                    router.reload({ only: ['media'] });
                }, 3000);
            }
        },
        { immediate: true },
    );
});

onUnmounted(stop);
</script>

<template>
    <Head title="History" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold tracking-tight">
                    Your generations
                </h1>
                <p class="text-sm text-muted-foreground">
                    Estimated prices become exact once a generation finishes.
                    Failed generations are free.
                </p>
            </div>

            <Button as-child>
                <Link :href="home()">
                    <Sparkles />
                    Generate
                </Link>
            </Button>
        </div>

        <div
            v-if="props.media.data.length"
            class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
        >
            <MediaCard
                v-for="item in props.media.data"
                :key="item.id"
                :item="item"
            />
        </div>

        <div
            v-else
            class="flex flex-1 flex-col items-center justify-center gap-3 rounded-xl border border-dashed py-20 text-center"
        >
            <Sparkles class="size-7 text-muted-foreground" />
            <div>
                <p class="font-medium">No generations yet</p>
                <p class="text-sm text-muted-foreground">
                    Write a prompt and press Generate. Every result shows up
                    here first.
                </p>
            </div>
            <Button as-child class="mt-1">
                <Link :href="home()">Generate your first image</Link>
            </Button>
        </div>
    </div>
</template>
