<?php

namespace App\Services\Media;

use App\Models\Media;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * Reads a user's generations for the history page.
 */
class MediaHistory
{
    private const int PER_PAGE = 12;

    /** How many results the landing page's slider holds. */
    private const int RECENT = 12;

    /**
     * @return LengthAwarePaginator<int, Media>
     */
    public function forUser(User $user): LengthAwarePaginator
    {
        return Media::query()
            ->where('user_id', $user->id)
            ->latest()
            ->paginate(self::PER_PAGE)
            ->withQueryString();
    }

    /**
     * The newest few, for showing results inline without leaving the page.
     *
     * @return Collection<int, Media>
     */
    public function recentForUser(User $user): Collection
    {
        return Media::query()
            ->where('user_id', $user->id)
            ->latest()
            ->limit(self::RECENT)
            ->get();
    }
}
