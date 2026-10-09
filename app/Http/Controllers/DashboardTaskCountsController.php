<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Dashboard\TaskCounts;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardTaskCountsController extends Controller
{
    public function __invoke(Request $request, TaskCounts $counts): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->canAccessPanel(filament()->getPanel('panel')), 403);

        return response()->json(['counts' => $counts->forUser($user)])
            ->header('Cache-Control', 'no-store');
    }
}
