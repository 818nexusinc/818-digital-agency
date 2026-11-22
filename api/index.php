<?php
// Vercel entry point (vercel-php runtime). Every dynamic route is served by the regular front controller.
chdir(dirname(__DIR__) . '/public_html');
require dirname(__DIR__) . '/public_html/index.php';
