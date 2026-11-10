<?php

namespace App\Http\Controllers;

use App\Services\AnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class ActivityController extends Controller
{
    public function __construct(private readonly AnalyticsService $analytics) {}

    public function recent(): JsonResponse
    {
        return response()->json([
            'actividad' => $this->analytics->recentActivity(20),
        ]);
    }
}
