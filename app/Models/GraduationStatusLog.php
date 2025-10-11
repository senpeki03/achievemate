<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GraduationStatusLog extends Model
{
    protected $fillable = ['application_id','actor_id','from_status','to_status','note'];
    public function application(){ return $this->belongsTo(GraduationApplication::class); }
    public function actor(){ return $this->belongsTo(User::class,'actor_id'); }
}
