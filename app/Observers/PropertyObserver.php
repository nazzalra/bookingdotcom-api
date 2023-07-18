<?php

namespace App\Observers;

use App\Models\Property;
use Illuminate\Support\Facades\Http;

class PropertyObserver
{
    public function creating(Property $property)
    {
        if(auth()->check()){
            $property->owner_id = auth()->id();
        }

        if(is_null($property->lat) && is_null($property->long))
        {
            $fullAddress = $property->address_street . ', '
                . $property->address_postcode . ', '
                . $property->city->name . ', '
                . $property->city->country->name;
            $result = Http::retry(2,1000)->get('https://geocode.maps.co/search',['q'=>$fullAddress])->collect();
            if($result->isNotEmpty()){
                $property->lat = $result[0]['lat'];
                $property->long = $result[0]['lon'];
            }
        }
    }
}
