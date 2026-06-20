<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DashboardHomeController extends Controller
{
    public function index()
    {
        // Logic to retrieve and return a list of users
        return response()->json(['message' => 'Dashboard page']);
    }
}
