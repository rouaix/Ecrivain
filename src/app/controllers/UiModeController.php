<?php

class UiModeController extends Controller
{
    public function switch()
    {
        $mode = $_POST['mode'] ?? 'classic';
        $mode = in_array($mode, ['classic', 'pro'], true) ? $mode : 'classic';

        setcookie('ui_mode', $mode, time() + 365 * 24 * 3600, '/', '', false, false);

        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        $path    = parse_url($referer, PHP_URL_PATH) ?: '';

        // Enlever le préfixe BASE (/ecrivain) pour obtenir le chemin F3
        $base = rtrim($this->f3->get('BASE'), '/');
        if ($base !== '' && str_starts_with($path, $base)) {
            $path = substr($path, strlen($base));
        }

        if ($path === '' || $path === '/') {
            $path = '/dashboard';
        }

        $this->f3->reroute($path);
    }
}
