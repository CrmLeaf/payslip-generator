<?php

declare(strict_types=1);

namespace Crmleaf\Payroll\Tools\PayslipGenerator\Tests;

use Crmleaf\Payroll\Calculators\PayslipGenerator;
use Crmleaf\Payroll\Tools\PayslipGenerator\PayslipGeneratorServiceProvider;
use Illuminate\Support\Facades\Blade;
use Orchestra\Testbench\TestCase;

/**
 * A smoke test for the package, not for the arithmetic.
 *
 * The statutory edge cases are covered where the maths lives, in
 * crmleaf/payroll-core. What has to be proven here is narrower and easy to
 * break: that the calculator this package wraps exists and takes the arguments
 * the generated controller passes it, that the provider boots on its own, that
 * the route stays off until it is asked for, and that the component renders.
 */
final class PayslipGeneratorTest extends TestCase
{
    /**
     * Held as a literal rather than as PayslipGenerator::class so that a calculator
     * which does not exist fails as one readable assertion instead of a fatal
     * error that takes the rest of the file with it.
     */
    private const CALCULATOR = 'Crmleaf\Payroll\Calculators\PayslipGenerator';

    private const METHOD = 'calculate';

    /**
     * @param \Illuminate\Foundation\Application $app
     *
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [PayslipGeneratorServiceProvider::class];
    }

    /**
     * @param \Illuminate\Foundation\Application $app
     */
    protected function defineEnvironment($app): void
    {
        // Enabled here so the HTTP surface is exercised at all. The shipped
        // default is off, which is what test_the_route_is_off_until_it_is_asked_for
        // pins down.
        $app['config']->set('payslip-generator.route.enabled', true);

        // Testbench boots a bare application with no .env, so the session
        // middleware the 'web' group pulls in has no key to work with. Generated
        // per run rather than hard-coded, because a literal key in a public
        // repository is a key somebody will eventually copy into production.
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
    }

    /**
     * Three tools once shipped naming a calculator nobody had written. Nothing
     * caught it because nothing ran this file; now that it runs, this is the
     * assertion that would have caught it.
     */
    public function test_the_calculator_it_wraps_exists_and_can_be_constructed(): void
    {
        self::assertTrue(
            class_exists(self::CALCULATOR),
            self::CALCULATOR.' is referenced by this package but does not exist in crmleaf/payroll-core.',
        );

        $calculator = new \ReflectionClass(self::CALCULATOR);

        self::assertTrue($calculator->isInstantiable(), self::CALCULATOR.' cannot be constructed.');

        // The service provider builds it with `new` and no arguments, so every
        // constructor dependency has to carry its own default.
        foreach ($calculator->getConstructor()?->getParameters() ?? [] as $parameter) {
            self::assertTrue(
                $parameter->isOptional(),
                sprintf('%s::__construct() requires $%s, so the provider cannot build it.', self::CALCULATOR, $parameter->getName()),
            );
        }
    }

    /**
     * Every field in stubs/tool/definitions.php has to be a named argument the
     * calculator accepts, because that is literally how the controller calls it:
     * `$this->calculator->calculate(...$request->payload())`.
     *
     * Renaming a field on one side only is otherwise invisible until a caller
     * gets an "unknown named parameter" error in production.
     */
    public function test_the_calculator_accepts_every_field_this_package_declares(): void
    {
        self::assertTrue(
            method_exists(self::CALCULATOR, self::METHOD),
            sprintf('%s::%s() is what this package calls, and it is not there.', self::CALCULATOR, self::METHOD),
        );

        $method = new \ReflectionMethod(self::CALCULATOR, self::METHOD);

        self::assertTrue($method->isPublic(), sprintf('%s::%s() must be public.', self::CALCULATOR, self::METHOD));
        self::assertFalse($method->isStatic(), sprintf('%s::%s() must not be static.', self::CALCULATOR, self::METHOD));

        $accepted = array_map(
            static fn (\ReflectionParameter $parameter): string => $parameter->getName(),
            $method->getParameters(),
        );

        $declared = [
            'employeeName',
            'employeeCode',
            'designation',
            'monthlyGross',
            'monthlyBasic',
            'payMonth',
            'daysPayable',
            'lopDays',
            'state',
            'asOf',
        ];

        foreach ($declared as $field) {
            self::assertContains($field, $accepted, sprintf(
                'This package sends $%s, but %s::%s() takes none such. Accepted: %s.',
                $field,
                self::CALCULATOR,
                self::METHOD,
                implode(', ', $accepted),
            ));
        }
    }

    /**
     * The other direction. A calculator that grows a parameter with no default
     * cannot be called by this package at all, because the request has no field
     * to put in it - and the failure would otherwise wait for whichever caller
     * happened to hit the new argument first.
     */
    public function test_the_calculator_needs_no_argument_this_package_cannot_send(): void
    {
        if (!method_exists(self::CALCULATOR, self::METHOD)) {
            self::fail(sprintf('%s::%s() does not exist, so there is nothing to compare against.', self::CALCULATOR, self::METHOD));
        }

        $declared = [
            'employeeName',
            'employeeCode',
            'designation',
            'monthlyGross',
            'monthlyBasic',
            'payMonth',
            'daysPayable',
            'lopDays',
            'state',
            'asOf',
        ];

        $required = [];

        foreach ((new \ReflectionMethod(self::CALCULATOR, self::METHOD))->getParameters() as $parameter) {
            if (!$parameter->isOptional()) {
                $required[] = $parameter->getName();
            }
        }

        // Asserted as one comparison rather than a loop so that a method whose
        // every parameter has a default still performs an assertion; an empty
        // loop body is a risky test, and failOnRisky is on.
        self::assertSame([], array_values(array_diff($required, $declared)), sprintf(
            '%s::%s() requires argument(s) that no field in stubs/tool/definitions.php declares.',
            self::CALCULATOR,
            self::METHOD,
        ));
    }

    public function test_the_calculator_resolves_from_the_container(): void
    {
        $calculator = $this->app->make(PayslipGenerator::class);

        self::assertInstanceOf(PayslipGenerator::class, $calculator);
        self::assertSame($calculator, $this->app->make(PayslipGenerator::class), 'The binding should be a singleton.');
    }

    public function test_the_configuration_is_merged(): void
    {
        self::assertSame('payslip-generator', $this->app['config']->get('payslip-generator.route.name'));
        self::assertSame('Payslip Generator', $this->app['config']->get('payslip-generator.view.title'));
    }

    public function test_the_route_is_off_until_it_is_asked_for(): void
    {
        /** @var array{route: array{enabled: bool}} $shipped */
        $shipped = require __DIR__.'/../config/payslip-generator.php';

        self::assertFalse($shipped['route']['enabled'], 'Requiring the package must not add a public URL.');
    }

    public function test_the_blade_component_renders(): void
    {
        $html = Blade::render('<x-crmleaf::payslip-generator />');

        self::assertStringContainsString('data-crmleaf-tool="payslip-generator"', $html);
        self::assertStringContainsString('data-crmleaf-form', $html);
    }

    public function test_the_route_answers_with_the_figures_and_the_working(): void
    {
        $response = $this->postJson('/tools/payslip-generator', [
            'employee_name' => 'Asha Menon',
            'monthly_gross' => 75000,
            'monthly_basic' => 30000,
            'pay_month' => '2025-08-01',
            // Pinned inside the shipped rate tables. This test is about the
            // package plumbing, not about rate coverage: a table that has run
            // out should fail payroll-core's suite, not fifteen mirrors at once.
            'as_of' => '2025-08-01',
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['tool', 'data', 'explain', 'working', 'citations']);
        $response->assertJsonPath('tool', 'payslip-generator');
    }

    public function test_incomplete_input_is_rejected_rather_than_guessed_at(): void
    {
        $this->postJson('/tools/payslip-generator', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['employee_name']);
    }
}
