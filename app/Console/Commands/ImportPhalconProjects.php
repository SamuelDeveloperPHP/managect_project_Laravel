<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PDO;
use RuntimeException;

class ImportPhalconProjects extends Command
{
    protected $signature = 'managect:import-phalcon {--dry-run : Validate and report without writing data}';

    protected $description = 'Importa projetos, tarefas Gantt, membros e responsáveis do banco Phalcon';

    public function handle(): int
    {
        $sourceConfig = config('phalcon.source');
        $password = (string) $sourceConfig['password'];
        if ($password === '') {
            $this->error('Defina PHALCON_SOURCE_PASSWORD no ambiente antes de iniciar a importação.');
            return self::FAILURE;
        }

        $host = (string) $sourceConfig['host'];
        $port = (int) $sourceConfig['port'];
        $database = (string) $sourceConfig['database'];
        $username = (string) $sourceConfig['username'];

        try {
            $source = new PDO("mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4", $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            $projects = $source->query('SELECT * FROM projects ORDER BY id')->fetchAll();
            $tasks = $source->query('SELECT * FROM gantt_tasks ORDER BY id')->fetchAll();
            $assignments = $source->query('SELECT * FROM gantt_task_assignments ORDER BY id')->fetchAll();
            $members = $source->query('SELECT * FROM project_members ORDER BY project_id, user_id')->fetchAll();
            $sourceCompanies = $source->query('SELECT id, name FROM companies ORDER BY id')->fetchAll();
            $sourceUsers = $source->query('SELECT id, company_id, email FROM users ORDER BY id')->fetchAll();

            $projectMap = $this->mapProjects($projects);
            $this->validateCompanies($sourceCompanies);
            $userMap = $this->mapUsers($sourceUsers);
            $companyIds = array_values(array_unique(array_map(static fn (array $row): int => (int) $row['company_id'], $tasks)));
            foreach ($companyIds as $companyId) {
                if (! DB::table('companies')->where('id', $companyId)->exists()) {
                    throw new RuntimeException("A empresa de origem {$companyId} não tem correspondência no destino.");
                }
            }

            if ($this->option('dry-run')) {
                $this->info(sprintf('Validação concluída: %d projeto(s), %d tarefa(s), %d responsável(is), %d vínculo(s) de equipe.', count($projects), count($tasks), count($assignments), count($members)));
                return self::SUCCESS;
            }

            $insertedProjects = $insertedTasks = $insertedAssignments = $insertedMembers = 0;
            DB::transaction(function () use ($projects, $tasks, $assignments, $members, $projectMap, $userMap, &$insertedProjects, &$insertedTasks, &$insertedAssignments, &$insertedMembers): void {
                foreach ($projects as $row) {
                    $projectId = $projectMap[(int) $row['id']];
                    $linked = DB::table('projects')->where('id', $projectId)->whereNull('phalcon_id')->update(['phalcon_id' => $row['id']]);
                    $insertedProjects += $linked ?: (int) DB::table('projects')->where('id', $projectId)->where('phalcon_id', $row['id'])->exists();
                    DB::table('projects')->where('id', $projectId)->whereNull('client')->update(['client' => $row['client']]);
                    DB::table('projects')->where('id', $projectId)->where('priority', 'medium')->update(['priority' => $row['priority']]);
                    DB::table('projects')->where('id', $projectId)->whereNull('leader_id')->update(['leader_id' => $userMap[(int) $row['leader_id']] ?? null]);
                    DB::table('projects')->where('id', $projectId)->whereNull('start_date')->update(['start_date' => $row['start_date']]);
                    DB::table('projects')->where('id', $projectId)->whereNull('deadline')->update(['deadline' => $row['deadline']]);
                    DB::table('projects')->where('id', $projectId)->whereNull('budget')->update(['budget' => $row['budget']]);
                    DB::table('projects')->where('id', $projectId)->whereNull('image_path')->update(['image_path' => $row['image_path']]);
                }

                foreach ($tasks as $row) {
                    $projectId = $row['project_id'] === null ? null : ($projectMap[(int) $row['project_id']] ?? null);
                    $existingTask = DB::table('gantt_tasks')->where('phalcon_id', $row['id'])->first();
                    if ($existingTask) {
                        $insertedTasks++;
                        continue;
                    }
                    $legacyTask = DB::table('gantt_tasks')->where('id', $row['id'])->first();
                    $taskData = [
                        'phalcon_id' => $row['id'], 'company_id' => $row['company_id'], 'project_id' => $projectId,
                        'code' => $row['code'], 'name' => $row['name'], 'description' => $row['description'], 'level' => $row['level'],
                        'status' => $row['status'], 'progress' => $row['progress'], 'start_at' => $row['start_at'], 'end_at' => $row['end_at'],
                        'duration' => max(1, (int) $row['duration']), 'depends' => $row['depends'], 'sort_order' => $row['sort_order'],
                        'collapsed' => $row['collapsed'], 'start_is_milestone' => $row['start_is_milestone'], 'end_is_milestone' => $row['end_is_milestone'],
                        'created_by' => $userMap[(int) $row['created_by']] ?? null, 'updated_by' => $userMap[(int) $row['updated_by']] ?? null,
                        'created_at' => $row['created_at'], 'updated_at' => $row['updated_at'],
                    ];
                    if ($legacyTask) {
                        if ((int) $legacyTask->company_id !== (int) $taskData['company_id']
                            || ($legacyTask->project_id === null ? null : (int) $legacyTask->project_id) !== $projectId
                            || (string) $legacyTask->name !== (string) $taskData['name']
                            || (string) $legacyTask->start_at !== (string) $taskData['start_at']
                            || (string) $legacyTask->end_at !== (string) $taskData['end_at']) {
                            throw new RuntimeException("A tarefa de destino {$row['id']} diverge da origem; a importação foi interrompida para evitar duplicação ou sobrescrita.");
                        }
                        DB::table('gantt_tasks')->where('id', $legacyTask->id)->whereNull('phalcon_id')->update(['phalcon_id' => $row['id']]);
                        $insertedTasks++;
                        continue;
                    }
                    $insertedTasks += DB::table('gantt_tasks')->insertOrIgnore($taskData);
                }

                foreach ($assignments as $row) {
                    $taskId = DB::table('gantt_tasks')->where('phalcon_id', $row['gantt_task_id'])->value('id');
                    $userId = $userMap[(int) $row['user_id']] ?? null;
                    if (! $taskId || ! $userId || ! DB::table('users')->where('id', $userId)->where('company_id', $row['company_id'])->exists()) {
                        continue;
                    }
                    if (DB::table('gantt_task_assignments')->where('phalcon_id', $row['id'])->exists()) {
                        $insertedAssignments++;
                        continue;
                    }
                    $existingAssignment = DB::table('gantt_task_assignments')->where('gantt_task_id', $taskId)->where('user_id', $userId)->where('role', $row['role'])->first();
                    $assignmentData = [
                        'phalcon_id' => $row['id'], 'gantt_task_id' => $taskId, 'company_id' => $row['company_id'],
                        'user_id' => $userId, 'role' => $row['role'], 'effort' => $row['effort'],
                        'created_at' => $row['created_at'], 'updated_at' => $row['updated_at'],
                    ];
                    if ($existingAssignment) {
                        DB::table('gantt_task_assignments')->where('id', $existingAssignment->id)->whereNull('phalcon_id')->update(['phalcon_id' => $row['id']]);
                        $insertedAssignments++;
                    } else {
                        $insertedAssignments += DB::table('gantt_task_assignments')->insertOrIgnore($assignmentData);
                    }
                }

                foreach ($members as $row) {
                    $projectId = $projectMap[(int) $row['project_id']] ?? null;
                    $userId = $userMap[(int) $row['user_id']] ?? null;
                    if (! $projectId || ! $userId) {
                        continue;
                    }
                    $existingMember = DB::table('project_members')->where('project_id', $projectId)->where('user_id', $userId)->first();
                    if ($existingMember) {
                        DB::table('project_members')->where('project_id', $projectId)->where('user_id', $userId)->whereNull('phalcon_project_id')->update(['phalcon_project_id' => $row['project_id']]);
                        $insertedMembers++;
                    } else {
                        $insertedMembers += DB::table('project_members')->insertOrIgnore([
                            'phalcon_project_id' => $row['project_id'], 'project_id' => $projectId, 'user_id' => $userId,
                            'created_at' => $row['created_at'],
                        ]);
                    }
                }
            }, 3);

            $this->info(sprintf('Importação concluída. Projetos vinculados: %d; tarefas vinculadas/importadas: %d; responsáveis vinculados/importados: %d; vínculos de equipe vinculados/importados: %d.', $insertedProjects, $insertedTasks, $insertedAssignments, $insertedMembers));
            return self::SUCCESS;
        } catch (\Throwable $exception) {
            report($exception);
            $this->error('Importação cancelada: não foi possível concluir a validação ou gravação. Consulte o log para detalhes técnicos.');
            return self::FAILURE;
        }
    }

    /** @return array<int, int> */
    private function mapProjects(array $projects): array
    {
        $map = [];
        foreach ($projects as $row) {
            $match = DB::table('projects')->where('phalcon_id', $row['id'])->first(['id', 'company_id', 'phalcon_id']);
            if (! $match) {
                $query = DB::table('projects')->where('company_id', $row['company_id']);
                $query = ! empty($row['code']) ? $query->where('code', $row['code']) : $query->where('id', $row['id']);
                $match = $query->first(['id', 'company_id', 'phalcon_id']);
            }
            if (! $match) {
                throw new RuntimeException("O projeto de origem {$row['id']} não tem correspondência inequívoca no destino; nenhum dado foi importado.");
            }
            if ((int) $match->company_id !== (int) $row['company_id']) {
                throw new RuntimeException('Um projeto de destino já está associado a outra empresa.');
            }
            if ($match->phalcon_id !== null && (int) $match->phalcon_id !== (int) $row['id']) {
                throw new RuntimeException('Um projeto de destino já está associado a outro ID Phalcon.');
            }
            $map[(int) $row['id']] = (int) $match->id;
        }
        return $map;
    }

    private function validateCompanies(array $sourceCompanies): void
    {
        foreach ($sourceCompanies as $company) {
            $destination = DB::table('companies')->where('id', $company['id'])->value('name');
            if ($destination === null || ! hash_equals(hash('sha256', (string) $company['name']), hash('sha256', (string) $destination))) {
                throw new RuntimeException("A empresa de origem {$company['id']} não corresponde à empresa do mesmo ID no destino.");
            }
        }
    }

    /** @return array<int, int> */
    private function mapUsers(array $sourceUsers): array
    {
        $map = [];
        foreach ($sourceUsers as $user) {
            $destination = DB::table('users')->whereRaw('LOWER(email) = ?', [mb_strtolower((string) $user['email'])])->first(['id', 'company_id']);
            if ($destination && (int) $destination->company_id === (int) $user['company_id']) {
                $map[(int) $user['id']] = (int) $destination->id;
            }
        }
        return $map;
    }
}
