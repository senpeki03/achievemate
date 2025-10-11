<?php

namespace App\Http\Controllers\admin;

use Illuminate\Http\Request;
use App\Models\UserDesignation;
use App\Models\UserManage;
use App\Models\Designation;
use App\Models\Campus;
use App\Models\College;
use App\Models\Program;
use App\Models\Login;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Hash;
use App\Mail\DesignationCredentialsMail;
use App\Http\Controllers\Controller;

class UserDesignationController extends Controller
{
    public function show($id)
    {
            // ⬇️ Fetch full user info via User_id from user_designation
        $designation = UserDesignation::with('user')->where('User_id', $id)->first(); // <--- add this line

        // ⬇️ Fallback (if needed) to find the UserManage info separately
        $user = UserManage::findOrFail($id);

        $designations = UserDesignation::where('user_designation.User_id', $id)
            ->join('designation', 'user_designation.Designation_id', '=', 'designation.Designation_id')
            ->join('campus', 'user_designation.Campus_id', '=', 'campus.Campus_id')
            ->join('college', 'user_designation.College_id', '=', 'college.College_id')
            ->join('program', 'user_designation.Program_id', '=', 'program.Program_id')
            ->select(
                'user_designation.*',
                'designation.Designation_name',
                'campus.Campus_name',
                'college.Abbreviation as College_abbreviation',
                'program.Abbreviation as Program_abbreviation'
            )
            ->get();

        $designationList = Designation::all();
        $campusList = Campus::all();
        $collegeList = College::all();
        $programList = Program::all();

        return view('admin.userdesignation', compact(
            'user',
            'designations',
            'designationList',
            'campusList',
            'collegeList',
            'programList'
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'User_id' => 'required|integer',
            'Designation_name' => 'required|string',
            'Campus_name' => 'required|string',
            'College_id' => 'required|integer',
            'Program_id' => 'required|integer',
            'Major_id' => 'nullable|integer',
        ]);

        $designationId = Designation::where('Designation_name', $validated['Designation_name'])->value('Designation_id');
        $campusId = Campus::where('Campus_name', $validated['Campus_name'])->value('Campus_id');

        if (!$designationId || !$campusId) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid Designation or Campus.'
            ]);
        }

        // ⬇️ Get user info
        $user = UserManage::find($validated['User_id']);
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'User not found']);
        }

        $email = $user->Email;
        $username = $user->First_name;         // First name as username
        $defaultPassword = $user->Last_name;   // Last name as default password
        $usertype = $validated['Designation_name'];

        // ⬇️ Create or update login and retrieve Login_id
        $login = Login::updateOrCreate(
            ['username' => $username], 
            [
                'username' => $username,
                'password' => Hash::make($defaultPassword),
                'usertype' => $usertype,
            ]
        );

        // ⬇️ Create designation and assign Login_id
        $designation = UserDesignation::create([
            'Designation_id' => $designationId,
            'Campus_id'      => $campusId,
            'College_id'     => $validated['College_id'],
            'Program_id'     => $validated['Program_id'],
            'Major_id'       => $validated['Major_id'] ?? null,
            'User_id'        => $validated['User_id'],
            'Login_id'       => $login->Login_id, // ✅ fixed
        ]);

        // ⬇️ Send email with credentials
        $loginUrl = url('');
        try {
            Mail::to($user->Email)->send(new DesignationCredentialsMail(
                $user->Title,
                $user->First_name . ' ' . $user->Middle_name . ' ' . $user->Last_name,
                $username,
                $defaultPassword,
                url(''),
                $usertype,
            ));

        } catch (\Exception $e) {
    Log::error("Email failed: " . $e->getMessage());
    }
        return response()->json(['success' => true]);
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
