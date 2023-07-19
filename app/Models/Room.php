<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Room extends Model
{
    use HasFactory;

    protected $fillable = [
        'apartment_id',
        'room_type_id',
        'name'
    ];

    public function room_type(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }

    public function beds(): HasMany
    {
        return $this->hasMany(Bed::class);
    }
}
