<?php

namespace App\Http\Controllers;

use App\Models\Planning;
use App\Models\User;
use App\Services\MyPlanningShowDataService;
use App\Services\PlanningLocationTimerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class MyPlanningController extends Controller
{
    public function __construct(
        private MyPlanningShowDataService $myPlanningShowDataService,
        private PlanningLocationTimerService $planningLocationTimerService
    ) {}

    public function show(?Planning $planning = null): View
    {
        /** @var User $user */
        $user = Auth::user();

        $planning = $this->myPlanningShowDataService->resolvePlanningForUser($planning, $user);

        if (! $planning) {
            return view($this->viewName('my-planning.show-empty'));
        }

        $viewData = $this->myPlanningShowDataService->buildShowData($planning);

        return view($this->viewName('my-planning.show'), [
            'planning' => $planning,
            ...$viewData,
        ]);
    }

    public function restartLocationTimer(Request $request, Planning $planning, int|string $locationId): JsonResponse
    {
        return $this->planningLocationTimerService->restartLocationTimer($request, $planning, $locationId);
    }
}
