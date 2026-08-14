<?php

use App\Http\Middleware\DefinirEquipePermissions;
use App\Http\Middleware\ForcerChangementMotDePasse;
use App\Http\Middleware\VerifieHabilitation;
use App\Http\Middleware\VerifierCompteActif;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        /*
         * Hébergement mutualisé (LWS, cPanel, OVH…) : le certificat HTTPS est porté par
         * un serveur frontal qui transmet ensuite la requête à Apache en clair. Sans cette
         * déclaration, Laravel croit être en http, fabrique des liens et des redirections
         * en http, que le frontal renvoie en https — d'où la boucle « cette page vous a
         * redirigé un trop grand nombre de fois ».
         *
         * L'en-tête X-Forwarded-Host est volontairement exclu : c'est celui qui permettrait
         * à un visiteur de forger l'adresse d'un lien de réinitialisation de mot de passe.
         * Le nom de domaine reste donc celui de APP_URL, quoi qu'annonce le frontal.
         *
         * Le « ?: » retombe sur « * » aussi bien quand la variable est absente que
         * lorsqu'elle est présente mais vide : une ligne « PROXYS_DE_CONFIANCE= »
         * oubliée dans un .env ne peut donc pas ramener la boucle de redirection.
         */
        $middleware->trustProxies(
            at: env('PROXYS_DE_CONFIANCE') ?: '*',
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO
                | Request::HEADER_X_FORWARDED_PREFIX,
        );

        $middleware->appendToGroup('web', [
            DefinirEquipePermissions::class,
            VerifierCompteActif::class,
            ForcerChangementMotDePasse::class,
        ]);

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'habilitation' => VerifieHabilitation::class,
        ]);

        /*
         * Le middleware "guest" natif de Laravel (utilisé par les routes de connexion
         * de Fortify) ignore config('fortify.home') : celui-ci ne joue qu'après la
         * soumission du formulaire. Sans ce réglage, un utilisateur déjà connecté qui
         * rouvre /login dans un second onglet (session partagée) retombe sur le repli
         * par défaut de Laravel — ici la racine "/", qui renvoie elle-même vers
         * /connexion puis /login : boucle infinie (ERR_TOO_MANY_REDIRECTS).
         */
        $middleware->redirectUsersTo(fn () => route('redirection'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        /*
         * Deux onglets du même navigateur partagent un seul cookie de session : se
         * connecter à un second compte dans l'onglet B remplace aussi la session de
         * l'onglet A, resté ouvert sur une page dont le rôle ne correspond plus à qui
         * est réellement connecté. La prochaine action dans cet onglet A (rafraîchir,
         * cliquer un lien) déclenche alors un 403 "User does not have the right roles"
         * — techniquement correct, mais incompréhensible pour l'utilisateur, qui n'a
         * rien fait de mal. On le renvoie plutôt vers l'espace du compte réellement
         * connecté, comme le fait déjà /redirection après une connexion normale.
         */
        $exceptions->render(function (\Spatie\Permission\Exceptions\UnauthorizedException $e, Request $request) {
            if ($request->expectsJson() || ! auth()->check()) {
                return null;
            }

            return redirect()->route('redirection');
        });
    })->create();
