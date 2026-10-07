<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Quando o segundo fator é exigido (todos os usuários) e a pessoa ainda não o ativou só alcança a tela de
 * ativação (no Perfil) e o logout. Todo o resto redireciona para lá (ou responde 403 em chamadas de API).
 */
class RequireTwoFactorEnrollment
{
    private const ALLOWED_ROUTES = ['profile.edit', 'two-factor.*', 'logout'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->mustEnrollTwoFactor() || $request->routeIs(self::ALLOWED_ROUTES)) {
            return $next($request);
        }

        $message = 'Para continuar, ative a verificação em duas etapas (app autenticador) no seu perfil.';

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['success' => false, 'message' => $message, 'two_factor_required' => true], 403);
        }

        return redirect()->route('profile.edit')->with('warning', $message);
    }
}
