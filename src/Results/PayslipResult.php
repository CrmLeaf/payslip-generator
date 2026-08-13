<?php

declare(strict_types=1);

namespace Crmleaf\Payroll\Results;

use Crmleaf\Payroll\Contracts\Step;
use Crmleaf\Payroll\Money;
use Crmleaf\Payroll\Support\Result;

/**
 * One employee's wage slip for one wage month, itemised the way section 13A of
 * the Payment of Wages Act 1936 and the Code on Wages 2019 require it to be.
 *
 * The two arrays that matter are `earningLines()` and `deductionLines()`: a
 * payslip is a table, and the order of its rows is part of what makes it
 * readable. Everything else on the class is either an identity field the slip
 * has to print, a total the rows add to, or the employer's own contributions,
 * which belong on the annexure rather than in the deductions column - they are
 * not the employee's money and never come out of the net.
 */
final class PayslipResult extends Result
{
    /** @var array<int, string> */
    private const ONES = [
        0 => 'Zero', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine',
        'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen',
        'Eighteen', 'Nineteen',
    ];

    /** @var array<int, string> */
    private const TENS = [
        2 => 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety',
    ];

    private const CRORE = 1_00_00_000;
    private const LAKH = 1_00_000;

    /**
     * The net pay written out the way a cheque is written, in Indian numbering.
     * A wage slip carries the figure in words as well as in digits so that a
     * disputed amount cannot turn on a misplaced comma.
     */
    public readonly string $netPayInWords;

    /**
     * @param array<int, string> $notes anything the calculation could not reach or had to
     *                                  adjust, spelled out rather than left implicit
     * @param array<int, Step> $steps
     * @param array<int, string> $citations
     */
    public function __construct(
        public readonly string $employeeName,
        public readonly string $employeeCode,
        public readonly string $designation,
        public readonly \DateTimeImmutable $payMonth,
        public readonly string $state,
        public readonly int $daysInMonth,
        public readonly int $daysPayable,
        public readonly int $lopDays,
        public readonly int $paidDays,
        public readonly Money $monthlyGross,
        public readonly Money $monthlyBasic,
        public readonly Money $basic,
        public readonly Money $hra,
        public readonly Money $specialAllowance,
        public readonly Money $grossEarnings,
        public readonly Money $providentFund,
        public readonly Money $stateInsurance,
        public readonly bool $stateInsuranceApplicable,
        public readonly Money $professionalTax,
        public readonly Money $tds,
        public readonly Money $totalDeductions,
        public readonly Money $netPay,
        public readonly Money $employerEpf,
        public readonly Money $employerEps,
        public readonly Money $employerPf,
        public readonly Money $employerEdli,
        public readonly Money $employerEsi,
        public readonly Money $totalEmployerContributions,
        public readonly PfResult $pfDetail,
        public readonly EsiResult $esiDetail,
        public readonly ProfessionalTaxResult $professionalTaxDetail,
        public readonly TdsResult $tdsDetail,
        public readonly array $notes = [],
        array $steps = [],
        array $citations = [],
    ) {
        $this->netPayInWords = self::amountInWords($netPay);

        $this->withWorking($steps, $citations);
    }

    /**
     * The earnings side of the slip, in the order it should be printed.
     *
     * All three lines are always present, including a nil one. A structure that
     * silently drops its special allowance in the month it happens to be zero
     * makes two consecutive payslips look like two different salaries.
     *
     * @return array<int, array{label: string, amount: Money}>
     */
    public function earningLines(): array
    {
        return [
            ['label' => 'Basic', 'amount' => $this->basic],
            ['label' => 'House rent allowance', 'amount' => $this->hra],
            ['label' => 'Special allowance', 'amount' => $this->specialAllowance],
        ];
    }

    /**
     * @return array<int, array{label: string, amount: Money}>
     */
    public function deductionLines(): array
    {
        $lines = [];

        if ($this->providentFund->isPositive()) {
            $lines[] = ['label' => 'Provident fund (employee share)', 'amount' => $this->providentFund];
        }

        if ($this->stateInsurance->isPositive()) {
            $lines[] = ['label' => 'ESI (employee share)', 'amount' => $this->stateInsurance];
        }

        if ($this->professionalTax->isPositive()) {
            $lines[] = ['label' => sprintf('Professional tax (%s)', $this->state), 'amount' => $this->professionalTax];
        }

        if ($this->tds->isPositive()) {
            $lines[] = ['label' => 'TDS under section 192', 'amount' => $this->tds];
        }

        return $lines;
    }

    /**
     * The employer's own contributions, for the annexure most employers print
     * below the slip. None of these are deducted from the employee.
     *
     * @return array<int, array{label: string, amount: Money}>
     */
    public function employerContributionLines(): array
    {
        $lines = [
            ['label' => 'Provident fund - EPF (A/c 1)', 'amount' => $this->employerEpf],
            ['label' => 'Provident fund - EPS (A/c 10)', 'amount' => $this->employerEps],
            ['label' => 'EDLI (A/c 21)', 'amount' => $this->employerEdli],
        ];

        if ($this->stateInsuranceApplicable) {
            $lines[] = ['label' => 'ESI (employer share)', 'amount' => $this->employerEsi];
        }

        return $lines;
    }

    public function payMonthLabel(): string
    {
        return $this->payMonth->format('F Y');
    }

    public function explain(): string
    {
        return sprintf(
            '%s (%s), %s: %d of %d days paid; %s earnings − %s deductions = %s net (%s).',
            $this->employeeName,
            $this->employeeCode,
            $this->payMonthLabel(),
            $this->paidDays,
            $this->daysInMonth,
            $this->grossEarnings->format(),
            $this->totalDeductions->format(),
            $this->netPay->format(),
            $this->netPayInWords,
        );
    }

    public function toArray(): array
    {
        return [
            'employee_name' => $this->employeeName,
            'employee_code' => $this->employeeCode,
            'designation' => $this->designation,
            'pay_month' => $this->payMonth->format('Y-m'),
            'pay_month_label' => $this->payMonthLabel(),
            'state' => $this->state,

            'days_in_month' => $this->daysInMonth,
            'days_payable' => $this->daysPayable,
            'lop_days' => $this->lopDays,
            'paid_days' => $this->paidDays,

            'earnings' => array_map($this->line(...), $this->earningLines()),
            'deductions' => array_map($this->line(...), $this->deductionLines()),
            'employer_contributions' => array_map($this->line(...), $this->employerContributionLines()),

            'monthly_gross' => $this->monthlyGross->toRupees(),
            'monthly_gross_formatted' => $this->monthlyGross->format(),
            'monthly_basic' => $this->monthlyBasic->toRupees(),
            'monthly_basic_formatted' => $this->monthlyBasic->format(),

            'basic' => $this->basic->toRupees(),
            'basic_formatted' => $this->basic->format(),
            'hra' => $this->hra->toRupees(),
            'hra_formatted' => $this->hra->format(),
            'special_allowance' => $this->specialAllowance->toRupees(),
            'special_allowance_formatted' => $this->specialAllowance->format(),
            'gross_earnings' => $this->grossEarnings->toRupees(),
            'gross_earnings_formatted' => $this->grossEarnings->format(),

            'provident_fund' => $this->providentFund->toRupees(),
            'provident_fund_formatted' => $this->providentFund->format(),
            'state_insurance' => $this->stateInsurance->toRupees(),
            'state_insurance_formatted' => $this->stateInsurance->format(),
            'state_insurance_applicable' => $this->stateInsuranceApplicable,
            'professional_tax' => $this->professionalTax->toRupees(),
            'professional_tax_formatted' => $this->professionalTax->format(),
            'tds' => $this->tds->toRupees(),
            'tds_formatted' => $this->tds->format(),
            'total_deductions' => $this->totalDeductions->toRupees(),
            'total_deductions_formatted' => $this->totalDeductions->format(),

            'net_pay' => $this->netPay->toRupees(),
            'net_pay_formatted' => $this->netPay->format(),
            'net_pay_in_words' => $this->netPayInWords,

            'employer_epf' => $this->employerEpf->toRupees(),
            'employer_epf_formatted' => $this->employerEpf->format(),
            'employer_eps' => $this->employerEps->toRupees(),
            'employer_eps_formatted' => $this->employerEps->format(),
            'employer_pf' => $this->employerPf->toRupees(),
            'employer_pf_formatted' => $this->employerPf->format(),
            'employer_edli' => $this->employerEdli->toRupees(),
            'employer_edli_formatted' => $this->employerEdli->format(),
            'employer_esi' => $this->employerEsi->toRupees(),
            'employer_esi_formatted' => $this->employerEsi->format(),
            'total_employer_contributions' => $this->totalEmployerContributions->toRupees(),
            'total_employer_contributions_formatted' => $this->totalEmployerContributions->format(),

            'notes' => $this->notes,
            'professional_tax_detail' => $this->professionalTaxDetail->toArray(),
            'tds_detail' => $this->tdsDetail->toArray(),
        ];
    }

    /**
     * An amount in Indian-numbering words: lakh and crore rather than million
     * and billion, and the "and" that a cheque puts before the final pair of
     * digits - "Rupees Forty Two Thousand Three Hundred and Fifty Only".
     *
     * Public and static because it is a pure function of the amount, and a
     * caller printing a covering letter wants it without building a payslip
     * first.
     */
    public static function amountInWords(Money $amount): string
    {
        $absolute = abs($amount->paise);
        $rupees = intdiv($absolute, 100);
        $paise = $absolute % 100;

        $words = 'Rupees '.self::indianWords($rupees);

        if ($paise > 0) {
            $words .= ' and '.self::indianWords($paise).' Paise';
        }

        $words .= ' Only';

        // Heavy loss of pay can leave the deductions larger than the month's
        // earnings, and a slip that prints the recovery as though it were a
        // payment is the one line an employee will dispute.
        return $amount->isNegative() ? 'Minus '.$words : $words;
    }

    private static function indianWords(int $number): string
    {
        if ($number < 100) {
            return self::belowHundred($number);
        }

        $parts = [];

        $crore = intdiv($number, self::CRORE);
        $number %= self::CRORE;

        $lakh = intdiv($number, self::LAKH);
        $number %= self::LAKH;

        $thousand = intdiv($number, 1000);
        $number %= 1000;

        $hundred = intdiv($number, 100);
        $tail = $number % 100;

        if ($crore > 0) {
            // Recursive rather than capped: above a hundred crore the Indian
            // system keeps grouping in crores ("One Thousand Two Hundred Crore"),
            // and there is no larger unit to reach for.
            $parts[] = self::indianWords($crore).' Crore';
        }

        if ($lakh > 0) {
            $parts[] = self::belowHundred($lakh).' Lakh';
        }

        if ($thousand > 0) {
            $parts[] = self::belowHundred($thousand).' Thousand';
        }

        if ($hundred > 0) {
            $parts[] = self::belowHundred($hundred).' Hundred';
        }

        if ($tail > 0) {
            $parts[] = ($parts === [] ? '' : 'and ').self::belowHundred($tail);
        }

        return implode(' ', $parts);
    }

    private static function belowHundred(int $number): string
    {
        if ($number < 20) {
            return self::ONES[$number];
        }

        $tens = self::TENS[intdiv($number, 10)];
        $ones = $number % 10;

        return $ones === 0 ? $tens : $tens.' '.self::ONES[$ones];
    }

    /**
     * @param array{label: string, amount: Money} $line
     *
     * @return array{label: string, amount: float, amount_formatted: string}
     */
    private function line(array $line): array
    {
        return [
            'label' => $line['label'],
            'amount' => $line['amount']->toRupees(),
            'amount_formatted' => $line['amount']->format(),
        ];
    }
}
