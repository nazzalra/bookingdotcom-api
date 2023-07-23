<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\PropertySearchResource;
use App\Models\Property;
use Illuminate\Http\Request;

class PropertyController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Property $property, Request $request)
    {
        $property->load('apartments.facilities');

        if($request->capacity_adults && $request->capacity_children){
            $property->load(['apartments' => function($query) use ($request){
                $query->where('capacity_adults', '>=', $request->capacity_adults)
                    ->where('capacity_children', '>=', $request->capacity_children)
                    ->orderBy('capacity_adults')
                    ->orderBy('capacity_children');
            }, 'apartments.facilities']);
        }

        return new PropertySearchResource($property);
    }
}
