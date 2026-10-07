<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Quem ainda não aceitou a versão vigente da Política de Privacidade e dos Termos de Uso só alcança a tela de
 * aceite, os próprios textos e o logout. Convidados por um administrador e todos que existiam antes desta regra
 * passam por aqui no próximo acesso; mudar `privacy.terms_version` repete o processo para todos.
 */
class RequireTermsAcceptance
{
    private const ALLOWED_ROUTES = ['terms.accept', 'terms.accept.store', 'legal.*', 'logout'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->mustAcceptTerms() || $request->routeIs(self::ALLOWED_ROUTES)) {
            return $next($request);
        }

        $message = 'Para continuar, leia e aceite a Política de Privacidade e os Termos de Uso.';

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['success' => false, 'message' => $message, 'terms_required' => true], 403);
        }

        return redirect()->route('terms.accept');
    }
}
