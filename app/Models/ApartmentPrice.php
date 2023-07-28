<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApartmentPrice extends Model
{
    use HasFactory;

    protected $fillable = [
        'apartment_id',
        'start_date',
        'end_date',
        'price'
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date'
    ];

    public function scopeValidForRange($query, array $range = [])
    {
        return $query->where(function($query) use ($range){
            return $query
                    // Data yang benar-benar berada dalam rentang tanggal yang ditentukan oleh larik $range.
                    ->where(function($query) use ($range){
                        $query->where('start_date', '>=', reset($range))->where('end_date', '<=', end($range));
                    })
                    // Data yang memiliki bagian dari rentang tanggal yang ditentukan dalam larik $range.
                    ->orWhere(function($query) use ($range){
                        $query->whereBetween('start_date', $range)->orWhereBetween('end_date', $range);
                    })
                    // Data yang secara parsial melampaui rentang tanggal yang ditentukan dalam larik $range.
                    ->orWhere(function($query) use ($range){
                        $query->where('start_date', '<=', reset($range))->where('end_date', '>=', end($range));
                    });
        });
    }
}
