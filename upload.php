<?php

set_time_limit(0);
date_default_timezone_set('UTC');

require "vendor/autoload.php";
require "config.php";

$uploader = new \Api\Uploader\Uploader($config);
$uploader->update();
