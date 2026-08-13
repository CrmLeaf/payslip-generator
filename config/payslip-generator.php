<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Route
    |--------------------------------------------------------------------------
    |
    | Off by default. Installing a package should not add a public URL to your
    | application without you asking for one. Turn it on here, or with
    | PAYSLIP_GENERATOR_ROUTE=true, and the tool mounts at
    | /<prefix>/payslip-generator answering both GET and POST.
    |
    */

    'route' => [
        'enabled' => env('PAYSLIP_GENERATOR_ROUTE', false),
        'prefix' => env('PAYSLIP_GENERATOR_PREFIX', 'tools'),
        'name' => 'payslip-generator',
        // Throttled by default. This route is public when enabled: the form
        // request authorises every caller, and a PDF request runs a full Dompdf
        // render, which is orders of magnitude more expensive than the
        // arithmetic behind it. Thirty a minute per IP is generous for a human
        // filling in a form and useless to anyone trying to exhaust the box.
        // Raise it behind authentication if you have some.
        'middleware' => ['web', 'throttle:30,1'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Presentation
    |--------------------------------------------------------------------------
    */

    'view' => [
        'title' => 'Payslip Generator',
        'tagline' => 'Compliance-ready payslip PDFs with your company branding.',
    ],

    'assets' => [
        // This tool calculates server-side; the script only enhances the form.
        'script' => false,
        // Optional CDN for the browser build. When set, the view loads it
        // ahead of the asset published into your own public directory. When
        // null, the published asset is used on its own and the page makes no
        // third-party request at all - which is the better default for a page
        // that handles salary figures.
        //
        // A hosted build is coming soon. To use it instead, set this to:
        // https://cdn.jsdelivr.net/npm/@crmleaf/payroll-js@<major>/dist/payroll.min.js
        'cdn' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Document
    |--------------------------------------------------------------------------
    |
    | Rendering happens inside your application, against this configuration.
    | There is no hosted document service and therefore no credential for a
    | browser to carry. Your company details never leave your infrastructure.
    |
    */

    'document' => [
        'format' => 'pdf',
        'paper' => 'a4',
        'orientation' => 'portrait',
        'filename' => 'payslip-generator',
        'company' => [
            'name' => env('PAYROLL_COMPANY_NAME', 'Your Company Private Limited'),
            'address' => env('PAYROLL_COMPANY_ADDRESS', ''),
            'gstin' => env('PAYROLL_COMPANY_GSTIN', ''),
            'pan' => env('PAYROLL_COMPANY_PAN', ''),
            'logo' => env('PAYROLL_COMPANY_LOGO', null),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Defaults
    |--------------------------------------------------------------------------
    |
    | Pre-fill values for the form and the Blade component. These are
    | presentation only - they are never passed to the calculator, so changing
    | one cannot change a statutory answer.
    |
    */

    'defaults' => [
        'employee_name' => 'Asha Menon',
        'employee_code' => 'EMP-0001',
        'designation' => 'Senior Engineer',
        'monthly_gross' => 75000,
        'monthly_basic' => 30000,
        'pay_month' => '2025-08-01',
        'days_payable' => 31,
        'lop_days' => 0,
        'state' => 'Karnataka',
    ],

];
