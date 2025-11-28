<?php

namespace App\Http\Controllers\Registrar;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Campus;
use App\Models\College;
use App\Models\Program;
use App\Models\Major;
use App\Models\Curriculum;
use App\Models\StudentManage;
use App\Models\UserDesignation;

class StudentController extends Controller
{
    public function index(Request $request)
    {
        // ========= 1. Kunin ang current login (App\Models\Login) =========
        $login = auth()->user(); // ito yung nasa `login` table

        Log::info('RegistrarStudent.index: start', [
            'login_class' => $login ? get_class($login) : null,
            'login_id'    => $login->Login_id ?? null,
            'session_Login_id' => session('Login_id'),
            'session_usertype' => session('usertype'),
        ]);

        // Gamitin agad ang relation na `userDesignation`
        $designation = null;
        if ($login && method_exists($login, 'userDesignation')) {
            $designation = $login->userDesignation; // may $with = ['userDesignation']

            Log::info('RegistrarStudent.index: userDesignation loaded from Login', [
                'has_designation' => (bool) $designation,
                'designation_id'  => $designation->UserDesignation_id ?? null,
                'designation_Campus_id' => $designation->Campus_id ?? null,
            ]);

            if ($designation && !$designation->relationLoaded('campus')) {
                $designation->load('campus');
            }
        } else {
            Log::warning('RegistrarStudent.index: auth()->user() has no userDesignation relation');
        }

        // Campus fixed by designation (Registrar / VC / etc.)
        $userCampusId   = $designation?->Campus_id;
        $userCampusName = $designation?->campus?->Campus_name;

        Log::info('RegistrarStudent.index: campus from designation', [
            'userCampusId'   => $userCampusId,
            'userCampusName' => $userCampusName,
        ]);

        // 🔒 Final campus for this page: kung may userCampusId, iyon ang gagamitin
        $campusId = $userCampusId ?: $this->resolveCampusId($request);

        Log::info('RegistrarStudent.index: final campusId resolved', [
            'campusId' => $campusId,
        ]);

        // ========= 2. Datasets for modal (dropdowns) =========
        $campuses = Campus::orderBy('Campus_name')
            ->get(['Campus_id', 'Campus_name']);

        $colleges = College::orderBy('College_name')
            ->get(['College_id', 'College_name', 'Campus_id']);

        $programs = Program::orderBy('Program_name')
            ->get(['Program_id', 'Program_name', 'Campus_id', 'College_id']);

        $majors = Major::orderBy('Major_name')
            ->get(['Major_id', 'Major_name', 'Campus_id', 'College_id', 'Program_id']);

        $curriculums = Curriculum::query()
            ->join('curriculum_ay as ay', 'ay.CurriculumAY_id', '=', 'curriculum.CurriculumAY_id')
            ->orderBy('Curriculum_name')
            ->get([
                'curriculum.curriculum_id',
                'curriculum.Curriculum_name',
                'ay.Campus_id',
                'ay.College_id',
                'ay.Program_id',
                'ay.Major_id',
            ]);

        // Kung wala talagang campus (sobrang edge case) → empty state
        if (!$campusId) {
            Log::warning('RegistrarStudent.index: campusId is NULL, returning empty view');

            return view('registrar.student', [
                'level'          => 'college',
                'title'          => 'Colleges',
                'rows'           => collect(),
                'campusId'       => null,
                'collegeId'      => null,
                'programId'      => null,
                'nextLevel'      => 'program',
                'breadcrumb'     => [],
                'campuses'       => $campuses,
                'colleges'       => $colleges,
                'programs'       => $programs,
                'majors'         => $majors,
                'curriculums'    => $curriculums,
                'userCampusId'   => $userCampusId,
                'userCampusName' => $userCampusName,
            ]);
        }

        // ========= 3. Drilldown level (college → program → major) =========
        $level     = $request->get('level', 'college');
        $collegeId = $request->integer('college_id') ?: null;
        $programId = $request->integer('program_id') ?: null;

        Log::info('RegistrarStudent.index: drilldown params', [
            'level'     => $level,
            'campus_id' => $campusId,
            'college_id'=> $collegeId,
            'program_id'=> $programId,
        ]);

        // Default values para hindi mag "Undefined variable" sa Blade
        $title      = 'Colleges';
        $breadcrumb = [];
        $nextLevel  = 'program';
        $rows       = collect();

        try {
            switch ($level) {
                case 'program':
                    if (!$collegeId) {
                        return redirect()->route('registrar.student', [
                            'level'     => 'college',
                            'campus_id' => $campusId,
                        ])->with('error', 'Select a college first.');
                    }

                    $rows = Program::query()
                        ->where('program.Campus_id', $campusId)
                        ->where('program.College_id', $collegeId)
                        ->leftJoin('student_course as sc', function ($j) {
                            $j->on('sc.Program_id', '=', 'program.Program_id')
                              ->on('sc.College_id', '=', 'program.College_id')
                              ->on('sc.Campus_id',  '=', 'program.Campus_id');
                        })
                        ->groupBy('program.Program_id', 'program.Program_name')
                        ->orderBy('program.Program_name')
                        ->get([
                            'program.Program_id as id',
                            'program.Program_name as name',
                            DB::raw('COALESCE(COUNT(DISTINCT sc.Student_id), 0) as total'),
                        ]);

                    $title      = 'Programs';
                    $breadcrumb = $this->breadcrumb($campusId, $collegeId, null);
                    $nextLevel  = 'major';
                    break;

                case 'major':
                    if (!$programId) {
                        return redirect()->route('registrar.student', [
                            'level'      => 'program',
                            'campus_id'  => $campusId,
                            'college_id' => $collegeId,
                        ])->with('error', 'Select a program first.');
                    }

                    $noneIds = Major::where('Program_id', $programId)
                        ->when($collegeId, fn($q) => $q->where('College_id', $collegeId))
                        ->whereRaw('LOWER(Major_name) = "none"')
                        ->pluck('Major_id');

                    $majList = Major::query()
                        ->where('major.Campus_id', $campusId)
                        ->when($collegeId, fn($q) => $q->where('major.College_id', $collegeId))
                        ->where('major.Program_id', $programId)
                        ->when($noneIds->isNotEmpty(), fn($q) => $q->whereNotIn('major.Major_id', $noneIds))
                        ->leftJoin('student_course as sc', 'sc.Major_id', '=', 'major.Major_id')
                        ->groupBy('major.Major_id', 'major.Major_name')
                        ->orderBy('major.Major_name')
                        ->get([
                            'major.Major_id as id',
                            'major.Major_name as name',
                            DB::raw('COALESCE(COUNT(DISTINCT sc.Student_id), 0) as total'),
                        ]);

                    $noneCount = DB::table('student_course as sc')
                        ->where('sc.Campus_id', $campusId)
                        ->when($collegeId, fn($q) => $q->where('sc.College_id', $collegeId))
                        ->where('sc.Program_id', $programId)
                        ->where(function ($q) use ($noneIds) {
                            $q->whereNull('sc.Major_id')
                              ->orWhere('sc.Major_id', 0);
                            if ($noneIds->isNotEmpty()) {
                                $q->orWhereIn('sc.Major_id', $noneIds);
                            }
                        })
                        ->distinct('sc.Student_id')
                        ->count('sc.Student_id');

                    $rows = $majList->push((object)[
                        'id'    => null,
                        'name'  => 'None',
                        'total' => (int) $noneCount,
                    ]);

                    $title      = 'Majors';
                    $breadcrumb = $this->breadcrumb($campusId, $collegeId, $programId);
                    $nextLevel  = null;
                    break;

                case 'college':
                default:
                    $rows = College::query()
                        ->where('college.Campus_id', $campusId)
                        ->leftJoin('curriculum_ay as ay', 'ay.College_id', '=', 'college.College_id')
                        ->leftJoin('curriculum as cur', 'cur.CurriculumAY_id', '=', 'ay.CurriculumAY_id')
                        ->leftJoin('student_manage as s', 's.curriculum_id', '=', 'cur.curriculum_id')
                        ->groupBy('college.College_id', 'college.College_name')
                        ->orderBy('college.College_name')
                        ->get([
                            'college.College_id as id',
                            'college.College_name as name',
                            DB::raw('COALESCE(COUNT(s.curriculum_id), 0) as total'),
                        ]);

                    $title      = 'Colleges';
                    $breadcrumb = $this->breadcrumb($campusId, null, null);
                    $nextLevel  = 'program';
                    $level      = 'college';
                    break;
            }
        } catch (\Throwable $e) {
            Log::error('Registrar Student index failed', [
                'campus_id'  => $campusId,
                'college_id' => $collegeId,
                'program_id' => $programId,
                'level'      => $level,
                'error'      => $e->getMessage(),
            ]);
            return back()->with('error', 'Failed to load list. Check logs.');
        }

        return view('registrar.student', [
            'level'          => $level,
            'title'          => $title,
            'rows'           => $rows,
            'campusId'       => $campusId,
            'collegeId'      => $collegeId,
            'programId'      => $programId,
            'nextLevel'      => $nextLevel,
            'breadcrumb'     => $breadcrumb,
            'campuses'       => $campuses,
            'colleges'       => $colleges,
            'programs'       => $programs,
            'majors'         => $majors,
            'curriculums'    => $curriculums,
            'userCampusId'   => $userCampusId,
            'userCampusName' => $userCampusName,
        ]);
    }


  /** Final student list by Curriculum/Major/Program (with optional Year Level) */
  public function studentList(Request $request)
  {
      // ✅ Uses same campus resolution (UserDesignation-first)
      $campusId  = $this->resolveCampusId($request);
      $currId    = $request->integer('curriculum_id') ?: null;

      // IMPORTANT: preserve 0 (for "None" group)
      $majorId = $request->has('major_id')
          ? (int) $request->query('major_id')
          : null;

      $programId = $request->integer('program_id') ?: null;

      $yearLevelLabel = trim((string) $request->query('year_level', ''));
      $yearLevelToken = strtoupper(str_replace(' ', '_', $yearLevelLabel));

      if (!$campusId) return back()->with('error', 'No campus found.');

      try {
          // IDs of majors named "None" / "No Major"
          $noneIds = Major::query()
              ->where('Campus_id', $campusId)
              ->when($programId, fn($q) => $q->where('Program_id', $programId))
              ->whereRaw('LOWER(Major_name) IN ("none","no major")')
              ->pluck('Major_id')
              ->all();

          $q = StudentManage::query()
              ->leftJoin('student_course as sc', 'sc.Student_id', '=', 'student_manage.Student_id')
              ->leftJoin('college', 'college.College_id', '=', 'sc.College_id')
              ->leftJoin('program', 'program.Program_id', '=', 'sc.Program_id')
              ->leftJoin('major', 'major.Major_id', '=', 'sc.Major_id')
              ->where('sc.Campus_id', $campusId);

          if ($currId) {
              // Filter by specific curriculum
              $q->where('student_manage.curriculum_id', $currId);

          } elseif ($majorId !== null) {
              // Filter per major (including "None" group)
              $q->where(function ($w) use ($majorId, $noneIds) {
                  if ($majorId === 0) {
                      // "None" group: walang major, 0, or majors named 'none'
                      $w->whereNull('sc.Major_id')
                        ->orWhere('sc.Major_id', 0);

                      if (!empty($noneIds)) {
                          $w->orWhereIn('sc.Major_id', $noneIds);
                      }
                  } else {
                      // Normal specific major
                      $w->where('sc.Major_id', $majorId);
                  }
              });

          } elseif ($programId) {
              // Filter by whole program (all majors)
              $q->where('sc.Program_id', $programId);

          } else {
              return back()->with('error', 'Select a program or major (or curriculum) first.');
          }

          if ($yearLevelLabel !== '') {
              $q->where(function ($w) use ($yearLevelLabel, $yearLevelToken) {
                  $w->where('student_manage.Year', $yearLevelLabel)
                    ->orWhere('student_manage.Year', $yearLevelToken);
              });
          }

          $students = $q->orderBy('student_manage.Last_name')
              ->orderBy('student_manage.First_name')
              ->get([
                  'student_manage.*',
                  'college.College_name',
                  'program.Program_name',
                  DB::raw("COALESCE(major.Major_name,'— No Major —') as Major_name"),
              ]);

          $first       = $students->first();
          $collegeName = $first->College_name ?? null;
          $programName = $first->Program_name ?? null;
          $majorName   = $first->Major_name   ?? '— No Major —';

          return view('registrar.studentlist', compact('students', 'collegeName', 'programName', 'majorName'));
      } catch (\Throwable $e) {
          Log::error('Registrar Student studentList failed', [
              'campus_id'   => $campusId,
              'curr_id'     => $currId,
              'major_id'    => $majorId,
              'program_id'  => $programId,
              'year_level'  => $yearLevelLabel,
              'error'       => $e->getMessage(),
          ]);
          return back()->with('error', 'Failed to load students. Check logs.');
      }
  }


    /* ================= helpers ================= */

    private function resolveCampusId(Request $request): ?int
    {
        $login = auth()->user();

        Log::info('RegistrarStudent.resolveCampusId: start', [
            'login_class' => $login ? get_class($login) : null,
            'login_id'    => $login->Login_id ?? null,
        ]);

        if ($login && method_exists($login, 'userDesignation')) {
            $designation = $login->userDesignation;

            Log::info('RegistrarStudent.resolveCampusId: from Login->userDesignation', [
                'has_designation' => (bool) $designation,
                'designation_id'  => $designation->UserDesignation_id ?? null,
                'Campus_id'       => $designation->Campus_id ?? null,
            ]);

            if ($designation && $designation->Campus_id) {
                return (int) $designation->Campus_id;
            }
        }

        // Fallback: from request
        if ($id = $request->integer('campus_id')) {
            Log::info('RegistrarStudent.resolveCampusId: using campus_id from request', [
                'campus_id' => $id,
            ]);
            return $id;
        }

        // Last fallback: first campus in system
        $fallback = Campus::query()->orderBy('Campus_id')->value('Campus_id');

        Log::info('RegistrarStudent.resolveCampusId: using first campus as fallback', [
            'campus_id' => $fallback,
        ]);

        return $fallback;
    }

    private function breadcrumb(?int $campusId, ?int $collegeId, ?int $programId): array
    {
        $crumbs = [];
        if ($campusId && ($campus = Campus::find($campusId))) {
            $crumbs[] = [
                'label' => $campus->Campus_name,
                'href'  => route('registrar.student', [
                    'level'     => 'college',
                    'campus_id' => $campusId,
                ]),
            ];
        }
        if ($campusId && $collegeId && ($college = College::find($collegeId))) {
            $crumbs[] = [
                'label' => $college->College_name,
                'href'  => route('registrar.student', [
                    'level'      => 'program',
                    'campus_id'  => $campusId,
                    'college_id' => $collegeId,
                ]),
            ];
        }
        if ($campusId && $programId && ($program = Program::find($programId))) {
            $crumbs[] = [
                'label' => $program->Program_name,
                'href'  => route('registrar.student', [
                    'level'      => 'major',
                    'campus_id'  => $campusId,
                    'program_id' => $programId,
                ]),
            ];
        }
        return $crumbs;
    }

    // AJAX: curriculum options for selected campus/college/program/major
    public function curriculumOptions(Request $request)
    {
        $campusId  = $this->resolveCampusId($request);
        $collegeId = (int) $request->query('college_id');
        $programId = $request->filled('program_id') ? (int) $request->query('program_id') : null;
        $majorRaw  = $request->query('major_id');
        $majorId   = ($majorRaw === '' || $majorRaw === null) ? null : (int) $majorRaw;

        if (!$campusId || !$collegeId) {
            return response()->json(['items' => [], 'count' => 0]);
        }

        $noneIds = Major::query()
            ->where('Campus_id',  $campusId)
            ->where('College_id', $collegeId)
            ->when($programId, fn($q) => $q->where('Program_id', $programId))
            ->whereRaw('LOWER(Major_name) IN ("none","no major")')
            ->pluck('Major_id')
            ->all();

        $q = Curriculum::query()
            ->join('curriculum_ay as ay', 'ay.CurriculumAY_id', '=', 'curriculum.CurriculumAY_id')
            ->leftJoin('program', 'program.Program_id', '=', 'ay.Program_id')
            ->leftJoin('major',   'major.Major_id',   '=', 'ay.Major_id')
            ->where('ay.Campus_id',  $campusId)
            ->where('ay.College_id', $collegeId);

        if ($programId !== null) {
            $q->where(function ($w) use ($programId) {
                $w->where('ay.Program_id', $programId)
                  ->orWhereNull('ay.Program_id');
            });
        }

        if ($majorRaw !== null) {
            if ($majorId !== null) {
                $q->where(function ($w) use ($majorId) {
                    $w->where('ay.Major_id', $majorId)
                      ->orWhereNull('ay.Major_id')
                      ->orWhere('ay.Major_id', 0);
                });
            } else {
                $q->where(function ($w) use ($noneIds) {
                    $w->whereNull('ay.Major_id')
                      ->orWhere('ay.Major_id', 0);
                    if (!empty($noneIds)) {
                        $w->orWhereIn('ay.Major_id', $noneIds);
                    }
                });
            }
        }

        $rows = $q->orderBy('curriculum.Curriculum_name')
            ->get([
                'curriculum.curriculum_id as id',
                'curriculum.Curriculum_name as name',
                'program.Abbreviation as program_abbr',
                DB::raw("COALESCE(major.Major_name,'None') as major_name"),
            ]);

        $items = $rows->map(fn($r) => [
            'id'   => $r->id,
            'text' => $r->name . ' — [' . ($r->program_abbr ?: '—') . ' / ' . $r->major_name . ']',
        ]);

        return response()->json(['items' => $items, 'count' => $items->count()]);
    }

    public function assignYear(Request $request)
    {
        return back()->with('status', 'Not implemented');
    }
}
