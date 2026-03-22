<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Guardian extends Model
{
    protected $fillable = [
        'minor_id',
        'guardian_id',
    ];

    // Relations
    public function minor()
    {
        return $this->belongsTo(User::class, 'minor_id');
    }

    public function guardian()
    {
        return $this->belongsTo(User::class, 'guardian_id');
    }
}
