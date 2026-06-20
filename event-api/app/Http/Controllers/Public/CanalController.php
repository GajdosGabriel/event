<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Repositories\Contracts\CanalRepository;
use Illuminate\Http\Request;

class CanalController extends Controller
{
    protected $canalRepository;

    public function __construct(CanalRepository $canalRepository)
    {
        $this->canalRepository = $canalRepository;
    }

    public function index()
    {
        return response()->json($this->canalRepository->publicIndex());
    }

    public function show($id)
    {
        return response()->json($this->canalRepository->publicShow($id));
    }
}
