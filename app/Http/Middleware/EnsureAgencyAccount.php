<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Agency endpoints: role "agence" AND an agence row owned by this account.
 */
class EnsureAgencyAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || !$user->isAgency()) {
            return response()->json(['success' => false, 'message' => 'Accès réservé aux comptes agence.'], 403);
        }

        if (!$user->agence) {
            return response()->json([
                'success' => false,
                'message' => "Ce compte agence n'est rattaché à aucune agence.",
            ], 403);
        }

        return $next($request);
    }
}
