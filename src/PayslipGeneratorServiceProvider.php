<?php

declare(strict_types=1);

namespace Crmleaf\Payroll\Tools\PayslipGenerator;

use Crmleaf\Payroll\Calculators\PayslipGenerator;
use Crmleaf\Payroll\Tools\PayslipGenerator\Documents\PayslipGeneratorDocument;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

/**
 * Registers Payslip Generator with a Laravel application.
 *
 * Everything this provider adds is either inert or off by default: the
 * calculator binding, one Blade component and a set of publishable paths. The
 * HTTP route is opt-in through `config('payslip-generator.route.enabled')`, because a
 * package that installs a public URL into your application without being asked
 * is a package that has made a routing decision on your behalf.
 */
final class PayslipGeneratorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/payslip-generator.php', 'payslip-generator');

        // A singleton because the calculator is stateless and its rate
        // repository parses the statutory tables once per process.
        $this->app->singleton(PayslipGenerator::class, static fn (): PayslipGenerator => new PayslipGenerator());

        $this->app->bind(PayslipGeneratorDocument::class, static fn (): PayslipGeneratorDocument => new PayslipGeneratorDocument());
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'payslip-generator');

        // One component per tool: resources/views/components/payslip-generator.blade.php,
        // written as <x-crmleaf::payslip-generator />. Every tool registers the same
        // 'crmleaf' prefix, so fifteen independently installed packages share one
        // component namespace instead of contributing fifteen aliases.
        Blade::anonymousComponentPath(__DIR__.'/../resources/views/components', 'crmleaf');

        if ($this->routeEnabled()) {
            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        }

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/payslip-generator.php' => config_path('payslip-generator.php'),
            ], 'payslip-generator-config');

            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/payslip-generator'),
            ], 'payslip-generator-views');

            $this->publishes([
                __DIR__.'/../resources/assets' => public_path('vendor/payslip-generator'),
            ], 'payslip-generator-assets');
        }
    }

    /**
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [
            PayslipGenerator::class,
            PayslipGeneratorDocument::class,
        ];
    }

    private function routeEnabled(): bool
    {
        /** @var \Illuminate\Contracts\Config\Repository $config */
        $config = $this->app->make('config');

        return (bool) $config->get('payslip-generator.route.enabled', false);
    }
}
