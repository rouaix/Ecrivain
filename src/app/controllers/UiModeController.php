<?php

class UiModeController extends Controller
{
    public function switch()
    {
        $mode = $_POST['mode'] ?? 'classic';
        $mode = in_array($mode, ['classic', 'pro'], true) ? $mode : 'classic';

        setcookie('ui_mode', $mode, time() + 365 * 24 * 3600, '/', '', false, false);

        $referer = $_SERVER['HTTP_REFERER'] ?? '/dashboard';
        $path    = parse_url($referer, PHP_URL_PATH) ?: '/dashboard';

        $this->f3->reroute($path);
    }
}
