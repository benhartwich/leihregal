<?php

namespace App\Http\Controllers;

use App\Models\Media;
use App\Models\MediaBookmark;

class BookmarkController extends Controller
{
    public function toggle(Media $media)
    {
        $userId = auth()->id();
        $existing = MediaBookmark::where('user_id', $userId)->where('media_id', $media->id)->first();

        if ($existing) {
            $existing->delete();
            $bookmarked = false;
        } else {
            MediaBookmark::create(['user_id' => $userId, 'media_id' => $media->id]);
            $bookmarked = true;
        }

        return response()->json(['bookmarked' => $bookmarked]);
    }
}
