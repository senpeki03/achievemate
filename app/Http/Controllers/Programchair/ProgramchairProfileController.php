<?php

namespace App\Http\Controllers\ProgramChair;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;

use App\Models\UserManage;
use App\Models\UserDesignation;
use App\Models\UserProfile;

class ProgramchairProfileController extends Controller
{
    /** Resolve User_id via Login_id from user_designation */
    protected function resolveUserId(): ?int
    {
        // Step 1: Get Login_id from session (or auth)
        $loginId = session('Login_id') ?? auth()->id();

        if (!$loginId) {
            Log::warning('No Login_id found in session/auth');
            return null;
        }

        // Step 2: Find matching User_id from user_designation
        $designation = UserDesignation::where('Login_id', $loginId)
            ->orderByDesc('UserDesignation_id')
            ->first();

        if (!$designation) {
            Log::warning('No mapping found in user_designation for Login_id', ['login_id' => $loginId]);
            return null;
        }

        return (int) $designation->User_id;
    }

    /** Show profile page */
    public function show(Request $request)
    {
        $userId = $this->resolveUserId();
        $user = $userId ? UserManage::find($userId) : null;

        $photoUrl = route('programchair.profile.photo', ['_v' => time()]);

        return view('programchair.profile', [
            'fullName' => trim(($user->First_name ?? '') . ' ' . ($user->Middle_name ?? '') . ' ' . ($user->Last_name ?? '')) ?: 'Program Chair',
            'college' => 'College of Informatics and Computing Sciences',
            'program' => 'Bachelor of Science in Information Technology',
            'srCode' => $userId ?? '—',
            'contactNumber' => '',
            'homeAddress' => '',
            'placeOfBirth' => '',
            'dobYmd' => '',
            'ayLabel' => date('Y') . '-' . (date('Y') + 1),
            'photoUrl' => $photoUrl,
        ]);
    }

    // ProgramchairProfile::photo()
    public function photo()
    {
        $userId = $this->resolveUserId();
        $row = $userId ? \App\Models\UserProfile::where('User_id', $userId)->first() : null;

        if ($row && !empty($row->Profile)) {
            $raw  = $row->Profile;
            $blob = ($dec = base64_decode($raw, true)) !== false ? $dec : $raw;
            $info = @getimagesizefromstring($blob);
            $mime = $info['mime'] ?? 'image/jpeg';
            return response($blob, 200)->header('Content-Type', $mime);
        }

        // Fallback to your public placeholder file
        $path = public_path('img/profile-placeholder.png');
        return response()->file($path, ['Content-Type' => 'image/png']);
    }


    /** Save new profile photo */
    public function save(Request $request)
    {
        $userId = $this->resolveUserId();
        if (!$userId) {
            return response()->json(['ok' => false, 'message' => 'Cannot resolve User_id from Login_id.'], 422);
        }

        if (!UserManage::where('User_id', $userId)->exists()) {
            return response()->json(['ok' => false, 'message' => "No matching parent in user_manage for User_id={$userId}"], 422);
        }

        if (!$request->hasFile('photo')) {
            return response()->json(['ok' => false, 'message' => 'No photo uploaded.'], 422);
        }

        try {
            $request->validate(['photo' => ['image', 'max:5120']]); // 5MB
            $file = $request->file('photo');
            if (!$file->isValid()) {
                throw ValidationException::withMessages(['photo' => 'Invalid upload.']);
            }

            $bytes = file_get_contents($file->getRealPath());
            $b64 = base64_encode($bytes);

            UserProfile::updateOrCreate(
                ['User_id' => $userId],
                ['Profile' => $b64]
            );

            Log::info('Profile saved for Program Chair', ['User_id' => $userId]);
            return response()->json(['ok' => true, 'message' => 'Profile saved.']);

        } catch (ValidationException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            Log::error('Program Chair Profile save failed', ['err' => $e->getMessage()]);
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 500);
        }
    }


}
