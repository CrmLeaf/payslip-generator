<?php

declare(strict_types=1);

namespace Crmleaf\Payroll\Tests\Calculators;

use Crmleaf\Payroll\Calculators\PayslipGenerator;
use Crmleaf\Payroll\Exceptions\InvalidInputException;
use Crmleaf\Payroll\Money;
use Crmleaf\Payroll\Results\PayslipResult;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PayslipGeneratorTest extends TestCase
{
    /**
     * Pinned to FY 2025-26 so the expected figures below stay fixed as rate
     * tables gain later versions. It is not the only date that works: the
     * generator called with no date at all is covered separately, in
     * test_works_without_an_explicit_date.
     */
    private const AS_OF = '2025-08-01';

    private PayslipGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new PayslipGenerator();
    }

    /**
     * ₹75,000 gross on a ₹30,000 basic, all 31 days of August 2025 paid, in
     * Karnataka, worked through by hand:
     *
     *   basic                                        30,000.00
     *   HRA                 50% × 30,000             15,000.00
     *   special allowance   75,000 − 30,000 − 15,000 30,000.00
     *   gross                                        75,000.00
     *
     *   PF (employee)       12% × min(30,000, 15,000) 1,800.00
     *   ESI                 nil, ₹75,000 is over the ₹21,000 wage limit
     *   professional tax    Karnataka, above ₹25,000     200.00
     *   TDS                 nil, ₹9,00,000 a year is inside the 87A rebate
     *   net                 75,000 − 2,000            73,000.00
     */
    public function testAFullMonthOnAKarnatakaPayroll(): void
    {
        $result = $this->payslip();

        self::assertSame('Asha Menon', $result->employeeName);
        self::assertSame('EMP-0001', $result->employeeCode);
        self::assertSame('2025-08', $result->payMonth->format('Y-m'));

        self::assertSame(31, $result->daysInMonth);
        self::assertSame(31, $result->daysPayable);
        self::assertSame(0, $result->lopDays);
        self::assertSame(31, $result->paidDays);

        self::assertSame(30_00_000, $result->basic->paise);
        self::assertSame(15_00_000, $result->hra->paise);
        self::assertSame(30_00_000, $result->specialAllowance->paise);
        self::assertSame(75_00_000, $result->grossEarnings->paise);

        self::assertSame(1_80_000, $result->providentFund->paise);
        self::assertSame(0, $result->stateInsurance->paise);
        self::assertFalse($result->stateInsuranceApplicable);
        self::assertSame(20_000, $result->professionalTax->paise);
        self::assertSame(0, $result->tds->paise);
        self::assertSame(2_00_000, $result->totalDeductions->paise);

        self::assertSame(73_00_000, $result->netPay->paise);
        self::assertSame('Rupees Seventy Three Thousand Only', $result->netPayInWords);
    }

    /**
     * The single property an employee checks first: the earnings column adds up.
     * Special allowance is the balancing figure, so this must hold to the paisa
     * whatever the proration rounding does - not merely to the rupee.
     *
     * @param float $gross monthly gross
     * @param float $basic monthly basic
     */
    #[DataProvider('componentCases')]
    public function testTheEarningsComponentsSumToTheGrossExactly(
        float $gross,
        float $basic,
        string $payMonth,
        int $daysPayable,
        int $lopDays,
    ): void {
        $result = $this->payslip(
            monthlyGross: $gross,
            monthlyBasic: $basic,
            payMonth: $payMonth,
            daysPayable: $daysPayable,
            lopDays: $lopDays,
        );

        self::assertSame(
            $result->grossEarnings->paise,
            $result->basic->add($result->hra, $result->specialAllowance)->paise,
            'basic + HRA + special allowance must reconstruct the gross exactly',
        );

        self::assertFalse($result->specialAllowance->isNegative(), 'no earnings head may go negative');

        $lines = array_reduce(
            $result->earningLines(),
            static fn (Money $carry, array $line) => $carry->add($line['amount']),
            Money::zero(),
        );

        self::assertSame($result->grossEarnings->paise, $lines->paise);
    }

    /**
     * @return array<string, array{0: float, 1: float, 2: string, 3: int, 4: int}>
     */
    public static function componentCases(): array
    {
        return [
            'full month' => [75_000, 30_000, '2025-08-01', 31, 0],
            'february, two days lost' => [75_000, 30_000, '2026-02-01', 28, 2],
            'september, five days lost' => [75_000, 30_000, '2025-09-01', 30, 5],
            'awkward thirds' => [55_555, 22_222, '2025-08-01', 31, 7],
            'basic is the whole gross' => [40_000, 40_000, '2025-08-01', 31, 3],
            'basic is two thirds of gross' => [30_000, 20_000, '2025-11-01', 30, 1],
            'a single paid day' => [75_000, 30_000, '2025-08-01', 31, 30],
            'nothing paid at all' => [75_000, 30_000, '2025-08-01', 31, 31],
        ];
    }

    /**
     * Two days of loss of pay costs more in February than in August, because a
     * wage month is divided by its own length and never by a flat thirty. The
     * three expectations below are (75,000 × paid) ÷ days, rounded to the paisa.
     */
    #[DataProvider('prorationCases')]
    public function testLossOfPayIsProratedOnTheMonthsOwnLength(
        string $payMonth,
        int $daysInMonth,
        int $lopDays,
        int $expectedPaidDays,
        int $expectedGrossPaise,
        int $expectedBasicPaise,
    ): void {
        $result = $this->payslip(
            payMonth: $payMonth,
            daysPayable: $daysInMonth,
            lopDays: $lopDays,
        );

        self::assertSame($daysInMonth, $result->daysInMonth);
        self::assertSame($expectedPaidDays, $result->paidDays);
        self::assertSame($expectedGrossPaise, $result->grossEarnings->paise);
        self::assertSame($expectedBasicPaise, $result->basic->paise);
    }

    /**
     * @return array<string, array{0: string, 1: int, 2: int, 3: int, 4: int, 5: int}>
     */
    public static function prorationCases(): array
    {
        return [
            // (75,000 × 26) ÷ 28 = 69,642.857…, and (30,000 × 26) ÷ 28 = 27,857.142…
            'february, 28 days' => ['2026-02-01', 28, 2, 26, 69_64_286, 27_85_714],
            // (75,000 × 28) ÷ 30 lands exactly on 70,000.
            'september, 30 days' => ['2025-09-01', 30, 2, 28, 70_00_000, 28_00_000],
            // (75,000 × 29) ÷ 31 = 70,161.290…
            'august, 31 days' => ['2025-08-01', 31, 2, 29, 70_16_129, 28_06_452],
            'february, none lost' => ['2026-02-01', 28, 0, 28, 75_00_000, 30_00_000],
            'september, none lost' => ['2025-09-01', 30, 0, 30, 75_00_000, 30_00_000],
            'august, none lost' => ['2025-08-01', 31, 0, 31, 75_00_000, 30_00_000],
        ];
    }

    /**
     * The proration is a line of the working in its own right. A slip that
     * quietly pays 26/28ths of a salary and prints only the answer is the
     * commonest payroll support ticket there is.
     */
    public function testTheProrationIsShownAsAStepOfItsOwn(): void
    {
        $result = $this->payslip(payMonth: '2026-02-01', daysPayable: 28, lopDays: 2);

        $labels = array_map(static fn ($step) => $step->label, $result->steps());
        $formulae = array_map(static fn ($step) => (string) $step->formula, $result->steps());

        self::assertContains('Gross prorated for 2 days of loss of pay', $labels);
        self::assertContains('Wage month February 2026', $labels);
        self::assertStringContainsString(
            '(₹75,000.00 × 26) ÷ 28',
            implode(' | ', $formulae),
            'the working must show the divisor it actually used',
        );
    }

    /**
     * The form ships a days-payable default of 31, because most months have 31
     * days. February must not therefore pay 31/28ths of a salary.
     */
    public function testDaysPayableCannotExceedTheMonthItself(): void
    {
        $result = $this->payslip(payMonth: '2026-02-01');

        self::assertSame(28, $result->daysPayable);
        self::assertSame(28, $result->paidDays);
        self::assertSame(75_00_000, $result->grossEarnings->paise);

        self::assertStringContainsString(
            'February 2026 has only 28 days',
            implode(' ', $result->notes),
            'capping the day count must be said out loud, not done silently',
        );
    }

    /**
     * Delhi has never enacted a professional tax. That is a valid nil line, not
     * an unsupported state and not an exception.
     */
    public function testAStateThatLeviesNoProfessionalTaxDeductsNone(): void
    {
        $result = $this->payslip(state: 'Delhi');

        self::assertSame('Delhi', $result->state);
        self::assertSame(0, $result->professionalTax->paise);
        self::assertFalse($result->professionalTaxDetail->levied);

        // Only PF is left, so the net is ₹200 higher than the Karnataka slip.
        self::assertSame(1_80_000, $result->totalDeductions->paise);
        self::assertSame(73_20_000, $result->netPay->paise);

        $labels = array_map(static fn (array $line) => $line['label'], $result->deductionLines());
        self::assertNotContains('Professional tax (Delhi)', $labels);
    }

    /**
     * Maharashtra charges ₹300 in February so that the year lands on the ₹2,500
     * Article 276 cap, which is why the pay month's number has to reach the
     * professional tax calculator rather than a hardcoded April.
     */
    #[DataProvider('maharashtraMonths')]
    public function testTheWageMonthDrivesTheProfessionalTaxSchedule(
        string $payMonth,
        int $daysPayable,
        int $expectedProfessionalTaxPaise,
    ): void {
        $result = $this->payslip(payMonth: $payMonth, daysPayable: $daysPayable, state: 'Maharashtra');

        self::assertSame($expectedProfessionalTaxPaise, $result->professionalTax->paise);
    }

    /**
     * @return array<string, array{0: string, 1: int, 2: int}>
     */
    public static function maharashtraMonths(): array
    {
        return [
            'an ordinary month' => ['2025-08-01', 31, 20_000],
            'february' => ['2026-02-01', 28, 30_000],
        ];
    }

    /**
     * ESI bites below the ₹21,000 wage limit and not a rupee above it. The
     * employee share is 0.75% of the wages actually earned, rounded up to the
     * whole rupee as rule 51 requires.
     */
    #[DataProvider('esiCases')]
    public function testEsiAppliesOnlyBelowTheWageLimit(
        float $gross,
        float $basic,
        bool $expectedApplicable,
        int $expectedEmployeePaise,
        int $expectedEmployerPaise,
    ): void {
        $result = $this->payslip(monthlyGross: $gross, monthlyBasic: $basic);

        self::assertSame($expectedApplicable, $result->stateInsuranceApplicable);
        self::assertSame($expectedEmployeePaise, $result->stateInsurance->paise);
        self::assertSame($expectedEmployerPaise, $result->employerEsi->paise);
    }

    /**
     * @return array<string, array{0: float, 1: float, 2: bool, 3: int, 4: int}>
     */
    public static function esiCases(): array
    {
        return [
            // 0.75% × 18,000 = 135.00; 3.25% × 18,000 = 585.00.
            'well below the limit' => [18_000, 9_000, true, 13_500, 58_500],
            // 0.75% × 21,000 = 157.50, rounded up to 158; 3.25% = 682.50 → 683.
            'exactly on the limit' => [21_000, 10_000, true, 15_800, 68_300],
            'one rupee over the limit' => [21_001, 10_000, false, 0, 0],
            'far over the limit' => [75_000, 30_000, false, 0, 0],
        ];
    }

    /**
     * Coverage is decided on the contracted monthly wage, the contribution on
     * what was earned. An employee on ₹18,000 who loses half of February is
     * still an ESI member; deciding coverage on the prorated figure would sign
     * people up and drop them month by month.
     */
    public function testEsiCoverageSurvivesLossOfPay(): void
    {
        $result = $this->payslip(
            monthlyGross: 18_000,
            monthlyBasic: 9_000,
            payMonth: '2026-02-01',
            daysPayable: 28,
            lopDays: 14,
        );

        self::assertTrue($result->stateInsuranceApplicable);
        self::assertSame(9_00_000, $result->grossEarnings->paise);
        // 0.75% × 9,000 = 67.50, rounded up to ₹68.
        self::assertSame(6_800, $result->stateInsurance->paise);
    }

    /**
     * PF is charged on the prorated basic against the ₹15,000 ceiling, and the
     * employer's own contributions belong on the annexure rather than in the
     * deductions column - they never come out of the net.
     */
    public function testTheEmployerContributionsAreReportedButNotDeducted(): void
    {
        $result = $this->payslip();

        // 12% of the ₹15,000 ceiling is ₹1,800; EPS takes min(8.33% × 15,000,
        // ₹1,250) = ₹1,250 of it and EPF the ₹550 remainder. EDLI is 0.5% of the
        // same ₹15,000 = ₹75.
        self::assertSame(1_25_000, $result->employerEps->paise);
        self::assertSame(55_000, $result->employerEpf->paise);
        self::assertSame(1_80_000, $result->employerPf->paise);
        self::assertSame(7_500, $result->employerEdli->paise);
        self::assertSame(1_87_500, $result->totalEmployerContributions->paise);

        self::assertSame(
            $result->grossEarnings->subtract($result->totalDeductions)->paise,
            $result->netPay->paise,
            'the employer contributions must not touch the net',
        );

        // Administration charges are a per-establishment minimum, so a single
        // employee's annexure must not carry the ₹500 floor.
        self::assertSame(0, $result->pfDetail->adminCharges->paise);
    }

    /**
     * The two columns of the slip have to add to the two totals, line by line,
     * and the totals have to give the net.
     */
    public function testTheColumnsReconcileToTheNet(): void
    {
        $result = $this->payslip(monthlyGross: 18_000, monthlyBasic: 9_000, lopDays: 3);

        $earnings = array_reduce(
            $result->earningLines(),
            static fn (Money $carry, array $line) => $carry->add($line['amount']),
            Money::zero(),
        );

        $deductions = array_reduce(
            $result->deductionLines(),
            static fn (Money $carry, array $line) => $carry->add($line['amount']),
            Money::zero(),
        );

        self::assertSame($result->grossEarnings->paise, $earnings->paise);
        self::assertSame($result->totalDeductions->paise, $deductions->paise);
        self::assertSame($earnings->subtract($deductions)->paise, $result->netPay->paise);
    }

    /**
     * A month lost entirely still owes the year's tax instalment, so the slip
     * can end in a recovery. Flooring that at zero would hide it.
     */
    public function testAMonthWhollyLostToLeaveShowsARecovery(): void
    {
        $result = $this->payslip(
            monthlyGross: 200_000,
            monthlyBasic: 80_000,
            daysPayable: 31,
            lopDays: 31,
        );

        self::assertSame(0, $result->paidDays);
        self::assertSame(0, $result->grossEarnings->paise);
        self::assertSame(24_37_500, $result->tds->paise);
        self::assertSame(-24_37_500, $result->netPay->paise);
        self::assertStringStartsWith('Minus Rupees ', $result->netPayInWords);
        self::assertStringContainsString('recoverable rather than payable', implode(' ', $result->notes));
    }

    /**
     * Indian numbering, with the "and" a cheque puts before the final pair of
     * digits. The lakh boundary is where a naive million-based implementation
     * gives itself away.
     */
    #[DataProvider('wordCases')]
    public function testAmountsAreWrittenInIndianNumbering(int $paise, string $expected): void
    {
        self::assertSame($expected, PayslipResult::amountInWords(Money::fromPaise($paise)));
    }

    /**
     * @return array<string, array{0: int, 1: string}>
     */
    public static function wordCases(): array
    {
        return [
            'nothing at all' => [0, 'Rupees Zero Only'],
            'the example on the tin' => [42_350_00, 'Rupees Forty Two Thousand Three Hundred and Fifty Only'],
            'a round hundred' => [1_00_00, 'Rupees One Hundred Only'],
            'just under a lakh' => [99_999_00, 'Rupees Ninety Nine Thousand Nine Hundred and Ninety Nine Only'],
            'exactly a lakh' => [1_00_000_00, 'Rupees One Lakh Only'],
            'a rupee over a lakh' => [1_00_001_00, 'Rupees One Lakh and One Only'],
            'past the lakh, with a tail' => [
                1_23_456_00,
                'Rupees One Lakh Twenty Three Thousand Four Hundred and Fifty Six Only',
            ],
            'a crore' => [1_00_00_000_00, 'Rupees One Crore Only'],
            'crores and lakhs' => [2_50_00_000_00, 'Rupees Two Crore Fifty Lakh Only'],
            // Above a hundred crore the grouping keeps counting in crores -
            // there is no larger unit in the Indian system to reach for.
            'above a hundred crore' => [
                1_01_00_00_000_00,
                'Rupees One Hundred and One Crore Only',
            ],
            'with paise' => [
                42_350_75,
                'Rupees Forty Two Thousand Three Hundred and Fifty and Seventy Five Paise Only',
            ],
            'paise alone' => [50, 'Rupees Zero and Fifty Paise Only'],
            'a recovery' => [-1_234_56, 'Minus Rupees One Thousand Two Hundred and Thirty Four and Fifty Six Paise Only'],
        ];
    }

    /**
     * The words on the slip are the words for its own net, zero paise and all.
     */
    public function testTheSlipCarriesItsNetInWords(): void
    {
        $whole = $this->payslip();
        self::assertSame(0, $whole->netPay->paise % 100, 'this fixture is meant to land on whole rupees');
        self::assertSame('Rupees Seventy Three Thousand Only', $whole->netPayInWords);

        $fractional = $this->payslip(payMonth: '2026-02-01', daysPayable: 28, lopDays: 2);
        self::assertSame(
            'Rupees Sixty Seven Thousand Six Hundred and Forty Two and Eighty Six Paise Only',
            $fractional->netPayInWords,
        );
    }

    /**
     * Rates default to the wage month, so a slip reissued years later still
     * applies the law that was in force when the wages were earned.
     */
    public function testTheRateVersionDefaultsToTheWageMonth(): void
    {
        $implicit = $this->generator->calculate(
            employeeName: 'Asha Menon',
            monthlyGross: Money::fromRupees(75_000),
            monthlyBasic: Money::fromRupees(30_000),
            payMonth: '2025-08-01',
        );

        self::assertSame($this->payslip()->netPay->paise, $implicit->netPay->paise);
    }

    public function testAStringWageMonthIsAcceptedJustAsADateIs(): void
    {
        $fromString = $this->payslip(payMonth: '2025-09-15');
        $fromDate = $this->generator->calculate(
            employeeName: 'Asha Menon',
            monthlyGross: Money::fromRupees(75_000),
            monthlyBasic: Money::fromRupees(30_000),
            payMonth: new \DateTimeImmutable('2025-09-15'),
            asOf: self::AS_OF,
        );

        self::assertSame(30, $fromString->daysInMonth);
        self::assertSame($fromString->netPay->paise, $fromDate->netPay->paise);
    }

    /**
     * Every statutory deduction has to say which enactment put it there.
     */
    public function testEveryStatutoryStepCarriesItsCitation(): void
    {
        $result = $this->payslip(monthlyGross: 18_000, monthlyBasic: 9_000);

        self::assertSame(
            'Payment of Wages Act 1936, section 13A read with the Code on Wages 2019 - '
            .'the particulars a wage slip must carry',
            $result->citations()[0] ?? null,
        );

        $citations = implode(' ', $result->citations());

        self::assertStringContainsString("Employees' Provident Funds", $citations);
        self::assertStringContainsString("Employees' State Insurance Act", $citations);
        self::assertStringContainsString('Article 276', $citations);
        self::assertStringContainsString('Income-tax Act', $citations);
    }

    /**
     * toArray() is what the Blade component and the PDF render, so it has to
     * carry the table rows as well as the scalars.
     */
    public function testTheArrayIsShapedForAPayslipTable(): void
    {
        $data = $this->payslip(designation: 'Senior Engineer')->toArray();

        self::assertSame('Asha Menon', $data['employee_name']);
        self::assertSame('Senior Engineer', $data['designation']);
        self::assertSame('2025-08', $data['pay_month']);
        self::assertSame('August 2025', $data['pay_month_label']);
        self::assertSame(31, $data['days_in_month']);
        self::assertSame(31, $data['paid_days']);

        self::assertSame(
            ['Basic', 'House rent allowance', 'Special allowance'],
            array_column($data['earnings'], 'label'),
        );
        self::assertSame(
            ['Provident fund (employee share)', 'Professional tax (Karnataka)'],
            array_column($data['deductions'], 'label'),
        );
        self::assertSame(
            ['Provident fund - EPF (A/c 1)', 'Provident fund - EPS (A/c 10)', 'EDLI (A/c 21)'],
            array_column($data['employer_contributions'], 'label'),
        );

        self::assertSame(73000.0, $data['net_pay']);
        self::assertSame('₹73,000.00', $data['net_pay_formatted']);
        self::assertSame('Rupees Seventy Three Thousand Only', $data['net_pay_in_words']);

        // Every scalar figure needs its formatted twin: the generic renderer
        // pairs `x` with `x_formatted` and prints the raw float otherwise.
        foreach (['gross_earnings', 'total_deductions', 'net_pay', 'basic', 'hra', 'special_allowance'] as $key) {
            self::assertArrayHasKey($key.'_formatted', $data);
        }
    }

    #[DataProvider('rejectedInput')]
    public function testCallerErrorsAreRejectedRatherThanGuessedAt(callable $call): void
    {
        $this->expectException(InvalidInputException::class);

        $call($this->generator);
    }

    /**
     * @return array<string, array{0: \Closure}>
     */
    public static function rejectedInput(): array
    {
        $base = static fn (array $overrides) => static fn (PayslipGenerator $generator) => $generator->calculate(...(
            $overrides + [
                'employeeName' => 'Asha Menon',
                'monthlyGross' => Money::fromRupees(75_000),
                'monthlyBasic' => Money::fromRupees(30_000),
                'payMonth' => '2025-08-01',
                'asOf' => self::AS_OF,
            ]
        ));

        return [
            'no employee name' => [$base(['employeeName' => '   '])],
            'negative gross' => [$base(['monthlyGross' => Money::fromRupees(-1)])],
            'negative basic' => [$base(['monthlyBasic' => Money::fromRupees(-1)])],
            'basic above gross' => [$base(['monthlyBasic' => Money::fromRupees(80_000)])],
            'negative days payable' => [$base(['daysPayable' => -1])],
            'negative loss of pay' => [$base(['lopDays' => -1])],
            'more loss of pay than days payable' => [$base(['daysPayable' => 10, 'lopDays' => 11])],
            'an unparsable wage month' => [$base(['payMonth' => 'the middle of last year'])],
        ];
    }

    /**
     * Every other test here pins a date, which keeps the figures stable but
     * never exercises the path a first-time caller takes. This generator reads
     * four rate tables; if any of them stops covering today, that path throws
     * while the rest of the suite stays green.
     *
     * The assertion is deliberately weak - the figures depend on whichever
     * versions are in force, and pinning them here would defeat the purpose.
     * What is being tested is that the call returns at all.
     */
    public function test_works_without_an_explicit_date(): void
    {
        $result = $this->generator->calculate(
            employeeName: 'Asha Menon',
            monthlyGross: Money::fromRupees(75_000),
            monthlyBasic: Money::fromRupees(30_000),
            payMonth: date('Y-m-01'),
        );

        self::assertTrue($result->netPay->isPositive());
        self::assertNotSame('', $result->explain());
    }

    private function payslip(
        float $monthlyGross = 75_000,
        float $monthlyBasic = 30_000,
        string $payMonth = '2025-08-01',
        int $daysPayable = 31,
        int $lopDays = 0,
        string $state = 'Karnataka',
        string $designation = '',
    ): PayslipResult {
        return $this->generator->calculate(
            employeeName: 'Asha Menon',
            monthlyGross: Money::fromRupees($monthlyGross),
            monthlyBasic: Money::fromRupees($monthlyBasic),
            payMonth: $payMonth,
            designation: $designation,
            daysPayable: $daysPayable,
            lopDays: $lopDays,
            state: $state,
            asOf: self::AS_OF,
        );
    }
}
