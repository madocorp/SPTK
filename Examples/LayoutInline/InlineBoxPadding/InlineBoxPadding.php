<?php

define('SPTK\DEBUG', false);
define('APP_PATH', __FILE__);
define('APP_NAMESPACE', 'EXAMPLES');
define('SPTK_PATH', realpath(__DIR__ . '/../../..'));

require_once SPTK_PATH . '/Autoload.php';

new SPTK\App(__DIR__ . "/layout.xml", __DIR__ . "/style.xss");
