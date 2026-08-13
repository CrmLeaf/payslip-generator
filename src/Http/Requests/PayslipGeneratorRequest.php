<?php

declare(strict_types=1);

namespace Crmleaf\Payroll\Tools\PayslipGenerator\Http\Requests;

use Crmleaf\Payroll\Money;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the wire input for Payslip Generator and turns it into named arguments
 * for Crmleaf\Payroll\Calculators\PayslipGenerator::calculate().
 *
 * Optional fields that were not sent are left out of the payload entirely
 * rather than passed as null, so the calculator's own documented defaults apply
 * and there is exactly one place each default is written down.
 */
final class PayslipGeneratorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        if (!$this->submitted()) {
            return [];
        }

        return [
            'employee_name' => ['required', 'string', 'max:120'],
            'employee_code' => ['nullable', 'string', 'max:40'],
            'designation' => ['nullable', 'string', 'max:120'],
            'monthly_gross' => ['required', 'numeric', 'min:0'],
            'monthly_basic' => ['required', 'numeric', 'min:0'],
            'pay_month' => ['required', 'date'],
            'days_payable' => ['nullable', 'integer', 'min:0', 'max:31'],
            'lop_days' => ['nullable', 'integer', 'min:0', 'max:31'],
            'state' => ['nullable', 'string', 'max:60'],
            'as_of' => ['nullable', 'date'],
        ];
    }

    /**
     * Named arguments for PayslipGenerator::calculate().
     *
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        /** @var array<string, mixed> $input */
        $input = $this->validated();

        $payload = [
            'employeeName' => (string) $input['employee_name'],
            'monthlyGross' => Money::fromRupees((float) $input['monthly_gross']),
            'monthlyBasic' => Money::fromRupees((float) $input['monthly_basic']),
            'payMonth' => new \DateTimeImmutable((string) $input['pay_month']),
        ];

        if (array_key_exists('employee_code', $input) && $input['employee_code'] !== null) {
            $payload['employeeCode'] = (string) $input['employee_code'];
        }

        if (array_key_exists('designation', $input) && $input['designation'] !== null) {
            $payload['designation'] = (string) $input['designation'];
        }

        if (array_key_exists('days_payable', $input) && $input['days_payable'] !== null) {
            $payload['daysPayable'] = (int) $input['days_payable'];
        }

        if (array_key_exists('lop_days', $input) && $input['lop_days'] !== null) {
            $payload['lopDays'] = (int) $input['lop_days'];
        }

        if (array_key_exists('state', $input) && $input['state'] !== null) {
            $payload['state'] = (string) $input['state'];
        }

        if (array_key_exists('as_of', $input) && $input['as_of'] !== null) {
            $payload['asOf'] = new \DateTimeImmutable((string) $input['as_of']);
        }

        return $payload;
    }

    /**
     * A bare GET renders an empty form; everything else is a submission.
     */
    public function submitted(): bool
    {
        return $this->isMethod('post') || $this->expectsJson() || $this->query->count() > 0;
    }

    public function wantsDocument(): bool
    {
        return in_array($this->input('format'), ['pdf', 'download'], true);
    }

    public function documentFilename(): string
    {
        $name = (string) $this->input('filename', 'payslip-generator');

        // Anything that is not plainly a filename is discarded rather than
        // sanitised, because a Content-Disposition header is header-injection surface.
        $safe = preg_replace('/[^A-Za-z0-9._-]/', '-', $name) ?? 'payslip-generator';

        return trim($safe, '-.') !== '' ? $safe.'.pdf' : 'payslip-generator.pdf';
    }
}
