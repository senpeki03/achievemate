<?php

namespace App\Http\Controllers\ProgramChair;

use App\Http\Controllers\Controller;
use App\Models\Rank;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RankController extends Controller
{
    public function index()
    {
        // Pull rule rows (is_rule = 1) -> pass to blade
        $rules = Rank::query()
            ->rules()
            ->orderBy('min_gwa')
            ->get(['min_gwa','max_gwa','Rank'])
            ->map(fn($r) => [
                'min_gwa'   => number_format((float)$r->min_gwa, 4, '.', ''),
                'max_gwa'   => number_format((float)$r->max_gwa, 4, '.', ''),
                'rank_name' => $r->Rank,
            ])
            ->toArray();

        return view('programchair.rank', compact('rules'));
    }

    public function save(Request $request)
    {
        $data = $request->validate([
            'rules'             => ['required','array','min:1'],
            'rules.*.min_gwa'   => ['required','numeric','between:0,5'],
            'rules.*.max_gwa'   => ['required','numeric','between:0,5'],
            'rules.*.rank_name' => ['required','string','max:100'],
        ]);

        $rules = collect($data['rules'])
            ->map(function ($r) {
                $min = (float)$r['min_gwa'];
                $max = (float)$r['max_gwa'];
                if ($min > $max) {
                    throw ValidationException::withMessages([
                        'rules' => ['Each row must have min_gwa ≤ max_gwa.'],
                    ]);
                }
                return [
                    'min_gwa'   => round($min, 4),
                    'max_gwa'   => round($max, 4),
                    'rank_name' => trim($r['rank_name']),
                ];
            })
            ->sortBy('min_gwa')
            ->values();

        // Map current account to a valid user_manage.User_id (or NULL)
        $creatorId = $this->resolveCreatorId();

        DB::transaction(function () use ($rules, $creatorId) {
            // remove previous rules
            Rank::query()->rules()->delete();

            // insert fresh set
            foreach ($rules as $r) {
                Rank::create([
                    'is_rule' => 1,
                    'User_id' => $creatorId,       // will be NULL if not resolvable
                    'min_gwa' => $r['min_gwa'],
                    'max_gwa' => $r['max_gwa'],
                    'Rank'    => $r['rank_name'],
                ]);
            }
        });

        return back()->with('success', 'Rank rules saved.');
    }

    /**
     * Resolve current user to a valid user_manage.User_id, else return NULL.
     */
    private function resolveCreatorId(): ?int
    {
        // Candidates: adjust order to match your app’s login/session
        $candidates = [
            session('User_id'),
            session('user_id'),
            session('id'),
            optional(Auth::user())->User_id ?? null,
            Auth::id(),
        ];

        foreach ($candidates as $raw) {
            if ($raw === null) continue;
            $id = is_numeric($raw) ? (int)$raw : null;
            if (!$id) continue;

            if (DB::table('user_manage')->where('User_id', $id)->exists()) {
                return $id;
            }
        }

        // Fallback by email/username (if present)
        $email = session('email') ?? session('Email') ?? optional(Auth::user())->email;
        if ($email) {
            $id = DB::table('user_manage')->where('Email', $email)->value('User_id');
            if ($id) return (int)$id;
        }

        $username = session('username') ?? session('Username') ?? optional(Auth::user())->username;
        if ($username) {
            $id = DB::table('user_manage')->where('Username', $username)->value('User_id');
            if ($id) return (int)$id;
        }

        return null;
    }
}
