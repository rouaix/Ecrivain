<?php
$docRoot = dirname(__DIR__);
require_once $docRoot . '/vendor/autoload.php';
$f3 = Base::instance();
$f3->config($docRoot . '/app/config.ini');

echo "OPENAI_MODEL: " . $f3->get('OPENAI_MODEL') . "\n";
echo "OPENAI_API_KEY: " . substr($f3->get('OPENAI_API_KEY'), 0, 10) . "...\n";
