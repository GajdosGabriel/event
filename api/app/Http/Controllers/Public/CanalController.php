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
        $canal = $this->canalRepository->publicShow($id);

        if (! $canal) {
            abort(404);
        }

        $data = $canal->toArray();
        // Kontaktovateľné len ak má kanál aktívneho majiteľa (self/admin
        // registrácia, overený e-mail) a návštevník ním nie je sám.
        $data['contactable'] = $canal->isContactableBy(auth('sanctum')->user());

        return response()->json($data);
    }
}
