<script setup lang="ts">
import { CornerDownLeft, ImagePlus, Sparkles, X } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { formatMoney } from '@/lib/money';
import type {
    EstimateTable,
    GenerationDraft,
    GenerationOptions,
} from '@/types';

const props = withDefaults(
    defineProps<{
        options: GenerationOptions;
        estimates: EstimateTable;
        maxReferenceImages: number;
        submitLabel?: string;
        processing?: boolean;
    }>(),
    { submitLabel: 'Generate', processing: false },
);

const draft = defineModel<GenerationDraft>({ required: true });

// Bound rather than a static attribute: vue-tsc 3.3 mangles `//` inside
// static string attributes on components, which breaks type-checking the loop.
const referenceImagePlaceholder = 'https://example.com/reference.jpg';

const emit = defineEmits<{
    submit: [draft: GenerationDraft];
}>();

const referenceImageCount = computed(
    () =>
        draft.value.referenceImageUrls.filter((url) => url.trim() !== '')
            .length,
);

// Prices are precomputed on the server for every size × quality × reference
// image count, so the number shown is exactly the one that would be stored.
const estimate = computed(
    () =>
        props.estimates[draft.value.size]?.[draft.value.quality]?.[
            referenceImageCount.value
        ] ?? null,
);

const selectedSize = computed(() =>
    props.options.sizes.find((size) => size.value === draft.value.size),
);

const selectedQuality = computed(() =>
    props.options.qualities.find(
        (quality) => quality.value === draft.value.quality,
    ),
);

const canSubmit = computed(
    () =>
        draft.value.prompt.trim().length >= 3 &&
        estimate.value !== null &&
        !props.processing,
);

const canAddReferenceImage = computed(
    () => draft.value.referenceImageUrls.length < props.maxReferenceImages,
);

function addReferenceImage() {
    if (canAddReferenceImage.value) {
        draft.value.referenceImageUrls.push('');
    }
}

function removeReferenceImage(index: number) {
    draft.value.referenceImageUrls.splice(index, 1);
}

function submit() {
    if (canSubmit.value) {
        emit('submit', draft.value);
    }
}
</script>

<template>
    <form
        class="overflow-hidden rounded-2xl border bg-card shadow-sm transition-shadow focus-within:shadow-md focus-within:ring-2 focus-within:ring-ring/30"
        @submit.prevent="submit"
    >
        <label for="prompt" class="sr-only">Prompt</label>
        <textarea
            id="prompt"
            v-model="draft.prompt"
            name="prompt"
            rows="3"
            maxlength="1000"
            placeholder="Describe the image you want…"
            class="block [field-sizing:content] max-h-72 min-h-28 w-full resize-none bg-transparent px-5 pt-5 pb-3 text-base leading-relaxed outline-none placeholder:text-muted-foreground"
            @keydown.ctrl.enter.prevent="submit"
            @keydown.meta.enter.prevent="submit"
        />

        <div v-if="draft.referenceImageUrls.length" class="space-y-2 px-5 pb-4">
            <p class="text-xs font-medium text-muted-foreground">
                Reference images
            </p>
            <div
                v-for="index in draft.referenceImageUrls.keys()"
                :key="index"
                class="flex items-center gap-2"
            >
                <Input
                    v-model="draft.referenceImageUrls[index]"
                    type="url"
                    inputmode="url"
                    :placeholder="referenceImagePlaceholder"
                    class="h-8 text-sm"
                />
                <Button
                    type="button"
                    variant="ghost"
                    size="icon-sm"
                    aria-label="Remove reference image"
                    @click="removeReferenceImage(index)"
                >
                    <X />
                </Button>
            </div>
        </div>

        <div
            class="flex flex-wrap items-center gap-2 border-t bg-muted/40 px-3 py-3"
        >
            <Select v-model="draft.size">
                <SelectTrigger
                    size="sm"
                    aria-label="Size"
                    class="bg-background"
                >
                    <SelectValue>{{ selectedSize?.label }}</SelectValue>
                </SelectTrigger>
                <SelectContent>
                    <SelectItem
                        v-for="size in options.sizes"
                        :key="size.value"
                        :value="size.value"
                    >
                        {{ size.label }}
                        <span class="text-muted-foreground">
                            {{ size.description }}
                        </span>
                    </SelectItem>
                </SelectContent>
            </Select>

            <Select v-model="draft.quality">
                <SelectTrigger
                    size="sm"
                    aria-label="Quality"
                    class="bg-background"
                >
                    <SelectValue>{{ selectedQuality?.label }}</SelectValue>
                </SelectTrigger>
                <SelectContent>
                    <SelectItem
                        v-for="quality in options.qualities"
                        :key="quality.value"
                        :value="quality.value"
                    >
                        {{ quality.label }}
                        <span class="text-muted-foreground">
                            {{ quality.description }}
                        </span>
                    </SelectItem>
                </SelectContent>
            </Select>

            <Button
                type="button"
                variant="ghost"
                size="sm"
                :disabled="!canAddReferenceImage"
                @click="addReferenceImage"
            >
                <ImagePlus />
                Reference image
            </Button>

            <div class="ml-auto flex items-center gap-3">
                <div class="text-right leading-tight">
                    <div
                        class="text-sm font-semibold tabular-nums"
                        :title="estimate ? `$${estimate} estimated` : undefined"
                        data-test="estimate"
                    >
                        ≈ {{ formatMoney(estimate) }}
                    </div>
                    <div class="text-[11px] text-muted-foreground">
                        estimate · exact cost when it finishes
                    </div>
                </div>

                <Button
                    type="submit"
                    size="lg"
                    class="bg-brand text-brand-foreground hover:bg-brand/90"
                    :disabled="!canSubmit"
                    data-test="generate-button"
                >
                    <Sparkles />
                    {{ submitLabel }}
                    <kbd
                        class="hidden items-center gap-0.5 rounded border border-brand-foreground/20 px-1.5 py-0.5 text-[10px] font-medium sm:inline-flex"
                    >
                        Ctrl
                        <CornerDownLeft class="size-3" />
                    </kbd>
                </Button>
            </div>
        </div>
    </form>
</template>
