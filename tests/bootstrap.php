<?php

require_once __DIR__ . '/../vendor/autoload.php';

// Stub Themosis\Hook\Hookable so hook classes can be instantiated without the full framework.
if (!class_exists('Themosis\Hook\Hookable')) {
    abstract class Hookable
    {
        abstract public function register(): void;
    }

    class_alias('Hookable', 'Themosis\Hook\Hookable');
}
