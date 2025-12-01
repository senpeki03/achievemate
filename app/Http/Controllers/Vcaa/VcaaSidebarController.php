<?php

namespace App\Http\Controllers\Vcaa;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VcaaSidebarController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->query('search');

        $query = DB::table('college')
            ->leftJoin('campus', 'campus.Campus_id', '=', 'college.Campus_id')
            ->leftJoin('program', 'program.College_id', '=', 'college.College_id')
            ->select(
                'college.College_id',
                'college.College_name',
                'campus.Campus_name',
                DB::raw('COUNT(program.Program_id) as program_count')
            )
            ->groupBy('college.College_id', 'college.College_name', 'campus.Campus_name')
            ->orderBy('college.College_name');

        if ($search) {
            $query->where('college.College_name', 'LIKE', "%{$search}%");
        }

        $collegeProgramCounts = $query->get();

        return view('vcaa.dashboard', compact('collegeProgramCounts'));
    }
}
