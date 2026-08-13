<?php

declare(strict_types=1);

namespace Crmleaf\Payroll\Tools\PayslipGenerator\Documents;

use Crmleaf\Payroll\Contracts\CalculationResult;
use Illuminate\Http\Response;
use RuntimeException;

/**
 * Renders a Payslip Generator result as a PDF file.
 *
 * The rendering happens inside your application, against your published
 * config, and the bytes never leave your infrastructure. That rules out the
 * obvious shortcut - a front-end component posting to a shared document
 * endpoint - because the token that shortcut needs would ship in the JavaScript
 * bundle every visitor downloads.
 *
 * The renderer itself is a suggested dependency rather than a required one:
 * plenty of applications already have one, and a calculation package has no
 * business dictating which.
 */
final class PayslipGeneratorDocument
{
    /**
     * @param array<string, mixed> $context extra values for the document view,
     *                                      typically the company block from config
     */
    public function html(CalculationResult $result, array $context = []): string
    {
        /** @var \Illuminate\Contracts\Config\Repository $config */
        $config = app('config');

        return view('payslip-generator::document', array_merge([
            'result' => $result,
            'data' => $result->toArray(),
            'title' => (string) $config->get('payslip-generator.view.title', 'Payslip Generator'),
            'company' => (array) $config->get('payslip-generator.document.company', []),
        ], $context))->render();
    }

    /**
     * @param array<string, mixed> $context
     */
    public function render(CalculationResult $result, array $context = []): string
    {
        /** @var \Illuminate\Contracts\Config\Repository $config */
        $config = app('config');

        if (!class_exists('Dompdf\Dompdf')) {
            throw new RuntimeException(
                'Rendering a PDF needs a renderer: run `composer require dompdf/dompdf`, '
                .'or call html() and pipe the markup through the renderer you already have.',
            );
        }

        $options = new \Dompdf\Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);

        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($this->html($result, $context));
        $dompdf->setPaper(
            (string) $config->get('payslip-generator.document.paper', 'a4'),
            (string) $config->get('payslip-generator.document.orientation', 'portrait'),
        );
        $dompdf->render();

        return (string) $dompdf->output();
    }

    /**
     * The result flattened to spreadsheet rows: the figures first, then the
     * working, then the statutory citations, so the file is auditable on its own
     * without the application that produced it.
     *
     * @return array<int, array<int, string>>
     */
    public function rows(CalculationResult $result): array
    {
        $rows = [['Figure', 'Value']];

        foreach ($result->toArray() as $key => $value) {
            if (is_array($value)) {
                continue;
            }

            $rows[] = [str_replace('_', ' ', (string) $key), $this->scalar($value)];
        }

        $rows[] = ['', ''];
        $rows[] = ['Working', 'Amount'];

        foreach ($result->steps() as $step) {
            $rows[] = [$step->label, $step->amount?->format() ?? (string) $step->formula];
        }

        $rows[] = ['', ''];
        $rows[] = ['Statutory basis', ''];

        foreach ($result->citations() as $citation) {
            $rows[] = [$citation, ''];
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $context
     */
    public function download(CalculationResult $result, string $filename, array $context = []): Response
    {
        return new Response($this->render($result, $context), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function scalar(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        return is_scalar($value) ? (string) $value : '';
    }
}
