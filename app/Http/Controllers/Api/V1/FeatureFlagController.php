<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\FeatureFlagService;
use Illuminate\Http\JsonResponse;

class FeatureFlagController extends Controller
{
    public function __construct(
        private FeatureFlagService $flags,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json($this->flags->allFlags());
    }
}
