<?php

declare(strict_types=1);

use Crmleaf\Payroll\Tools\PayslipGenerator\Http\Controllers\PayslipGeneratorController;
use Illuminate\Support\Facades\Route;

/*
 * Loaded by PayslipGeneratorServiceProvider only when config('payslip-generator.route.enabled')
 * is true, so requiring the package never adds a URL on its own.
 */

/** @var \Illuminate\Contracts\Config\Repository $config */
$config = app('config');

Route::middleware((array) $config->get('payslip-generator.route.middleware', ['web']))
    ->prefix((string) $config->get('payslip-generator.route.prefix', 'tools'))
    ->group(static function () use ($config): void {
        Route::match(['get', 'post'], '/payslip-generator', PayslipGeneratorController::class)
            ->name((string) $config->get('payslip-generator.route.name', 'payslip-generator'));
    });
