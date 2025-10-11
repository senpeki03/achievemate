<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GraduationRequirementType extends Model
{
    protected $fillable = ['code','name','is_file_required','sort'];
    public $timestamps = true;
}
