<?php

declare(strict_types=1);

namespace Crmleaf\Payroll\Tools\PayslipGenerator\Http\Controllers;

use Crmleaf\Payroll\Calculators\PayslipGenerator;
use Crmleaf\Payroll\Contracts\CalculationResult;
use Crmleaf\Payroll\Exceptions\InvalidInputException;
use Crmleaf\Payroll\Tools\PayslipGenerator\Documents\PayslipGeneratorDocument;
use Crmleaf\Payroll\Tools\PayslipGenerator\Http\Requests\PayslipGeneratorRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * The whole HTTP surface of Payslip Generator: one action, one calculator call.
 *
 * A GET with no input renders the form. Anything else validates, calculates and
 * answers in the caller's preferred format. Nothing here decides anything about
 * payroll - that all lives in Crmleaf\Payroll\Calculators\PayslipGenerator - which is why the tool can
 * be embedded, mounted at any prefix, or ignored entirely in favour of calling
 * the calculator yourself.
 */
final class PayslipGeneratorController
{
    public function __construct(
        private readonly PayslipGenerator $calculator,
        private readonly PayslipGeneratorDocument $document,
    ) {
    }

    public function __invoke(PayslipGeneratorRequest $request): JsonResponse|Response|View
    {
        if (!$request->submitted()) {
            return $this->view($request, null);
        }

        try {
            $result = $this->calculator->calculate(...$request->payload());
        } catch (InvalidInputException $e) {
            // A statutory *ineligibility* is never an exception - the calculator
            // returns a zero result and explains itself. Landing here means the
            // input was genuinely unusable, so 422 is the honest answer.
            if ($request->expectsJson()) {
                return new JsonResponse([
                    'tool' => 'payslip-generator',
                    'message' => $e->getMessage(),
                ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
            }

            return $this->view($request, null, $e->getMessage());
        }

        if ($request->wantsDocument()) {
            return $this->document->download($result, $request->documentFilename());
        }

        if ($request->expectsJson()) {
            return new JsonResponse([
                'tool' => 'payslip-generator',
                'input' => $request->validated(),
                'data' => $result->toArray(),
                'explain' => $result->explain(),
                'working' => $result->steps(),
                'citations' => $result->citations(),
            ]);
        }

        return $this->view($request, $result);
    }

    private function view(PayslipGeneratorRequest $request, ?CalculationResult $result, ?string $error = null): View
    {
        /** @var \Illuminate\Contracts\Config\Repository $config */
        $config = app('config');

        return view('payslip-generator::payslip-generator', [
            'result' => $result,
            'error' => $error,
            'input' => $request->submitted() ? $request->validated() : [],
            'defaults' => (array) $config->get('payslip-generator.defaults', []),
            'title' => (string) $config->get('payslip-generator.view.title', 'Payslip Generator'),
            'action' => $request->url(),
        ]);
    }
}
