<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
     public function index()
    {
        // Logic to retrieve and return a list of users
        return response()->json(['message' => 'Admin Dashboard page']);
    }
}
