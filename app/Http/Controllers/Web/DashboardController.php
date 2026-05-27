<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\JobPosting;
use App\Models\OutreachMessage;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $stats = [
            'jobs_open'             => JobPosting::query()->whereNotIn('status', ['closed', 'filled'])->count(),
            'candidates_sourced'    => Candidate::query()->count(),
            'pending_approvals'     => OutreachMessage::query()->where('status', 'pending_approval')->count(),
            'outreach_sent'         => OutreachMessage::query()->where('status', 'sent')->count(),
            'candidates_shortlisted' => Candidate::query()->where('stage', 'shortlisted')->count(),
        ];
        $recentCandidates = Candidate::query()->latest()->limit(10)->get();
        $recentJobs       = JobPosting::query()->latest()->limit(5)->get();

        return view('dashboard', compact('stats', 'recentCandidates', 'recentJobs'));
    }
}
