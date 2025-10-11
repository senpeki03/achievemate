<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PostRecipient extends Model
{
    protected $table = 'post_recipient';
    protected $primaryKey = 'PostRecipient_id';
    public $timestamps = false;

    protected $fillable = [
        'Post_id',
        'Student_id',
        'is_read',
    ];

        // Define the relationship between PostRecipient and Post
    public function post()
    {
        return $this->belongsTo(Post::class, 'Post_id');
    }

     public function student()
    {
        return $this->belongsTo(StudentManage::class, 'Student_id');
    }
}

