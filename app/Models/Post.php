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
        'Academic_year',
        'Semester',
        'Start_date',
        'End_date',
        'image',
    ];

    protected $casts = [
        'Start_date' => 'date',
        'End_date'   => 'date',
    ];
    // In Post model
    public function getFormattedAcademicYearAttribute()
    {
        if (!$this->Academic_year) {
            // Try to extract from Title or Announcement
            $text = $this->Title . ' ' . $this->Announcement;
            if (preg_match('/(\d{4}\s*[-–—]\s*\d{4})/', $text, $matches)) {
                return trim($matches[1]);
            }
            return null;
        }
        return $this->Academic_year;
    }

    public function getFormattedSemesterAttribute()
    {
        if (!$this->Semester) {
            // Try to extract from Title or Announcement
            $text = $this->Title . ' ' . $this->Announcement;
            if (preg_match('/(First|Second|Summer|1st|2nd|1|2)\s+Semester/i', $text, $matches)) {
                return $this->normalizeSemester(trim($matches[1]));
            }
            return null;
        }
        return $this->normalizeSemester($this->Semester);
    }

    private function normalizeSemester(string $semester): string
    {
        $semester = strtolower(trim($semester));
        
        $mapping = [
            'first' => 'First',
            '1st' => 'First', 
            '1' => 'First',
            'second' => 'Second',
            '2nd' => 'Second',
            '2' => 'Second',
            'summer' => 'Summer'
        ];
        
        return $mapping[$semester] ?? ucfirst($semester);
    }

    public function recipients()
    {
        return $this->hasMany(PostRecipient::class, 'Post_id', 'Post_id');
    }

    public function userDesignation()
    {
        return $this->belongsTo(UserDesignation::class, 'UserDesignation_id', 'UserDesignation_id');
    }

    public function getIsActiveAttribute()
    {
        $today = now()->startOfDay();

        $startsOk = !$this->Start_date || $this->Start_date->lte($today);
        $endsOk   = !$this->End_date   || $this->End_date->gte($today);

        return $startsOk && $endsOk;
    }

    public function getImageUrlAttribute(): string
    {
        $img = trim((string) $this->image);

        if ($img === '') {
            return asset('img/placeholders/post-placeholder.svg');
        }

        if (Str::startsWith($img, ['http://', 'https://'])) {
            return $img;
        }

        if (Str::startsWith($img, ['storage/', 'public/'])) {
            $normalized = Str::replaceFirst('public/', '', $img);

            if (Str::startsWith($img, 'storage/')) {
                return asset($img);
            }

            return Storage::url($normalized);
        }

        return asset('img/'.$img);
    }
}