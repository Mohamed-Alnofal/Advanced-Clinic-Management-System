<?php

namespace Modules\Admin\Http\Controllers;

use Illuminate\Http\Request;
use Modules\Admin\Services\FinancialsService;
use Modules\Core\Http\Controllers\Controller;

class FinancialsController extends Controller
{
    public function __construct(
        private FinancialsService $financials,
    ) {}

    public function summary()
    {
        return response()->json(['data' => $this->financials->summary()]);
    }

    public function trend(Request $request)
    {
        $months = min(24, max(3, (int) $request->query('months', 6)));

        return response()->json(['data' => $this->financials->trend($months)]);
    }

    public function byProvider()
    {
        return response()->json(['data' => $this->financials->byProvider()]);
    }

    public function transactions(Request $request)
    {
        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(50, max(5, (int) $request->query('per_page', 10)));

        return response()->json($this->financials->transactions($page, $perPage));
    }
}
