<?php

namespace App\Http\Controllers\Dean;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;

use App\Models\UserManage;
use App\Models\UserDesignation;
use App\Models\UserProfile;

class DeanProfileController extends Controller
{
    /** Map Login_id -> latest User_id via user_designation */
    protected function userIdFromLogin(?int $loginId): ?int
    {
        if (!$loginId) return null;
        $row = UserDesignation::where('Login_id', $loginId)
            ->orderByDesc('UserDesignation_id')
            ->first();
        return $row?->User_id;
    }

    /** Resolve Dean's actual User_id */
    protected function resolveUserId(): ?int
    {
        // If session already has a valid User_id, use it
        $sid = session('User_id') ?? session('user_id');
        if ($sid && UserManage::where('User_id', $sid)->exists()) {
            return (int) $sid;
        }

        // Map from Login_id (session) → User_id
        $loginFromSession = session('Login_id');
        if ($uid = $this->userIdFromLogin((int) $loginFromSession)) {
            return $uid;
        }

        // Fallback: auth()->id() as Login_id
        $loginFromAuth = auth()->id();
        if ($uid = $this->userIdFromLogin((int) $loginFromAuth)) {
            return $uid;
        }

        return null;
    }

    /** GET /dean/profile */
    public function show()
    {
        $userId = $this->resolveUserId();
        $user   = $userId ? UserManage::find($userId) : null;

        $fullName = trim(
            ($user->First_name ?? '') . ' ' .
            ($user->Middle_name ?? '') . ' ' .
            ($user->Last_name ?? '')
        ) ?: 'Dean';

        return view('dean.profile', [
            'fullName'      => $fullName,
            'college'       => 'College of Informatics and Computing Sciences',
            'program'       => 'Bachelor of Science in Information Technology',
            'srCode'        => $userId ?? '—',
            'contactNumber' => '',
            'homeAddress'   => '',
            'placeOfBirth'  => '',
            'dobYmd'        => '',
            'ayLabel'       => date('Y') . '-' . (date('Y') + 1),
            'photoUrl'      => route('dean.profile.photo', ['_v' => time()]),
        ]);
    }

    /** GET /dean/profile/photo (stream from DB with fallback) */
    public function photo()
    {
        $userId = $this->resolveUserId();
        $row = $userId ? UserProfile::where('User_id', $userId)->first() : null;

        if ($row && !empty($row->Profile)) {
            $raw  = $row->Profile;
            $blob = ($dec = base64_decode($raw, true)) !== false ? $dec : $raw;
            $info = @getimagesizefromstring($blob);
            $mime = $info['mime'] ?? 'image/jpeg';
            return response($blob, 200)->header('Content-Type', $mime);
        }

        // fallback file
        $path = public_path('img/profile-placeholder.png');
        return response()->file($path, ['Content-Type' => 'image/png']);
    }

    /** POST /dean/profile/save (upload, base64 store) */
    public function save(Request $request)
    {
        $userId = $this->resolveUserId();
        if (!$userId) {
            return response()->json(['ok' => false, 'message' => 'Cannot resolve User_id from Login_id.'], 422);
        }

        if (!UserManage::where('User_id', $userId)->exists()) {
            return response()->json(['ok' => false, 'message' => "No parent in user_manage for User_id={$userId}"], 422);
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
            $b64   = base64_encode($bytes); // safe for MEDIUMBLOB + JSON

            UserProfile::updateOrCreate(
                ['User_id' => $userId],
                ['Profile' => $b64]
            );

            return response()->json(['ok' => true, 'message' => 'Profile saved.']);
        } catch (ValidationException $e) {
            return response()->json([
                'ok'      => false,
                'message' => $e->getMessage(),
                'errors'  => $e->errors()
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Dean profile save failed', ['err' => $e->getMessage()]);
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Legacy / route compatibility:
     * Some routes may still point to DeanProfileController@change,
     * so forward that to changePassword().
     */
    public function change(Request $request)
    {
        return $this->changePassword($request);
    }

    /** POST /dean/password/change */
    public function changePassword(Request $request)
    {
        Log::info('Dean.changePassword: start', [
            'login_id' => auth()->id(),
            'ip'       => $request->ip(),
        ]);

        try {
            // Validate inputs (matches your Blade field names)
            $request->validate([
                'current_password'          => ['required', 'string'],
                'new_password'              => ['required', 'string', 'min:8', 'confirmed'],
                // requires new_password_confirmation
            ]);

            // Authenticated login record (from login table)
            $login = auth()->user();

            if (!$login) {
                Log::warning('Dean.changePassword: no auth user');
                return response()->json([
                    'ok'      => false,
                    'message' => 'Not authenticated.',
                ], 401);
            }

            // Try both common column names: password / Password
            $hashedPassword = null;

            if (isset($login->password)) {
                $hashedPassword = $login->password;
            } elseif (isset($login->Password)) {
                $hashedPassword = $login->Password;
            }

            if (!$hashedPassword) {
                Log::error('Dean.changePassword: no password column on login model', [
                    'class' => get_class($login),
                ]);
                return response()->json([
                    'ok'      => false,
                    'message' => 'Password column not found on login record.',
                ], 500);
            }

            // Check current password
            if (!Hash::check($request->input('current_password'), $hashedPassword)) {
                return response()->json([
                    'ok'      => false,
                    'message' => 'The current password is incorrect.',
                ], 422);
            }

            $newHashed = Hash::make($request->input('new_password'));

            // Set both if they exist to be safe
            if (isset($login->password)) {
                $login->password = $newHashed;
            }
            if (isset($login->Password)) {
                $login->Password = $newHashed;
            }

            $login->save();

            Log::info('Dean.changePassword: success', ['login_pk' => $login->getKey()]);

            return response()->json([
                'ok'      => true,
                'message' => 'Password changed successfully.',
            ]);
        } catch (ValidationException $e) {
            Log::warning('Dean.changePassword: validation failed', [
                'errors' => $e->errors(),
            ]);
            return response()->json([
                'ok'      => false,
                'message' => 'Password change failed.',
                'errors'  => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Dean.changePassword: exception', [
                'err'   => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'ok'      => false,
                'message' => 'An unexpected error occurred.',
            ], 500);
        }
    }
}
