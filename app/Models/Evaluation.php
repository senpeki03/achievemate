<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Evaluation extends Model
{
    protected $table = 'evaluation';
    protected $primaryKey = 'Evaluation_id';
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