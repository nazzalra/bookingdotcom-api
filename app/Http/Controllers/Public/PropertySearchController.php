<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Geoobject;
use App\Models\Property;
use Illuminate\Http\Request;

class PropertySearchController extends Controller
{
    public function __invoke(Request $request)
    {
        return Property::with('city')
            ->when($request->city, function($query) use ($request){
                $query->where('city_id',$request->city);
            })
            ->when($request->country, function($query) use ($request){
                $query->whereHas('city', fn ($query) => $query->where('country_id', $request->country));
            })
            ->when($request->geoobject, function($query) use ($request){
                $geoobject = Geoobject::find($request->geoobject);
                if($geoobject){
                    $condition = "(
                        6371 * acos(
                            cos(radians(" . $geoobject->lat . "))
                            * cos(radians(`lat`))
                            * cos(radians(`long`) - radians(" . $geoobject->long . "))
                            + sin(radians(" . $geoobject->lat . ")) * sin(radians(`lat`))
                        ) < 10
                    )";
                    $query->whereRaw($condition);
                }
            })
            ->when($request->capacity_adults && $request->capacity_children, function($query) use ($request){
                $query->withWhereHas('apartments', function($query) use ($request){
                    $query->where('capacity_adults', '>=', $request->capacity_adults)
                            ->where('capacity_children', '>=', $request->capacity_children);
                });
            })
            ->get();
    }
}