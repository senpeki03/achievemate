<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class UploadDl extends Model
{
    protected $table = 'upload_dl';
    protected $primaryKey = 'upload_id';
    public $timestamps = false;

    protected $fillable = [
        'userId',
        'Post_id',
        'firstname',
        'middlename',
        'lastname',
        'Year',
        'department',
        'program',
        'track',
        'contact',
        'courses',
        'GWA',
        'rank',
        'file_name',
        'file_data',
        'status'
    ];
}
