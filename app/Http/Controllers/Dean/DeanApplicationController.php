<?php

// app/Http/Controllers/Dean/DeanApplicationController.php
namespace App\Http\Controllers\Dean;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use App\Models\Application;
use App\Services\AwardService;
use App\Models\StudentManage;
use App\Models\StudentNotification;

class DeanApplicationController extends Controller
{
    public function __construct(private AwardService $awardService) {}

    public function updateStatus(Request $request)
    {
        $request->validate([
            'id'     => 'required|integer',
            'status' => 'required|string|in:Approved,Verified,Pending,Rejected',
        ]);

        $app = Application::findOrFail($request->integer('id'));
        $app->Status = $request->string('status');
        $app->save();

        if ($app->Status === 'Approved') {
            $this->awardService->onApproved($app);
        }

        return response()->json(['ok' => true]);
    }

    protected function afterApproved(Application $app): void
    {
        try {
            // (optional) handa ka ng paths (badge/cert), pwedeng simple muna
            $badgePath = 'assets/badges/deans_lister.png'; // public asset mo
            $certificatePath = null; // kung meron kang gina-generate na PDF, ilagay mo path

            // ✅ CREATE the StudentNotification row
            $notif = StudentNotification::create([
                'Student_id' => $app->Student_id,            // ⚠️ case must match DB column
                'type'       => 'deans_lister_award',
                'title'      => "Dean’s Lister Award",
                'message'    => "You qualified for Dean’s Lister. Tap to claim your badge and certificate.",
                'data'       => [
                    'application_id'   => $app->Application_id,
                    'badge_path'       => $badgePath,
                    'certificate_path' => $certificatePath,
                ],
                'claim_token' => Str::uuid()->toString(),
                'claimable'   => true,
                'is_read'     => false,
            ]);

            Log::info('Created StudentNotification', ['id' => $notif->getKey(), 'student' => $app->Student_id]);
        } catch (\Throwable $e) {
            Log::error('afterApproved failed', ['err' => $e->getMessage(), 'app' => $app->Application_id]);
        }
    }
}
