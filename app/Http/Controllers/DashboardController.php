<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Services\AnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(private readonly AnalyticsService $analytics) {}

    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'period' => 'sometimes|integer|in:7,30,90,365',
            'categoria' => 'sometimes|integer|exists:categories,id',
            'granularidad' => 'sometimes|string|in:dia,semana,mes',
        ]);

        $days = (int) ($validated['period'] ?? 30);
        $categoryId = $validated['categoria'] ?? null;
        $granularity = $validated['granularidad'] ?? 'dia';
        $granularityKey = ['dia' => 'day', 'semana' => 'week', 'mes' => 'month'][$granularity];

        $from = now()->subDays($days)->startOfDay();

        return Inertia::render('dashboard', [
            'kpis' => $this->analytics->kpis($from, $categoryId),
            'serie' => $this->analytics->series($from, $granularityKey, $categoryId),
            'top' => $this->analytics->topProducts($from, $categoryId),
            'mapa' => $this->analytics->salesMap($from, $categoryId),
            'alertas' => $this->analytics->lowStock(),
            'actividad' => $this->analytics->recentActivity(),
            'categorias' => Category::query()->select('id', 'name')->orderBy('name')->get(),
            'categoriaShare' => $this->analytics->categoryShare($from, $categoryId),
            'topCiudades' => $this->analytics->topCities($from, $categoryId),
            'filters' => [
                'period' => $days,
                'granularidad' => $granularity,
                'categoria' => $categoryId,
            ],
            'updatedAt' => Carbon::now()->toIso8601String(),
        ]);
    }
}
