import type { GenerationDraft } from '@/types';

const key = 'promptedit.generation-draft';

// Keeps a guest's prompt across the sign-in round trip. Per browser, best effort.
export function saveDraft(draft: GenerationDraft): void {
    try {
        sessionStorage.setItem(key, JSON.stringify(draft));
    } catch {
        // Private mode or blocked storage: the draft is simply not kept.
    }
}

export function takeDraft(): GenerationDraft | null {
    try {
        const raw = sessionStorage.getItem(key);
        sessionStorage.removeItem(key);

        return raw ? (JSON.parse(raw) as GenerationDraft) : null;
    } catch {
        return null;
    }
}
