<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ClearValidationErrors
{
    /**
     * Handle an incoming request.
     * 
     * Deze middleware zorgt ervoor dat validation errors uit de sessie worden gewist
     * wanneer een gebruiker een formulier pagina bezoekt via een GET request.
     * Dit voorkomt dat oude validation errors worden getoond bij nieuwe formulieren.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Alleen uitvoeren op GET requests naar create/edit pagina's
        if ($request->isMethod('GET') && $this->isFormPage($request)) {
            // Wis alle validation gerelateerde sessie data
            $request->session()->forget(['errors', '_old_input']);
            
            // Wis flash data voor errors
            $request->session()->flash('errors', new \Illuminate\Support\ViewErrorBag());
            
            // Force regenereer de errors ViewErrorBag als leeg
            view()->share('errors', new \Illuminate\Support\ViewErrorBag());
        }

        return $next($request);
    }

    /**
     * Bepaal of de huidige route een formulier pagina is
     */
    private function isFormPage(Request $request): bool
    {
        $routeName = $request->route()?->getName();
        
        if (!$routeName) {
            return false;
        }

        // Routes die eindigen op .create of .edit zijn formulier pagina's
        return str_ends_with($routeName, '.create') || str_ends_with($routeName, '.edit');
    }
} 