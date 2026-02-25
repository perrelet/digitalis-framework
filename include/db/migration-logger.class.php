<?php

namespace Digitalis\DB;

class Migration_Logger {

    public function info    (string $message, array $context = []): void {}
    public function warning (string $message, array $context = []): void {}
    public function error   (string $message, array $context = []): void {}

}