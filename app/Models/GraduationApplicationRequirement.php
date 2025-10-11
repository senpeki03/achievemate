<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GraduationApplicationRequirement extends Model
{
    protected $table = 'graduation_application_requirements';

    // PK is not "id"
    protected $primaryKey = 'GradReq_id';
    public $incrementing  = true;
    protected $keyType     = 'int';

    protected $fillable = [
        'application_id',     // FK -> graduation_applications.Graduation_id
        'type_id',
        'file_name',
        'file_path',
        'mime_type',
        'size_bytes',
        'status',             // pending|approved|rejected|returned
        'notes',
        'checked_by',
        'checked_at',
    ];

    protected $casts = [
        'checked_at' => 'datetime',
        'size_bytes' => 'integer',
    ];

    /* ----------------- relationships ----------------- */

    // Parent application (note: parent PK is Graduation_id)
    public function application()
    {
        return $this->belongsTo(
            GraduationApplication::class,
            'Graduation_id',   // this model's FK
            'Graduation_id'    // parent key
        );
    }

    // Optional: if you later add a types table
    public function type()
    {
        return $this->belongsTo(RequirementType::class, 'type_id');
    }

    public function checker()
    {
        return $this->belongsTo(User::class, 'checked_by');
    }
}
