<?php

class ProjectLayoutController extends Controller
{
    public function setLayout()
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        $host    = $this->f3->get('HOST');

        if ($referer && parse_url($referer, PHP_URL_HOST) === $host) {
            $this->f3->reroute($referer);
        } else {
            $this->f3->reroute($this->f3->get('BASE') . '/dashboard');
        }
    }
}
