<?php

declare(strict_types=1);

namespace Crmleaf\Payroll\Calculators;

use Crmleaf\Payroll\Contracts\Step;
use Crmleaf\Payroll\Exceptions\InvalidInputException;
use Crmleaf\Payroll\Money;
use Crmleaf\Payroll\Rates\RateRepository;
use Crmleaf\Payroll\Results\PayslipResult;

/**
 * A wage slip for one employee for one wage month.
 *
 * Section 13A of the Payment of Wages Act 1936 and the Code on Wages 2019 fix
 * what a slip has to carry: who was paid, for which period and how many days of
 * it, the wages under each head, every deduction with its reason, and the net.
 * None of the arithmetic is invented here - provident fund, state insurance,
 * professional tax and tax at source each have their own calculator, and this
 * class decides only what to feed them for a single month.
 *
 * The one piece of judgement it does exercise is loss of pay. A month is
 * divided by its own length rather than by a flat thirty, and the proration is
 * printed as a step of its own, because an employee who loses two days of a
 * 28-day February loses more than two days of a 31-day March and is entitled to
 * see why.
 */
final class PayslipGenerator
{
    /**
     * What a wage slip is for, and the reason every line below has to be shown
     * rather than merely computed.
     */
    private const WAGE_SLIP_CITATION = 'Payment of Wages Act 1936, section 13A read with the Code on Wages '
        .'2019 - the particulars a wage slip must carry';

    /**
     * House rent allowance as a percentage of basic. The Income-tax Act does not
     * set the allowance, only the exemption - rule 2A allows up to half of
     * salary in the four metros and 40% elsewhere - so 50% of basic is a house
     * convention rather than a statutory quantity. It matches CtcCalculator's
     * default, which is what lets a payslip reconcile to the offer letter that
     * produced it.
     */
    private const HRA_PERCENT_OF_BASIC = 50;

    public function __construct(private readonly RateRepository $rates = new RateRepository())
    {
    }

    /**
     * @param Money $monthlyGross the full month's gross under the salary structure, before loss of pay
     * @param Money $monthlyBasic basic plus dearness allowance, the base for provident fund and HRA
     * @param \DateTimeImmutable|string $payMonth any date inside the wage month; its own calendar
     *                                            length is the divisor for loss of pay
     * @param int $daysPayable days the employee was on the roll in the wage month
     * @param int $lopDays loss-of-pay days inside those payable days
     * @param string $state the state whose professional tax schedule applies
     * @param \DateTimeImmutable|string|null $asOf overrides the rate version; defaults to the wage
     *                                             month, so a slip reissued years later still applies
     *                                             the law that was in force when the wages were earned
     */
    public function calculate(
        string $employeeName,
        Money $monthlyGross,
        Money $monthlyBasic,
        \DateTimeImmutable|string $payMonth,
        string $employeeCode = 'EMP-0001',
        string $designation = '',
        int $daysPayable = 31,
        int $lopDays = 0,
        string $state = 'Karnataka',
        \DateTimeImmutable|string|null $asOf = null,
    ): PayslipResult {
        $employeeName = trim($employeeName);
        $payMonth = $this->normalisePayMonth($payMonth);

        $this->validate($employeeName, $monthlyGross, $monthlyBasic, $daysPayable, $lopDays);

        $asOf ??= $payMonth;
        $notes = [];

        // The wage month's own length, never a flat thirty. Paying 2/30ths of a
        // February salary for two days of loss of pay short-changes the employee
        // by a fourteenth of the deduction.
        $daysInMonth = (int) $payMonth->format('t');

        if ($daysPayable > $daysInMonth) {
            // The form ships a default of 31 because most months have 31 days.
            // Carrying that into February would pay more than a whole month, so
            // it is capped - and said out loud, because a day count nobody
            // typed is exactly the sort of thing that turns into a query.
            $notes[] = sprintf(
                'Days payable was given as %d but %s has only %d days, so %d days were taken as payable.',
                $daysPayable,
                $payMonth->format('F Y'),
                $daysInMonth,
                $daysInMonth,
            );

            $daysPayable = $daysInMonth;
        }

        if ($lopDays > $daysPayable) {
            throw InvalidInputException::outOfRange(
                'Loss-of-pay days',
                sprintf('at most the %d days payable', $daysPayable),
                $lopDays,
            );
        }

        $paidDays = $daysPayable - $lopDays;

        $grossEarnings = $monthlyGross->multiply($paidDays)->divide($daysInMonth);
        $basic = $monthlyBasic->multiply($paidDays)->divide($daysInMonth);

        // HRA is taken on the prorated basic and the allowance absorbs the
        // remainder, so the three heads add back to the month's gross to the
        // paise however the rounding falls. An employee checking the arithmetic
        // adds the earnings column first, and it has to agree.
        $hra = $basic->percentage(self::HRA_PERCENT_OF_BASIC)->min($grossEarnings->subtract($basic)->floorAtZero());
        $specialAllowance = $grossEarnings->subtract($basic, $hra);

        $steps = [
            new Step(
                label: sprintf('Wage month %s', $payMonth->format('F Y')),
                formula: sprintf(
                    '%d days in the month, %d payable, %d of loss of pay → %d paid days',
                    $daysInMonth,
                    $daysPayable,
                    $lopDays,
                    $paidDays,
                ),
                citation: self::WAGE_SLIP_CITATION,
                context: [
                    'days_in_month' => $daysInMonth,
                    'days_payable' => $daysPayable,
                    'lop_days' => $lopDays,
                    'paid_days' => $paidDays,
                ],
            ),
            new Step(
                label: $lopDays > 0
                    ? sprintf('Gross prorated for %d day%s of loss of pay', $lopDays, $lopDays === 1 ? '' : 's')
                    : 'Gross for the full month',
                amount: $grossEarnings,
                formula: sprintf(
                    '(%s × %d) ÷ %d, on the actual length of %s',
                    $monthlyGross->format(),
                    $paidDays,
                    $daysInMonth,
                    $payMonth->format('F Y'),
                ),
                citation: self::WAGE_SLIP_CITATION,
            ),
            new Step(
                label: 'Basic',
                amount: $basic,
                formula: sprintf('(%s × %d) ÷ %d', $monthlyBasic->format(), $paidDays, $daysInMonth),
            ),
            new Step(
                label: 'House rent allowance',
                amount: $hra,
                formula: sprintf('%d%% × %s', self::HRA_PERCENT_OF_BASIC, $basic->format()),
                context: ['basis' => 'basic'],
            ),
            new Step(
                label: 'Special allowance (balancing figure)',
                amount: $specialAllowance,
                formula: sprintf('%s − %s − %s', $grossEarnings->format(), $basic->format(), $hra->format()),
            ),
            new Step(
                label: 'Gross earnings',
                amount: $grossEarnings,
                formula: sprintf('%s + %s + %s', $basic->format(), $hra->format(), $specialAllowance->format()),
            ),
        ];

        // Administration charges are left out on purpose: the ₹500 floor in the
        // EPF table is a per-establishment monthly minimum, so charging it to
        // one employee's annexure overstates the employer's cost enormously.
        $pf = (new PfCalculator($this->rates))->calculate(
            basicSalary: $basic,
            includeAdminCharges: false,
            asOf: $asOf,
        );

        $steps[] = new Step(
            label: 'Provident fund, employee share',
            amount: $pf->employeeContribution,
            formula: sprintf('%s%% × %s', self::rate($pf->contributionRate), $pf->pfWages->format()),
            citation: $pf->citations()[0] ?? null,
        );

        // Coverage is settled on the full monthly wage, the contribution on the
        // wages actually earned. Testing coverage on a prorated figure would
        // drag an employee on ₹25,000 into ESI for the one month they took a
        // fortnight of unpaid leave, and then out again the month after.
        $esiCalculator = new EsiCalculator($this->rates);
        $esiCoverage = $esiCalculator->calculate(grossWages: $monthlyGross, asOf: $asOf);
        $esi = $esiCoverage->applicable
            ? $esiCalculator->calculate(grossWages: $grossEarnings, asOf: $asOf)
            : $esiCoverage;

        $steps[] = new Step(
            label: $esi->applicable ? 'ESI, employee share' : 'ESI - not applicable',
            amount: $esi->employeeContribution,
            formula: $esi->applicable
                ? sprintf('roundup(%s%% × %s)', self::rate($esi->employeeRate), $grossEarnings->format())
                : $esiCoverage->reason,
            citation: $esi->citations()[0] ?? null,
        );

        $professionalTaxDetail = (new ProfessionalTaxCalculator($this->rates))->calculate(
            monthlySalary: $grossEarnings,
            state: $state,
            month: (int) $payMonth->format('n'),
            asOf: $asOf,
        );

        // Most states assess monthly and the month's own levy is what comes off
        // this slip - which is how Maharashtra's ₹300 February lands in February
        // rather than being averaged away. Tamil Nadu, Kerala, Bihar, Jharkhand
        // and Puducherry assess over a half year or a year, and deducting a
        // whole period's levy from one month would be an over-recovery, so a
        // twelfth of the annual liability is taken instead.
        $professionalTax = $professionalTaxDetail->frequency === 'monthly'
            ? $professionalTaxDetail->amount
            : $professionalTaxDetail->monthlyEquivalent;

        $steps[] = new Step(
            label: sprintf('Professional tax, %s', $professionalTaxDetail->state),
            amount: $professionalTax,
            formula: $professionalTaxDetail->frequency === 'monthly'
                ? $professionalTaxDetail->reason
                : sprintf(
                    '%s assesses %s; one twelfth of the annual %s is deducted this month',
                    $professionalTaxDetail->state,
                    str_replace('_', '-', $professionalTaxDetail->frequency),
                    $professionalTaxDetail->annualTotal->format(),
                ),
            citation: $professionalTaxDetail->citations()[0] ?? null,
        );

        // Section 192 asks the employer to estimate the whole financial year, so
        // the instalment is a twelfth of the tax on the contracted salary - not
        // on this month's prorated earnings. Annualising a month with loss of
        // pay would under-deduct all year and leave the employee with a bill in
        // March.
        $tdsDetail = (new TdsCalculator($this->rates))->calculate(
            monthlyGross: $monthlyGross,
            asOf: $asOf,
        );

        $steps[] = new Step(
            label: 'TDS under section 192',
            amount: $tdsDetail->monthlyTds,
            formula: sprintf(
                'annual tax of %s on %s ÷ 12',
                $tdsDetail->annualTax->format(),
                $tdsDetail->annualGross->format(),
            ),
            citation: $tdsDetail->citations()[0] ?? null,
        );

        $totalDeductions = $pf->employeeContribution->add(
            $esi->employeeContribution,
            $professionalTax,
            $tdsDetail->monthlyTds,
        );

        $steps[] = new Step(
            label: 'Total deductions',
            amount: $totalDeductions,
        );

        // Not floored. A month of near-total loss of pay can leave the statutory
        // deductions larger than the wages, and the slip has to show the
        // recovery rather than quietly print a zero.
        $netPay = $grossEarnings->subtract($totalDeductions);

        if ($netPay->isNegative()) {
            $notes[] = sprintf(
                'Deductions of %s exceed earnings of %s, so %s is recoverable rather than payable.',
                $totalDeductions->format(),
                $grossEarnings->format(),
                $netPay->multiply(-1)->format(),
            );
        }

        $steps[] = new Step(
            label: $netPay->isNegative() ? 'Net recoverable from the employee' : 'Net pay',
            amount: $netPay,
            formula: sprintf('%s − %s', $grossEarnings->format(), $totalDeductions->format()),
            citation: self::WAGE_SLIP_CITATION,
        );

        $steps[] = new Step(
            label: 'Net pay in words',
            formula: PayslipResult::amountInWords($netPay),
            citation: self::WAGE_SLIP_CITATION,
        );

        $totalEmployerContributions = $pf->employerTotal->add($pf->edli, $esi->employerContribution);

        $notes[] = sprintf(
            'TDS is estimated under the default new regime on this salary alone, with no investment '
            .'declaration and nothing already deducted, as a twelfth of the year\'s liability on %s.',
            $tdsDetail->annualGross->format(),
        );

        $notes[] = 'The employer contributions are shown for information and are not deducted from the net. '
            .'PF administration charges are excluded: the minimum is a per-establishment monthly charge, '
            .'not a per-employee one.';

        return new PayslipResult(
            employeeName: $employeeName,
            employeeCode: $employeeCode,
            designation: $designation,
            payMonth: $payMonth,
            state: $professionalTaxDetail->state,
            daysInMonth: $daysInMonth,
            daysPayable: $daysPayable,
            lopDays: $lopDays,
            paidDays: $paidDays,
            monthlyGross: $monthlyGross,
            monthlyBasic: $monthlyBasic,
            basic: $basic,
            hra: $hra,
            specialAllowance: $specialAllowance,
            grossEarnings: $grossEarnings,
            providentFund: $pf->employeeContribution,
            stateInsurance: $esi->employeeContribution,
            stateInsuranceApplicable: $esi->applicable,
            professionalTax: $professionalTax,
            tds: $tdsDetail->monthlyTds,
            totalDeductions: $totalDeductions,
            netPay: $netPay,
            employerEpf: $pf->employerEpf,
            employerEps: $pf->employerEps,
            employerPf: $pf->employerTotal,
            employerEdli: $pf->edli,
            employerEsi: $esi->employerContribution,
            totalEmployerContributions: $totalEmployerContributions,
            pfDetail: $pf,
            esiDetail: $esi,
            professionalTaxDetail: $professionalTaxDetail,
            tdsDetail: $tdsDetail,
            notes: $notes,
            steps: $steps,
            citations: array_merge(
                [self::WAGE_SLIP_CITATION],
                $pf->citations(),
                $esi->citations(),
                $professionalTaxDetail->citations(),
                $tdsDetail->citations(),
            ),
        );
    }

    private function normalisePayMonth(\DateTimeImmutable|string $payMonth): \DateTimeImmutable
    {
        if ($payMonth instanceof \DateTimeImmutable) {
            return $payMonth->setTime(0, 0);
        }

        try {
            return (new \DateTimeImmutable($payMonth))->setTime(0, 0);
        } catch (\Exception) {
            // A month nobody can parse is a caller bug, not a payroll outcome,
            // and guessing at it would silently pay the wrong wage month.
            throw InvalidInputException::outOfRange('Pay month', 'a parsable date', $payMonth);
        }
    }

    private function validate(
        string $employeeName,
        Money $monthlyGross,
        Money $monthlyBasic,
        int $daysPayable,
        int $lopDays,
    ): void {
        if ($employeeName === '') {
            throw InvalidInputException::outOfRange(
                'Employee name',
                'given - section 13A requires the slip to name the person it is issued to',
                '(empty)',
            );
        }

        if ($monthlyGross->isNegative()) {
            throw InvalidInputException::negative('Monthly gross');
        }

        if ($monthlyBasic->isNegative()) {
            throw InvalidInputException::negative('Monthly basic');
        }

        if ($monthlyBasic->greaterThan($monthlyGross)) {
            throw InvalidInputException::outOfRange(
                'Monthly basic',
                'no more than the monthly gross',
                $monthlyBasic->format(),
            );
        }

        if ($daysPayable < 0) {
            throw InvalidInputException::negative('Days payable');
        }

        if ($lopDays < 0) {
            throw InvalidInputException::negative('Loss-of-pay days');
        }
    }

    private static function rate(float $rate): string
    {
        return rtrim(rtrim(number_format($rate, 2, '.', ''), '0'), '.');
    }
}
