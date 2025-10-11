<?php

namespace App\Http\Controllers\Dean;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

// ✅ use the real model name you have
use App\Models\Application;
// (optional, if you’ll email) use App\Models\Login;
// (optional, if you’ll create dean records) use App\Models\DeanHonorList;

class ApplicationNotifController extends Controller
{
    // Single update (Approve/Verified/etc.)
    public function updateStatus(Request $request)
    {
        $data = $request->validate([
            'id'     => ['required','integer'],
            'status' => ['required', Rule::in(['Pending','Verified','Approved'])],
        ]);

        // Because Application model has $primaryKey = 'Application_id',
        // findOrFail() will correctly use that PK.
        $app = Application::findOrFail($data['id']);

        // Your column is "Status" (capital S). Set that exact attribute.
        $app->Status = $data['status'];
        $app->save();

        return response()->json(['ok' => true]);
    }

    // Bulk approve selected application IDs
    public function bulkApprove(Request $request)
    {
        $data = $request->validate([
            'application_ids'   => ['required','array','min:1'],
            'application_ids.*' => ['integer'],
        ]);

        DB::beginTransaction();
        try {
            // Pull apps; add relations if you need (e.g., ->with('student'))
            $apps = Application::query()
                ->whereIn('Application_id', $data['application_ids'])
                ->lockForUpdate()
                ->get();

            if ($apps->isEmpty()) {
                return response()->json(['message' => 'No matching applications.'], 422);
            }

            foreach ($apps as $app) {
                // 1) Update status
                $app->Status = 'Approved';
                $app->save();

                // 2) (Optional) Send email — only if you wire your email lookup
                // $email = optional($app->student?->login)->username; // if username = email
                // if ($email) { Mail::to($email)->queue(new DeanApprovedMail($app)); }

                // 3) (Optional) Create Dean’s List record so it shows in portfolio
                // DeanHonorList::firstOrCreate([...]);
            }

            DB::commit();
            return response()->json(['message' => 'Selected students approved.']);
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);
            return response()->json(['message' => 'Bulk approve failed: '.$e->getMessage()], 500);
        }
    }
}
