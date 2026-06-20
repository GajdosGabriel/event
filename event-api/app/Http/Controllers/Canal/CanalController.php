<?php

namespace App\Http\Controllers\Canal;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class CanalController extends Controller
{
    public function index()
    {
        // Logic to retrieve and return a list of users
        return response()->json(['message' => 'List of users']);
    }
}
