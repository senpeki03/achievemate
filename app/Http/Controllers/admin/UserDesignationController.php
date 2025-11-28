<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\QueryException;

use App\Models\UserDesignation;
use App\Models\UserManage;
use App\Models\Designation;
use App\Models\Campus;
use App\Models\College;
use App\Models\Program;
use App\Models\Login;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Hash;
use App\Mail\DesignationCredentialsMail;

class UserDesignationController extends Controller
{
    public function show($id)
    {
        // Full user info
        $user = UserManage::findOrFail($id);

        // LEFT JOINs because many FKs can be NULL based on Access
        $designations = UserDesignation::where('user_designation.User_id', $id)
            ->leftJoin('designation', 'user_designation.Designation_id', '=', 'designation.Designation_id')
            ->leftJoin('campus',      'user_designation.Campus_id',      '=', 'campus.Campus_id')
            ->leftJoin('college',     'user_designation.College_id',     '=', 'college.College_id')
            ->leftJoin('program',     'user_designation.Program_id',     '=', 'program.Program_id')
            ->leftJoin('major',       'user_designation.Major_id',       '=', 'major.Major_id')
            ->select(
                'user_designation.*',
                'designation.Designation_name',
                'designation.Access',
                'campus.Campus_name',
                'college.Abbreviation as College_abbreviation',
                'program.Abbreviation as Program_abbreviation',
                'major.Major_name as Major_name'
            )
            ->get();

        $designationList = Designation::select('Designation_id','Designation_name','Access')->get();
        $campusList  = Campus::all();
        $collegeList = College::all();
        $programList = Program::all();

        return view('admin.userdesignation', compact(
            'user','designations','designationList','campusList','collegeList','programList'
        ));
    }

    public function store(Request $request)
    {
        try {
            // Base validation (don’t force optional fields here)
            $base = $request->validate([
                'User_id'          => 'required|integer',
                'Designation_name' => 'required|string',
                'Campus_name'      => 'nullable|string',
                'College_id'       => 'nullable|integer',
                'Program_id'       => 'nullable|integer',
                'Major_id'         => 'nullable|integer',
            ]);

            // Find designation & access
            $designation = Designation::where('Designation_name', $base['Designation_name'])->first();
            if (!$designation) {
                return response()->json(['success' => false, 'message' => 'Invalid Designation.'], 422);
            }
            $access = $designation->Access; // Campus | College | Program | Major

            // Enforce requireds by access
            $rules = [];
            switch ($access) {
                case 'Campus':
                    $rules = ['Campus_name' => 'required|string'];
                    break;
                case 'College':
                    $rules = ['Campus_name' => 'required|string', 'College_id' => 'required|integer'];
                    break;
                case 'Program':
                    $rules = ['Campus_name' => 'required|string', 'College_id' => 'required|integer', 'Program_id' => 'required|integer'];
                    break;
                case 'Major':
                    $rules = ['Campus_name' => 'required|string', 'College_id' => 'required|integer', 'Program_id' => 'required|integer', 'Major_id' => 'required|integer'];
                    break;
                default:
                    $rules = [];
            }
            $request->validate($rules);

            // Resolve FKs (nullable where not required)
            $designationId = $designation->Designation_id;

            $campusId = null;
            if (!empty($base['Campus_name'])) {
                $campusId = Campus::where('Campus_name', $base['Campus_name'])->value('Campus_id');
                if (!$campusId && in_array($access, ['Campus','College','Program','Major'], true)) {
                    return response()->json(['success'=>false,'message'=>'Invalid Campus.'], 422);
                }
            }

            $collegeId = $base['College_id'] ?? null;
            $programId = $base['Program_id'] ?? null;
            $majorId   = $base['Major_id']   ?? null;

            // Ensure user exists
            $user = UserManage::find($base['User_id']);
            if (!$user) {
                return response()->json(['success' => false, 'message' => 'User not found.'], 404);
            }

            // Create / update login (consider switching to unique username scheme later)
            $username        = $user->First_name;
            $defaultPassword = $user->Last_name;
            $usertype        = $base['Designation_name'];

            $login = Login::updateOrCreate(
                ['username' => $username],
                ['password' => Hash::make($defaultPassword), 'usertype' => $usertype]
            );

            // Block exact duplicate assignment (same scope for same user)
            $exists = UserDesignation::where([
                'User_id'        => $base['User_id'],
                'Designation_id' => $designationId,
                'Campus_id'      => $campusId,
                'College_id'     => $collegeId,
                'Program_id'     => $programId,
                'Major_id'       => $majorId,
            ])->exists();

            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'This designation already exists for the user.'
                ], 422);
            }

            // Create record (ensure DB columns are nullable for Campus/College/Program/Major/Login)
            UserDesignation::create([
                'Designation_id' => $designationId,
                'Campus_id'      => $campusId,
                'College_id'     => $collegeId,
                'Program_id'     => $programId,
                'Major_id'       => $majorId,
                'User_id'        => $base['User_id'],
                'Login_id'       => $login->Login_id,
            ]);

            // Email (non-fatal)
            try {
                Mail::to($user->Email)->send(new DesignationCredentialsMail(
                    $user->Title,
                    $user->First_name . ' ' . $user->Middle_name . ' ' . $user->Last_name,
                    $username,
                    $defaultPassword,
                    url(''),
                    $usertype,
                ));
            } catch (\Throwable $e) {
                Log::warning("Email failed for designation: " . $e->getMessage());
            }

            return response()->json(['success' => true]);

        } catch (ValidationException $ve) {
            return response()->json([
                'success' => false,
                'message' => $ve->getMessage(),
                'errors'  => $ve->errors(),
            ], 422);
        } catch (QueryException $qe) {
            Log::error('[UserDesignation.store][SQL] '.$qe->getMessage(), ['sql' => $qe->getSql(), 'bindings' => $qe->getBindings()]);
            if (app()->environment('local')) {
                return response()->json(['success' => false, 'message' => $qe->getMessage()], 500);
            }
            return response()->json(['success' => false, 'message' => 'Database error.'], 500);
        } catch (\Throwable $e) {
            Log::error("[UserDesignation.store] ".$e->getMessage(), ['trace' => $e->getTraceAsString()]);
            if (app()->environment('local')) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'file'    => $e->getFile(),
                    'line'    => $e->getLine(),
                ], 500);
            }
            return response()->json(['success' => false, 'message' => 'Internal error while saving designation.'], 500);
        }
    }

    public function delete($id)
    {
        $designation = UserDesignation::findOrFail($id);
        $designation->delete();
        return response()->json(['success' => true, 'cacheFlush' => true]);
    }

    public function getCollegesByCampus(Request $request)
    {
        $campusName = $request->query('campus_name');

        $colleges = College::join('campus', 'college.Campus_id', '=', 'campus.Campus_id')
            ->where('campus.Campus_name', $campusName)
            ->select('college.College_id', 'college.College_name', 'college.Abbreviation')
            ->get();

        return response()->json($colleges);
    }

    public function getByCollege(Request $request)
    {
        $collegeId = $request->query('college_id');

        $programs = Program::where('College_id', $collegeId)
            ->get(['Program_id', 'Program_name']);

        return response()->json($programs);
    }

    public function getByProgram(Request $request)
    {
        $programId = $request->query('program_id');

        $majors = \App\Models\Major::where('Program_id', $programId)
            ->get(['Major_id', 'Major_name']);

        return response()->json($majors);
    }
}
