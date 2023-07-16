<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class PropertyController extends Controller
{
    public function index()
    {
        $this->authorize('properties-manage');

        // will implement later
        return response()->json(['success'=>true]);
    }
}
