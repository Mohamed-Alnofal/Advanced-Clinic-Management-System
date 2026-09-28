<?php

namespace Modules\Admin\Http\Controllers;
use Modules\Core\Http\Controllers\Controller;

use Modules\Core\Services\AuditService;

class AuditLogController extends Controller
{
    protected $auditService;

    public function __construct(AuditService $auditService)
    {
        $this->auditService = $auditService;
    }

    public function index()
    {
        $logs = $this->auditService->getAllLogs();

        return response()->json([
            'status' => true,
            'data' => $logs
        ]);
    }

    public function statistics()
    {
        $statistics = $this->auditService->getStatistics();

        return response()->json([
            'status' => true,
            'data' => $statistics
        ]);
    }
}