<?php

namespace App\Http\Controllers\admin;

use Illuminate\Http\Request;
use App\Models\UserManage;
use App\Http\Controllers\Controller;

class UserManageController extends Controller
{
    public function index()
    {
        // Fetch all users from the user_manage table
        $users = UserManage::all(); 
        return view('admin.usermanage', compact('users')); // Pass to the Blade view
    }

    public function store(Request $request)
    {
        // Check if the email already exists in the database
        $emailExists = UserManage::where('Email', $request->Email)->exists();
        
        if ($emailExists) {
            // Set session flash message for duplicate email
            session()->flash('duplicateEmail', 'This email is already in use.');
            return response()->json(['status' => 'email_duplicate']);  // Respond with a custom status
        }

        // Proceed with user creation if email is unique
        $request->validate([
            'Email' => 'required|email|unique:user_manage,Email', // Ensure email is unique
        ]);

        UserManage::create([
            'Title' => $request->Title,
            'First_name' => $request->First_name,
            'Middle_name' => $request->Middle_name,
            'Last_name' => $request->Last_name,
            'Email' => $request->Email,
        ]);

        return response()->json(['status' => 'success']);
    }

    public function update(Request $request, $id)
    {
        $user = UserManage::findOrFail($id);

        // Validate that email is unique (if the email is updated)
        $request->validate([
            'Email' => 'required|email|unique:user_manage,Email,' . $user->id, // Allow same email for the current user
        ]);

        // Update the user
        $user->update([
            'Title' => $request->Title,
            'First_name' => $request->First_name,
            'Middle_name' => $request->Middle_name,
            'Last_name' => $request->Last_name,
            'Email' => $request->Email,  // Ensure email is updated
        ]);

        return response()->json(['status' => 'updated']);
    }


    public function destroy($id)
    {
        $user = UserManage::find($id);
        
        if (!$user) {
            return response()->json(['status' => 'not_found'], 404); // Handle not found user
        }

        $user->delete();
        return response()->json(['status' => 'success']);
    }
}
