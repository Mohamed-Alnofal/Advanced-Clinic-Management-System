<?php

namespace Modules\Attachments\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Attachments\Services\AttachmentService;
use Modules\Core\Http\Controllers\Controller;

class AttachmentsController extends Controller
{
    public function __construct(
        private AttachmentService $service
    ) {}

    public function store(Request $request)
    {
        $request->validate([
            'medical_visit_id' => 'required|exists:medical_visits,id',
            'file' => 'required|file|max:10240',
            'type' => 'required|in:xray,analysis,report,other',
        ]);

        return response()->json([
            'data' => $this->service->store($request)
        ]);
    }

    public function download($id)
    {
        return $this->service->download($id);
    }

    public function destroy($id)
    {
        $this->service->destroy($id);

        return response()->json([
            'message' => 'Deleted Successfully'
        ]);
    }
}