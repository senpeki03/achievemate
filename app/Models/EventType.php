<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventType extends Model
{
    use HasFactory;

    // Table name
    protected $table = 'event_types';

    // Primary key
    protected $primaryKey = 'EventType_id';

    // If you don't have created_at / updated_at
    public $timestamps = false;

    // Fillable columns
    protected $fillable = [
        'type_name',
    ];

    /**
     * If you later add EventType_id in events table,
     * you can use this relationship.
     */
    public function events()
    {
        return $this->hasMany(Event::class, 'EventType_id', 'EventType_id');
    }
}
