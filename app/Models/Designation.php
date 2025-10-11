<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Designation extends Model
{
    protected $table = 'designation';
    protected $primaryKey = 'Designation_id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'Designation_name',
        'Access'
    ];
    
    public function userDesignations()
{
    return $this->hasMany(\App\Models\UserDesignation::class, 'Designation_id', 'Designation_id');
}

}
