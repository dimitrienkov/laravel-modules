<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Pest Bootstrap
|--------------------------------------------------------------------------
|
| Unit and Feature suites boot the Laravel application via Orchestra
| Testbench. The Architecture suite intentionally does NOT boot Laravel
| — pest-plugin-arch works through reflection and stays fast.
|
*/

uses(Orchestra\Testbench\TestCase::class)->in('Unit', 'Feature');
