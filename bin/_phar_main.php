#!/usr/bin/env php
<?php
define('COWORKER_PHAR', preg_replace(';^phar://;', '', dirname(__DIR__)));
require_once __DIR__ . '/coworker';
