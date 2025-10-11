<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;

class Logs extends Model
{
    protected $table = 'system_logs';
    protected $primaryKey = 'systemId'; // or 'id' if you change primary key
    public $timestamps = false; // your table uses manual timestamps

    protected $fillable = [
        'userId', 'action', 'ip_address', 'created_at' // matches your DB exactly
    ];

    public static function record($userId, $action)
    {
        self::create([
            'userId'    => $userId,
            'action'    => $action,
            'ip_address'=> Request::ip(),
            'created_at' => now(), // must match your DB column
        ]);
    }
}

