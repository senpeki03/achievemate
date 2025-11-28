<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApplicationRecipient extends Model
{
    protected $table = 'application_recipient';
    protected $primaryKey = 'application_recipient_id';
    public $timestamps = false;

    protected $fillable = [
        'Application_id',
        'User_id',
        'is_read',
    ];

    public function application()
    {
        return $this->belongsTo(Application::class, 'Application_id');
    }

    public function user()
    {
        return $this->belongsTo(UserManage::class, 'User_id');
    }
}
