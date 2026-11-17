<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import {
    Activity,
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    Eye,
    Moon,
    Receipt,
    Sun,
} from '@lucide/vue';
import type { LucideIcon } from '@lucide/vue';
import {
    computed,
    nextTick,
    onMounted,
    onUnmounted,
    ref,
    useTemplateRef,
    watch,
} from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import AuthDialog from '@/components/auth/AuthDialog.vue';
import MediaCard from '@/components/media/MediaCard.vue';
import PromptComposer from '@/components/generation/PromptComposer.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import UserInfo from '@/components/UserInfo.vue';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { useAppearance } from '@/composables/useAppearance';
import { saveDraft, takeDraft } from '@/lib/draft';
import { formatMoney } from '@/lib/money';
import { home } from '@/routes';
import * as media from '@/routes/media';
import type {
    AuthMode,
    EstimateTable,
    GenerationDraft,
    GenerationOptions,
    MediaItem,
} from '@/types';

const props = defineProps<{
    options: GenerationOptions;
    estimates: EstimateTable;
    maxReferenceImages: number;
    defaults: { size: string; quality: string };
    samplePrompts: string[];
    recentMedia: MediaItem[];
}>();

const page = usePage();
const user = computed(() => page.props.auth.user);
const appName = computed(() => page.props.name);

const { resolvedAppearance, updateAppearance } = useAppearance();

const draft = ref<GenerationDraft>({
    prompt: '',
    size: props.defaults.size,
    quality: props.defaults.quality,
    referenceImageUrls: [],
});

const auth = ref<{ open: boolean; mode: AuthMode; reason?: string }>({
    open: false,
    mode: 'login',
});

type Feature = {
    icon: LucideIcon;
    title: string;
    text: string;
    statuses?: { label: string; class: string }[];
};

const features: Feature[] = [
    {
        icon: Eye,
        title: 'See the price first',
        text: 'Every option updates the estimate as you change it. Nothing is submitted without a number next to it.',
    },
    {
        icon: Receipt,
        title: 'Pay for what renders',
        text: 'The exact cost is recorded the moment Pixelforge finishes. Failed generations cost nothing — not even a fraction of a cent.',
    },
    {
        icon: Activity,
        title: 'Watch it happen',
        text: 'Your history updates live, with the estimate and the final cost side by side.',
        statuses: [
            {
                label: 'Queued',
                class: 'bg-secondary text-secondary-foreground',
            },
            {
                label: 'Processing',
                class: 'bg-amber-500/15 text-amber-700 dark:text-amber-400',
            },
            {
                label: 'Succeeded',
                class: 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-400',
            },
            {
                label: 'Failed · free',
                class: 'bg-red-500/10 text-red-600 dark:text-red-400',
            },
        ],
    },
];

function openAuth(mode: AuthMode, reason?: string) {
    auth.value = { open: true, mode, reason };
}

function toggleTheme() {
    updateAppearance(resolvedAppearance.value === 'dark' ? 'light' : 'dark');
}

function useSample(prompt: string) {
    draft.value.prompt = prompt;
    document.getElementById('prompt')?.focus();
}

const results = useTemplateRef<HTMLElement>('results');

const form = useForm({
    prompt: '',
    size: '',
    quality: '',
    reference_image_urls: [] as string[],
});

function submit(current: GenerationDraft) {
    if (!user.value) {
        // The draft survives the sign-in round trip so nothing is retyped.
        saveDraft(current);

        openAuth(
            'login',
            'Sign in to generate — your prompt will be waiting for you.',
        );

        return;
    }

    form.prompt = current.prompt;
    form.size = current.size;
    form.quality = current.quality;
    form.reference_image_urls = current.referenceImageUrls.filter(
        (url) => url.trim() !== '',
    );

    /*
     * The controller redirects back, so this re-renders the landing page with
     * a fresh recentMedia and the new generation already in the slider.
     * preserveState keeps the composer's own draft from being reset.
     */
    form.post(media.store().url, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            draft.value.prompt = '';
            draft.value.referenceImageUrls = [];

            // Carry the eye to the result that just appeared. nextTick so the
            // new card is in the DOM before we measure where to scroll.
            void nextTick(() => {
                results.value?.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start',
                });
            });
        },
    });
}

// A prompt written before signing in, restored after the round trip.
// In onMounted because it reads sessionStorage, which SSR has no access to.
onMounted(() => {
    if (!user.value) {
        return;
    }

    const saved = takeDraft();

    if (saved) {
        draft.value = saved;
    }
});

// Poll only while something is still running, so an idle page is silent.
const hasPending = computed(() =>
    props.recentMedia.some(
        (item) => item.status === 'queued' || item.status === 'processing',
    ),
);

let timer: ReturnType<typeof setInterval> | null = null;

function stopPolling() {
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
            stopPolling();

            if (pending) {
                timer = setInterval(() => {
                    router.reload({ only: ['recentMedia'] });
                }, 3000);
            }
        },
        { immediate: true },
    );
});

onUnmounted(stopPolling);

// Slider state. The arrows are hidden until the rail actually overflows.
const rail = useTemplateRef<HTMLElement>('rail');
const canScroll = ref(false);
const atStart = ref(true);
const atEnd = ref(false);

function updateScrollState() {
    const el = rail.value;

    if (!el) {
        return;
    }

    canScroll.value = el.scrollWidth > el.clientWidth + 1;
    atStart.value = el.scrollLeft <= 1;
    atEnd.value = el.scrollLeft + el.clientWidth >= el.scrollWidth - 1;
}

function scrollBy(direction: number) {
    rail.value?.scrollBy({
        left: direction * (rail.value.clientWidth * 0.8),
        behavior: 'smooth',
    });
}

onMounted(updateScrollState);
watch(
    () => props.recentMedia,
    () => nextTick(updateScrollState),
    { deep: true },
);
</script>

<template>
    <Head title="Generate images with Pixelforge" />

    <div class="relative isolate flex min-h-svh flex-col">
        <div
            aria-hidden="true"
            class="pointer-events-none absolute inset-x-0 top-0 -z-10 h-[42rem] overflow-hidden"
        >
            <div
                class="absolute inset-0 bg-[radial-gradient(var(--border)_1px,transparent_1px)] [mask-image:radial-gradient(ellipse_60%_60%_at_50%_0%,black,transparent)] [background-size:24px_24px]"
            />
            <div
                class="absolute top-24 left-1/2 h-72 w-[48rem] -translate-x-1/2 rounded-full bg-brand opacity-30 blur-3xl dark:opacity-15"
            />
        </div>

        <header class="sticky top-0 z-40 bg-background/70 backdrop-blur">
            <div
                class="mx-auto flex h-16 max-w-5xl items-center justify-between px-4 sm:px-6"
            >
                <Link
                    :href="home()"
                    class="flex items-center gap-2 font-semibold"
                >
                    <span
                        class="flex size-8 items-center justify-center rounded-md bg-primary text-primary-foreground"
                    >
                        <AppLogoIcon class="size-5 fill-current" />
                    </span>
                    {{ appName }}
                </Link>

                <nav class="flex items-center gap-1">
                    <Button
                        variant="ghost"
                        size="icon"
                        :aria-label="
                            resolvedAppearance === 'dark'
                                ? 'Switch to light mode'
                                : 'Switch to dark mode'
                        "
                        @click="toggleTheme"
                    >
                        <Sun v-if="resolvedAppearance === 'dark'" />
                        <Moon v-else />
                    </Button>

                    <DropdownMenu v-if="user">
                        <DropdownMenuTrigger as-child>
                            <Button
                                variant="ghost"
                                class="h-9 gap-2 px-2"
                                data-test="user-menu"
                            >
                                <UserInfo :user="user" :show-name="false" />
                                <ChevronDown class="size-4 opacity-60" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent class="w-56" align="end">
                            <UserMenuContent :user="user" />
                        </DropdownMenuContent>
                    </DropdownMenu>
                    <template v-else>
                        <Button variant="ghost" @click="openAuth('login')">
                            Sign in
                        </Button>
                        <Button @click="openAuth('register')">Sign up</Button>
                    </template>
                </nav>
            </div>
        </header>

        <main class="flex-1">
            <section
                class="mx-auto max-w-5xl px-4 pt-14 pb-16 sm:px-6 sm:pt-20"
            >
                <div class="mx-auto max-w-2xl text-center">
                    <Badge
                        variant="outline"
                        class="gap-1.5 bg-background/60 px-3 py-1 text-xs font-medium"
                    >
                        <span class="size-1.5 rounded-full bg-brand" />
                        Image generation · powered by Pixelforge
                    </Badge>
                    <h1
                        class="mt-6 text-4xl font-semibold tracking-tight text-balance sm:text-5xl"
                    >
                        Type a prompt. Know the price. Get the picture.
                    </h1>
                    <p class="mt-4 text-lg text-pretty text-muted-foreground">
                        Describe what you want, pick a size and quality, and see
                        what it will cost before you press Generate. The exact
                        cost is recorded the moment it finishes — and failed
                        generations are free.
                    </p>
                </div>

                <div class="mx-auto mt-10 max-w-3xl">
                    <PromptComposer
                        v-model="draft"
                        :options="options"
                        :estimates="estimates"
                        :max-reference-images="maxReferenceImages"
                        :processing="form.processing"
                        @submit="submit"
                    />

                    <div
                        v-if="Object.keys(form.errors).length"
                        class="mt-3 space-y-1 rounded-lg border border-destructive/30 bg-destructive/5 p-3 text-sm text-red-600 dark:text-red-400"
                    >
                        <p v-for="(error, key) in form.errors" :key="key">
                            {{ error }}
                        </p>
                    </div>

                    <div
                        class="mt-4 flex flex-wrap items-center justify-center gap-2 text-xs text-muted-foreground"
                    >
                        <span>Try one:</span>
                        <button
                            v-for="prompt in samplePrompts"
                            :key="prompt"
                            type="button"
                            class="rounded-full border bg-background/60 px-3 py-1 text-left transition-colors hover:border-foreground/30 hover:text-foreground"
                            @click="useSample(prompt)"
                        >
                            {{ prompt }}
                        </button>
                    </div>
                </div>
            </section>

            <section
                v-if="user && recentMedia.length"
                ref="results"
                class="mx-auto max-w-5xl scroll-mt-20 px-4 pb-16 sm:px-6"
            >
                <div class="mb-4 flex items-center justify-between gap-4">
                    <h2 class="text-lg font-semibold tracking-tight">
                        Your latest generations
                    </h2>

                    <div v-if="canScroll" class="flex gap-1">
                        <Button
                            variant="outline"
                            size="icon-sm"
                            aria-label="Scroll back"
                            :disabled="atStart"
                            @click="scrollBy(-1)"
                        >
                            <ChevronLeft />
                        </Button>
                        <Button
                            variant="outline"
                            size="icon-sm"
                            aria-label="Scroll forward"
                            :disabled="atEnd"
                            @click="scrollBy(1)"
                        >
                            <ChevronRight />
                        </Button>
                    </div>
                </div>

                <!-- A rail rather than a grid: everything stays on this page. -->
                <div
                    ref="rail"
                    class="-mx-4 flex snap-x snap-mandatory gap-4 overflow-x-auto scroll-smooth px-4 pb-2 sm:mx-0 sm:px-0 [&::-webkit-scrollbar]:hidden"
                    style="scrollbar-width: none"
                    @scroll="updateScrollState"
                >
                    <MediaCard
                        v-for="item in recentMedia"
                        :key="item.id"
                        :item="item"
                        class="w-64 shrink-0 snap-start sm:w-72"
                    />
                </div>
            </section>

            <section class="border-t bg-muted/30">
                <div
                    class="mx-auto grid max-w-5xl gap-12 px-4 py-20 sm:px-6 lg:grid-cols-[1.1fr_0.9fr] lg:items-start"
                >
                    <div>
                        <h2 class="text-2xl font-semibold tracking-tight">
                            Pricing you can see coming
                        </h2>
                        <p class="mt-2 text-pretty text-muted-foreground">
                            Prices are Pixelforge's token rates plus a 25%
                            service margin, in US dollars. Part of the cost is
                            only known once a generation finishes, so what you
                            see up front is an estimate — and what you're
                            charged is the real thing.
                        </p>

                        <ul class="mt-8 space-y-6">
                            <li
                                v-for="feature in features"
                                :key="feature.title"
                                class="flex gap-4"
                            >
                                <span
                                    class="flex size-10 shrink-0 items-center justify-center rounded-lg border bg-background"
                                >
                                    <component
                                        :is="feature.icon"
                                        class="size-5"
                                    />
                                </span>
                                <div>
                                    <h3 class="font-medium">
                                        {{ feature.title }}
                                    </h3>
                                    <p
                                        class="mt-1 text-sm text-pretty text-muted-foreground"
                                    >
                                        {{ feature.text }}
                                    </p>
                                    <div
                                        v-if="feature.statuses"
                                        class="mt-2.5 flex flex-wrap gap-1.5"
                                    >
                                        <Badge
                                            v-for="status in feature.statuses"
                                            :key="status.label"
                                            variant="outline"
                                            :class="[
                                                'border-transparent',
                                                status.class,
                                            ]"
                                        >
                                            {{ status.label }}
                                        </Badge>
                                    </div>
                                </div>
                            </li>
                        </ul>
                    </div>

                    <div class="rounded-2xl border bg-card p-5 shadow-sm">
                        <div class="flex items-baseline justify-between">
                            <h3 class="font-medium">
                                Estimated price per image
                            </h3>
                            <span class="text-xs text-muted-foreground">
                                USD
                            </span>
                        </div>
                        <table class="mt-4 w-full text-sm">
                            <thead>
                                <tr class="text-xs text-muted-foreground">
                                    <th class="pb-2 text-left font-medium">
                                        Size
                                    </th>
                                    <th
                                        v-for="quality in options.qualities"
                                        :key="quality.value"
                                        class="pb-2 text-right font-medium"
                                    >
                                        {{ quality.label }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="size in options.sizes"
                                    :key="size.value"
                                    class="border-t"
                                >
                                    <td class="py-3">
                                        <div class="font-medium">
                                            {{ size.label }}
                                        </div>
                                        <div
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{ size.description }}
                                        </div>
                                    </td>
                                    <td
                                        v-for="quality in options.qualities"
                                        :key="quality.value"
                                        class="py-3 text-right tabular-nums"
                                    >
                                        {{
                                            formatMoney(
                                                estimates[size.value]?.[
                                                    quality.value
                                                ]?.[0],
                                            )
                                        }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <p
                            class="mt-4 text-xs text-pretty text-muted-foreground"
                        >
                            Without reference images; each one adds to the
                            estimate, and the composer shows the exact figure as
                            you change options.
                        </p>
                    </div>
                </div>
            </section>
        </main>

        <footer class="border-t">
            <div
                class="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-2 px-4 py-8 text-xs text-muted-foreground sm:px-6"
            >
                <span
                    >{{ appName }} · image generation powered by
                    Pixelforge</span
                >
                <span
                    >Estimates are not final until a generation completes</span
                >
            </div>
        </footer>

        <AuthDialog
            v-model:open="auth.open"
            v-model:mode="auth.mode"
            :reason="auth.reason"
        />
    </div>
</template>
