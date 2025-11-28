<?php

namespace App\Http\Controllers\ProgramChair;

use App\Http\Controllers\Controller;
use App\Models\Rank;
use App\Models\UserDesignation;
use App\Models\UserManage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RankController extends Controller
{
    public function index()
    {
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

        try {
            DB::transaction(function () use ($rules) {
                // remove previous rules
                Rank::query()->rules()->delete();

                // insert fresh set
                foreach ($rules as $r) {
                    Rank::create([
                        'is_rule' => 1,
                        'User_id' => $this->resolveCreatorId(),
                        'min_gwa' => $r['min_gwa'],
                        'max_gwa' => $r['max_gwa'],
                        'Rank'    => $r['rank_name'],
                    ]);
                }
            });

            return back()->with('success', 'Rank rules saved.');
            
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to save rules: ' . $e->getMessage()]);
        }
    }

    /**
     * Resolve current user to a valid user_manage.User_id via UserDesignation
     */
    private function resolveCreatorId(): ?int
    {
        $user = Auth::user();
        
        if (!$user) {
            return null;
        }

        // Get Login_id from authenticated user
        $loginId = $user->Login_id ?? $user->login_id ?? $user->id ?? null;
        
        if (!$loginId) {
            return null;
        }

        // Find UserDesignation by Login_id and get the User_id
        $userDesignation = UserDesignation::where('Login_id', $loginId)->first();
        
        return $userDesignation ? $userDesignation->User_id : null;
    }
}