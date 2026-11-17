export type MediaStatus = 'queued' | 'processing' | 'succeeded' | 'failed';

export type MediaItem = {
    id: string;
    status: MediaStatus;
    statusLabel: string;
    prompt: string;
    size: string;
    quality: string;
    /** Decimal strings: formatted for display, never used in arithmetic. */
    estimatedPrice: string;
    price: string | null;
    imageUrl: string | null;
    isStored: boolean;
    errorMessage: string | null;
    createdAt: string | null;
    completedAt: string | null;
};

export type Paginated<T> = {
    data: T[];
    meta?: { current_page: number; last_page: number; total: number };
    links?: { url: string | null; label: string; active: boolean }[];
};
