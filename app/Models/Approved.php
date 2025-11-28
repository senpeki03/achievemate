<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Approved extends Model
{
    protected $table = 'approved';
    protected $primaryKey = 'Approved_id';
    public $timestamps = false;
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'Student_id',
        'User_id',
        'Date',
        'Academic_year',
    ];

    protected $casts = [
        'Student_id' => 'integer',
        'User_id'  => 'integer',
        'Date' => 'date', // Add this line
    ];

}