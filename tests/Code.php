<?php
    declare(strict_types=1);

    namespace com\femastudios\jtrace\tests;

    final class Code {

        function runInner() {
            throw new \Exception('Inner message', 101);
        }

        function run() {
            try {
                $this->runInner();
            } catch (\Exception $e) {
                throw new \LogicException('Inner threw', 0, $e);
            }
        }
    }