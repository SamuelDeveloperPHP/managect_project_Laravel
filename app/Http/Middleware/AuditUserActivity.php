<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Project;
use App\Models\ProjectBacklogItem;
use App\Models\User;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\HttpResponseException;
use Throwable;

class AuditUserActivity
{
    public function handle(Request $request, Closure $next): Response
    {
        $actorBefore = $request->user();
        $connection = DB::connection();
        $isMutation = ! in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true);

        if ($isMutation) {
            $connection->beginTransaction();
        }

        try {
            $response = $next($request);
        } catch (Throwable $exception) {
            if ($isMutation && $connection->transactionLevel() > 0) {
                $connection->rollBack();
            }
            $status = $this->exceptionStatus($exception);
            $this->record($request, $actorBefore ?? $request->user() ?? Auth::user(), $status, $status >= 500 ? 'error' : 'denied');
            throw $exception;
        }

        $actor = $actorBefore ?? $request->user() ?? Auth::user();
        $status = $response->getStatusCode();
        if ($actor === null && $request->isMethod('GET') && $status < 400) {
            if ($isMutation && $connection->transactionLevel() > 0) {
                $connection->commit();
            }

            return $response;
        }

        if ($isMutation && $status >= 400 && $connection->transactionLevel() > 0) {
            $connection->rollBack();
        }

        try {
            $this->record($request, $actor, $status, $status >= 400 ? 'denied' : 'success');
        } catch (Throwable $exception) {
            if ($isMutation && $connection->transactionLevel() > 0) {
                $connection->rollBack();
            }
            throw $exception;
        }

        if ($isMutation && $status < 400 && $connection->transactionLevel() > 0) {
            $connection->commit();
        }

        return $response;
    }

    private function exceptionStatus(Throwable $exception): int
    {
        if ($exception instanceof HttpExceptionInterface) {
            return $exception->getStatusCode();
        }
        if ($exception instanceof HttpResponseException) {
            return $exception->getResponse()->getStatusCode();
        }
        if ($exception instanceof AuthenticationException) {
            return 401;
        }
        if ($exception instanceof ValidationException) {
            return 422;
        }

        return 500;
    }

    private function record(Request $request, mixed $actor, int $status, string $outcome): void
    {
        $route = $request->route();
        $routeName = is_object($route) && method_exists($route, 'getName') ? $route->getName() : null;
        $routePath = is_object($route) && method_exists($route, 'uri') ? $route->uri() : '(unmatched route)';
        $action = $routeName ?: 'web.request';

        // Query values and request bodies are deliberately omitted to avoid logging secrets or personal data.
        $queryKeys = array_slice(array_keys($request->query()), 0, 30);
        $parameters = is_object($route) && method_exists($route, 'parameters') ? $route->parameters() : [];
        $entityType = null;
        $entityId = null;
        foreach (['project', 'item', 'user', 'company'] as $candidate) {
            if (array_key_exists($candidate, $parameters)) {
                $entityType = $candidate;
                $value = $parameters[$candidate];
                $entityId = is_numeric($value) ? (int) $value : (is_object($value) && method_exists($value, 'getKey') ? $value->getKey() : null);
                break;
            }
        }
        $entityType ??= 'route';

        $actorId = $actor?->getAuthIdentifier();
        $userExists = $actorId !== null && User::withTrashed()->whereKey($actorId)->exists();
        $auditCompanyId = $actor?->company_id;

        if ($actor?->hasRole('master')) {
            // A linked company is a reference for the platform account, not the
            // tenant scope for global activity. Attribute only explicitly scoped
            // or entity-specific master actions to a company.
            $auditCompanyId = null;
            $requestedCompanyId = $request->input('company_id', $request->query('company_id'));
            if (is_numeric($requestedCompanyId) && Company::query()->whereKey((int) $requestedCompanyId)->exists()) {
                $auditCompanyId = (int) $requestedCompanyId;
            } elseif ($entityId !== null) {
                $entityCompanyId = match ($entityType) {
                    'company' => $entityId,
                    'user' => User::withTrashed()->whereKey($entityId)->value('company_id'),
                    'project' => Project::withoutGlobalScopes()->whereKey($entityId)->value('company_id'),
                    'item' => ProjectBacklogItem::withoutGlobalScopes()->whereKey($entityId)->value('company_id'),
                    default => null,
                };
                if ($entityCompanyId !== null) {
                    $auditCompanyId = (int) $entityCompanyId;
                }
            }

            if ($auditCompanyId === null && $request->session()->has('master_company_id')) {
                $selectedCompanyId = (int) $request->session()->get('master_company_id');
                if (Company::query()->whereKey($selectedCompanyId)->where('is_active', true)->exists()) {
                    $auditCompanyId = $selectedCompanyId;
                }
            }
        }

        AuditLog::query()->create([
            'user_id' => $userExists ? $actorId : null,
            'company_id' => $auditCompanyId,
            'action' => mb_substr($action, 0, 80),
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'description' => mb_substr($request->method().' /'.$routePath, 0, 500),
            'route_name' => $routeName,
            'method' => $request->method(),
            'path' => mb_substr('/'.$routePath, 0, 2000),
            'status_code' => $status,
            'outcome' => $outcome,
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
            'metadata' => [
                'query_keys' => $queryKeys,
                'actor_role' => $actor?->role,
                'actor_id' => $actorId,
                'request_id' => $request->attributes->get('request_id'),
            ],
            'created_at' => now(),
        ]);
    }
}
