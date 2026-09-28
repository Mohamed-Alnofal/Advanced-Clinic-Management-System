<?php

namespace Modules\Disputes\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;

use Modules\Disputes\Models\Dispute;

class AdminDisputeController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | عرض جميع الشكاوى
    |--------------------------------------------------------------------------
    */
    public function index(Request $request)
    {
        $status = $request->status;

        $priority = $request->priority;

        $page = $request->page ?? 1;

        $version = (int) Cache::get('admin_disputes_cache_version', 0);

        $cacheKey = "admin_disputes_v{$version}_{$status}_{$priority}_page_{$page}";

        $disputes = Cache::remember(

            $cacheKey,

            now()->addMinutes(5),

            function () use ($status, $priority) {

                return Dispute::with([

                    'complainant:id,name,email',

                    'defendant:id,name,email',

                    'handler:id,name,email'

                ])

                ->when($status, function ($query) use ($status) {

                    $query->where('status', $status);
                })

                ->when($priority, function ($query) use ($priority) {

                    $query->where('priority', $priority);
                })

                ->latest()

                ->paginate(15);
            }
        );

        return response()->json($disputes);
    }

    /*
    |--------------------------------------------------------------------------
    | عرض شكوى واحدة
    |--------------------------------------------------------------------------
    */
    public function show($id)
    {
        $cacheKey = "dispute_{$id}";

        $dispute = Cache::remember(

            $cacheKey,

            now()->addMinutes(10),

            function () use ($id) {

                return Dispute::with([

                    'complainant',

                    'defendant',

                    'handler'

                ])->findOrFail($id);
            }
        );

        return response()->json($dispute);
    }

    /*
    |--------------------------------------------------------------------------
    | حل النزاع
    |--------------------------------------------------------------------------
    */
    public function resolve($id)
    {
        $dispute = Dispute::findOrFail($id);

        $dispute->update([

            'status' => 'resolved',

            'resolved_at' => now(),

            'handled_by' => Auth::id(),
        ]);

        /*
        |--------------------------------------------------------------------------
        | تنظيف Cache
        |--------------------------------------------------------------------------
        */
        Cache::forget("dispute_{$id}");
        $this->forgetDisputeListCaches();

        return response()->json([

            'message' => 'Dispute resolved successfully',

            'dispute' => $dispute
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | تصعيد النزاع
    |--------------------------------------------------------------------------
    */
    public function escalate($id)
    {
        $dispute = Dispute::findOrFail($id);

        $dispute->update([

            'status' => 'escalated',

            'is_escalated' => true,

            'handled_by' => Auth::id(),
        ]);

        /*
        |--------------------------------------------------------------------------
        | تنظيف Cache
        |--------------------------------------------------------------------------
        */
        Cache::forget("dispute_{$id}");
        $this->forgetDisputeListCaches();

        return response()->json([

            'message' => 'Dispute escalated successfully',

            'dispute' => $dispute
        ]);
    }

    public function updateStatus(Request $request, $id)
{
    $request->validate([
        'status' => 'required|in:open,in_progress,resolved,escalated,closed'
    ]);

    $dispute = Dispute::findOrFail($id);

    $dispute->update([
        'status' => $request->status,
        'handled_by' => auth()->id(),
    ]);

    Cache::forget("dispute_{$id}");
    $this->forgetDisputeListCaches();

    return response()->json([
        'message' => 'Status updated successfully',
        'dispute' => $dispute
    ]);
}

    private function forgetDisputeListCaches(): void
    {
        Cache::forever(
            'admin_disputes_cache_version',
            ((int) Cache::get('admin_disputes_cache_version', 0)) + 1
        );
    }
}