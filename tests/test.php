<?php
    declare(strict_types=1);

    use com\femastudios\jtrace\JTrace;
    use com\femastudios\jtrace\tests\Code;

    require_once __DIR__ . '/../vendor/autoload.php';


    /**
     * @throws Exception
     */
    function run_code() {
        (new Code())->run();
    }

    try {
        run_code();
    } catch (Exception $e) {
        JTrace::new()->printFromThrowable($e);
    }