<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Campus extends Model
{
    protected $table = 'campus';
    protected $primaryKey = 'Campus_id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'Campus_name',
        'Location',
        'Created_at'
    ];
    
}
