<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Post extends Model
{
    protected $table = 'post';
    protected $primaryKey = 'Post_id';
    public $timestamps = false;

    protected $fillable = [
        'UserDesignation_id',
        'Title',
        'Announcement',
        'Start_date',
        'End_date',
        'image',
    ];

    public function recipients()
    {
        return $this->hasMany(PostRecipient::class, 'Post_id', 'Post_id');
    }

    // ✅ Computed URL for the image regardless of how it's stored
    public function getImageUrlAttribute(): string
    {
        $img = trim((string) $this->image);

        // 1) Nothing set → placeholder
        if ($img === '') {
            return asset('img/placeholders/post-placeholder.svg');
        }

        // 2) Already a full URL
        if (Str::startsWith($img, ['http://', 'https://'])) {
            return $img;
        }

        // 3) storage/... (public disk)
        if (Str::startsWith($img, ['storage/', 'public/'])) {
            // normalize "public/foo.jpg" => "foo.jpg"
            $normalized = Str::replaceFirst('public/', '', $img);

            // If already "storage/..." assume it's public URL
            if (Str::startsWith($img, 'storage/')) {
                return asset($img);
            }

            // Otherwise attempt via Storage::url
            return Storage::url($normalized);
        }

        // 4) Plain filename -> /public/img/<filename>
        return asset('img/'.$img);
    }
}
