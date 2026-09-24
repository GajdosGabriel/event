<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Resources\ResourceMerger;
use Illuminate\Http\Request;

class ResourceMergeController extends Controller
{
    public function __invoke(Request $request, ResourceMerger $merger)
    {
        abort_unless($request->user()->hasRole('super-admin'), 403);
        $input = $request->validate(['target_id' => ['required', 'integer', 'min:1']]);
        $merger->merge($request->route('resource'), (int) $request->route('id'), (int) $input['target_id'], (int) $request->user()->id);
        return response()->json(['data' => ['id' => (int) $input['target_id']]]);
    }
}
