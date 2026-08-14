<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Http\Response;
use Cake\Routing\Router;

class AirLandingController extends AppController
{
    /** Keep the legacy Cake AIR URL compatible without exposing its old landing. */
    public function index(): Response
    {
        return $this->redirect('/fly-ny', 302);
    }

    /** Render the canonical public AIR landing. */
    public function modern(): Response
    {
        $this->viewBuilder()->setLayout('landing_fullbleed');
        $this->prepareAirLandingView('/fly-ny');
        $this->response = $this->response
            ->withHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->withHeader('Pragma', 'no-cache')
            ->withHeader('Expires', '0');

        return $this->render('index');
    }

    private function prepareAirLandingView(string $brandHref): void
    {
        $session = $this->request->getSession();
        $lang = $this->landingLanguage();
        $flowQuery = '?tc6=1';
        $isPassengerAuthenticated = (bool)$session->read('passenger.authenticated')
            && trim((string)$session->read('passenger.auth_user')) !== '';

        $labels = match ($lang) {
            'en' => ['cases' => 'My cases', 'login' => 'Log in', 'logout' => 'Log out'],
            'fr' => ['cases' => 'Mes dossiers', 'login' => 'Connexion', 'logout' => 'Deconnexion'],
            default => ['cases' => 'Mine sager', 'login' => 'Log ind', 'logout' => 'Log ud'],
        };

        $railNav = [];
        if ($isPassengerAuthenticated) {
            $railNav[] = ['label' => $labels['cases'], 'href' => Router::url($this->localizedLandingUrl('/passenger/case', $lang), true), 'active' => false];
        } else {
            $railNav[] = ['label' => $labels['login'], 'href' => Router::url($this->localizedLandingUrl('/passenger/login', $lang), true), 'active' => false];
        }
        if ($isPassengerAuthenticated) {
            $railNav[] = ['label' => $labels['logout'], 'href' => Router::url($this->localizedLandingUrl('/passenger/logout', $lang), true), 'active' => false];
        }

        $quickLinks = [
            'ongoing' => Router::url($this->localizedLandingUrl('/flow/air/ongoing' . $flowQuery, $lang), true),
            'completed' => Router::url($this->localizedLandingUrl('/flow/air/completed' . $flowQuery, $lang), true),
            'beforeStart' => Router::url($this->localizedLandingUrl('/flow/air/before_start' . $flowQuery, $lang), true),
            'passenger' => Router::url($this->localizedLandingUrl('/passenger/case', $lang), true),
        ];

        $languageLinks = [];
        foreach (['da' => 'Dansk', 'en' => 'English', 'fr' => 'Francais'] as $code => $label) {
            $languageLinks[] = [
                'label' => $label,
                'href' => Router::url($this->localizedLandingUrl($brandHref, $code), true),
                'active' => $code === $lang,
            ];
        }
        $pageTranslations = [];
        $htmlLang = $lang;
        $fullBleedLanding = true;

        $this->set(compact(
            'railNav',
            'quickLinks',
            'fullBleedLanding',
            'brandHref',
            'lang',
            'languageLinks',
            'pageTranslations',
            'htmlLang'
        ));
    }

    private function landingLanguage(): string
    {
        $lang = strtolower(trim((string)($this->request->getQuery('lang') ?? 'da')));

        return in_array($lang, ['da', 'en', 'fr'], true) ? $lang : 'da';
    }

    private function localizedLandingUrl(string $path, string $lang): string
    {
        if ($lang === 'da') {
            return $path;
        }

        return $path . (str_contains($path, '?') ? '&' : '?') . 'lang=' . rawurlencode($lang);
    }
}
