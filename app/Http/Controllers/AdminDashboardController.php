<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\GanttTask;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AdminDashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $isMaster = $request->user()->hasRole('master');
        $request->validate([
            'company_id' => ['nullable', 'integer', Rule::exists('companies', 'id')->whereNull('deleted_at')],
            'period' => ['nullable', Rule::in([7, 30, 90])],
        ]);

        // Master starts with the global view; a company filter is optional.
        $companyId = $isMaster
            ? ($request->filled('company_id') ? $request->integer('company_id') : $request->session()->get('master_company_id'))
            : (int) $request->user()->company_id;
        $period = (int) $request->input('period', 30);
        $since = now()->subDays($period)->startOfDay();

        $projects = Project::query()->when($companyId, fn ($query) => $query->where('company_id', $companyId));
        $tasks = GanttTask::query()->when($companyId, fn ($query) => $query->where('company_id', $companyId));
        $activeUsers = User::query()->where('is_active', true);
        $stats = [
            'projects' => (clone $projects)->count(),
            'active_projects' => (clone $projects)->whereIn('status', ['active', 'in_progress', 'STATUS_ACTIVE'])->count(),
            'tasks' => (clone $tasks)->count(),
            'completed_tasks' => (clone $tasks)->where('progress', 100)->count(),
            'overdue_tasks' => (clone $tasks)->where('end_at', '<', now())->where('progress', '<', 100)->count(),
            'team_members' => (clone $activeUsers)->where('role', '!=', 'master')->when($companyId, fn ($query) => $query->where('company_id', $companyId))->count(),
        ];

        $companyMetrics = [];
        $sessionTable = config('session.table', 'sessions');
        $sessionTrackingAvailable = config('session.driver') === 'database' && DB::getSchemaBuilder()->hasTable($sessionTable);
        if ($isMaster) {
            $companies = Company::query()->orderBy('name')->get(['id', 'name', 'is_active']);
            $userCounts = User::query()
                ->where('role', '!=', 'master')
                ->select('company_id', DB::raw('COUNT(*) as total_users'), DB::raw('SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_users'))
                ->groupBy('company_id')->get()->keyBy('company_id');

            $onlineCounts = collect();
            if ($sessionTrackingAvailable) {
                $cutoff = now()->subMinutes((int) config('session.lifetime', 120))->timestamp;
                $onlineCounts = DB::table(config('session.table', 'sessions').' as s')
                    ->join('users as u', 'u.id', '=', 's.user_id')
                    ->join('companies as c', 'c.id', '=', 'u.company_id')
                    ->whereNotNull('s.user_id')->where('s.last_activity', '>=', $cutoff)
                    ->where('u.is_active', true)->where('u.role', '!=', 'master')->whereNull('u.deleted_at')
                    ->where('c.is_active', true)->whereNull('c.deleted_at')
                    ->select('u.company_id', DB::raw('COUNT(DISTINCT u.id) as online_users'))
                    ->groupBy('u.company_id')->get()->keyBy('company_id');
            }

            $companyMetrics = $companies->map(function (Company $company) use ($userCounts, $onlineCounts, $sessionTrackingAvailable): array {
                $counts = $userCounts->get($company->id);
                $total = (int) ($counts->total_users ?? 0);
                $active = (int) ($counts->active_users ?? 0);
                $online = (int) ($onlineCounts->get($company->id)->online_users ?? 0);

                return [
                    'id' => $company->id,
                    'name' => $company->name,
                    'is_active' => $company->is_active,
                    'total_users' => $total,
                    'active_users' => $active,
                    'inactive_users' => max(0, $total - $active),
                    'online_users' => $sessionTrackingAvailable ? $online : null,
                    'offline_users' => $sessionTrackingAvailable ? max(0, $active - $online) : null,
                ];
            })->values();
        }

        $auditQuery = AuditLog::query()->where('created_at', '>=', $since)
            ->when($companyId, fn ($query) => $query->where('company_id', $companyId));
        $accessMetrics = [
            'events' => (clone $auditQuery)->count(),
            'users' => (clone $auditQuery)->whereNotNull('user_id')->distinct('user_id')->count('user_id'),
            'period_days' => $period,
            'online_tracking' => $isMaster && $sessionTrackingAvailable,
        ];

        $dailyUsage = (clone $auditQuery)
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as events'), DB::raw('COUNT(DISTINCT user_id) as users'))
            ->groupBy(DB::raw('DATE(created_at)'))->orderBy('date')->get();

        return Inertia::render('Admin/Dashboard', [
            'stats' => $stats,
            'selectedCompanyId' => $companyId,
            'companies' => $isMaster ? Company::query()->orderBy('name')->get(['id', 'name']) : [],
            'companyMetrics' => $companyMetrics,
            'accessMetrics' => $accessMetrics,
            'dailyUsage' => $dailyUsage,
            'recentAccess' => (clone $auditQuery)->with(['user:id,name', 'company:id,name'])->latest('created_at')->limit(12)->get([
                'id', 'user_id', 'company_id', 'action', 'route_name', 'method', 'path', 'status_code', 'outcome', 'created_at',
            ]),
            'recentProjects' => (clone $projects)->withCount('tasks')->when($isMaster && ! $companyId, fn ($query) => $query->with('company:id,name'))->latest()->limit(5)->get(['id', 'company_id', 'name', 'code', 'status', 'deadline']),
            'upcomingTasks' => (clone $tasks)->with(['project' => fn ($project) => $project->select('id', 'name', 'code')])->where('progress', '<', 100)->orderBy('end_at')->limit(8)->get(['id', 'project_id', 'name', 'status', 'progress', 'start_at', 'end_at']),
            'canManageCompany' => $request->user()->hasRole('admin', 'master'),
            'period' => $period,
        ]);
    }
}
