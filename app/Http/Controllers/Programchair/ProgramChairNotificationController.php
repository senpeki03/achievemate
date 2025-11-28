<?php

namespace App\Http\Controllers\ProgramChair;

use App\Http\Controllers\Controller;
use App\Models\ApplicationRecipient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProgramChairNotificationController extends Controller
{
    public function index()
    {
        $login = Auth::user(); // Login model

        $designation = $login->userDesignation;  // UserDesignation
        if (!$designation) {
            abort(403, 'No designation found for this user.');
        }

        // This is the UserManage primary key used by application_recipient.User_id
        $userManageId = $designation->User_id;

        $notifications = ApplicationRecipient::with([
                'application.student',
            ])
            ->where('User_id', $userManageId)
            ->orderByDesc('application_recipient_id') // ✅ double t
            ->get();

        $unreadCount = $notifications->where('is_read', 0)->count();

        return view('programchair.pgnotification', [
            'notifications' => $notifications,
            'unreadCount'   => $unreadCount,
        ]);
    }

    public function markAsRead(Request $request)
    {
        $login = Auth::user();
        $designation = $login->userDesignation;

        if (!$designation) {
            return response()->json(['status' => 'error', 'message' => 'No designation'], 403);
        }

        $userManageId = $designation->User_id;

        $id = $request->input('id');

        $rec = ApplicationRecipient::where('application_recipient_id', $id) // ✅ double t
            ->where('User_id', $userManageId)
            ->first();

        if ($rec) {
            $rec->is_read = 1;
            $rec->save();
        }

        $unreadCount = ApplicationRecipient::where('User_id', $userManageId)
            ->where('is_read', 0)
            ->count();

        return response()->json([
            'status'      => 'ok',
            'unreadCount' => $unreadCount,
        ]);
    }
}
