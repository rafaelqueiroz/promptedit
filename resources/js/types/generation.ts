export type ComposerOption = {
    value: string;
    label: string;
    description: string;
};

export type GenerationOptions = {
    sizes: ComposerOption[];
    qualities: ComposerOption[];
};

/** [size][quality][referenceImageCount] → user price as a USD decimal string. */
export type EstimateTable = Record<string, Record<string, string[]>>;

export type GenerationDraft = {
    prompt: string;
    size: string;
    quality: string;
    referenceImageUrls: string[];
};
