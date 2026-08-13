/*
 * Progressive enhancement for Payslip Generator.
 *
 * If the browser build (@crmleaf/payroll-js) is on the page, the form computes
 * in place and never touches the network. If it is not, this script does
 * nothing at all and the form posts to the server as it would have anyway.
 * That ordering matters: the page has to work before the JavaScript loads, not
 * because of it.
 */
(function () {
    'use strict';

    var TOOL = 'payslip-generator';
    var CALCULATOR = 'payslip';
    var FIELDS = [
        { name: 'employee_name', argument: 'employeeName', type: 'string' },
        { name: 'employee_code', argument: 'employeeCode', type: 'string' },
        { name: 'designation', argument: 'designation', type: 'string' },
        { name: 'monthly_gross', argument: 'monthlyGross', type: 'money' },
        { name: 'monthly_basic', argument: 'monthlyBasic', type: 'money' },
        { name: 'pay_month', argument: 'payMonth', type: 'date' },
        { name: 'days_payable', argument: 'daysPayable', type: 'int' },
        { name: 'lop_days', argument: 'lopDays', type: 'int' },
        { name: 'state', argument: 'state', type: 'string' },
        { name: 'as_of', argument: 'asOf', type: 'date' },
    ];

    function engine() {
        var scope = window.CrmleafPayroll || window.crmleaf || null;

        return scope && typeof scope[CALCULATOR] === 'function' ? scope : null;
    }

    function read(form, field) {
        var element = form.elements.namedItem(field.name);

        if (!element) {
            return undefined;
        }

        if (field.type === 'bool') {
            return element.type === 'checkbox' ? element.checked : element.value === '1';
        }

        var value = (element.value || '').trim();

        if (value === '') {
            return undefined;
        }

        if (field.type === 'money' || field.type === 'float') {
            return Number(value.replace(/,/g, ''));
        }

        if (field.type === 'int') {
            return parseInt(value.replace(/,/g, ''), 10);
        }

        if (field.type === 'array') {
            try {
                return JSON.parse(value);
            } catch (error) {
                return undefined;
            }
        }

        return value;
    }

    function payload(form) {
        var input = {};

        FIELDS.forEach(function (field) {
            var value = read(form, field);

            if (value !== undefined && value !== null && value === value) {
                input[field.argument] = value;
            }
        });

        return input;
    }

    function money(value) {
        if (typeof value !== 'number' || !isFinite(value)) {
            return String(value);
        }

        return value.toLocaleString('en-IN', { maximumFractionDigits: 2 });
    }

    // Built with createElement and textContent rather than innerHTML on purpose.
    // Several of these values are strings the user typed - an employee name, a
    // code, a designation - and this file is published into the consuming
    // application's public directory, so a payload here would be their cross-site
    // scripting bug, in a page handling salary data. textContent cannot execute
    // markup, so there is nothing to escape and nothing to get wrong later.
    function render(output, result) {
        var table = document.createElement('table');
        var body = document.createElement('tbody');

        table.className = 'crmleaf-tool__figures';

        Object.keys(result)
            .filter(function (key) {
                var value = result[key];

                return value !== null && typeof value !== 'object' && !/_formatted$/.test(key);
            })
            .forEach(function (key) {
                var display = result[key + '_formatted'];

                if (display === undefined) {
                    display = typeof result[key] === 'boolean'
                        ? (result[key] ? 'Yes' : 'No')
                        : money(result[key]);
                }

                var row = document.createElement('tr');
                var head = document.createElement('th');
                var cell = document.createElement('td');

                head.setAttribute('scope', 'row');
                head.textContent = key.replace(/_/g, ' ');
                cell.textContent = String(display);

                row.appendChild(head);
                row.appendChild(cell);
                body.appendChild(row);
            });

        table.appendChild(body);

        output.textContent = '';
        output.appendChild(table);
        output.hidden = false;
    }

    function bind(root) {
        var form = root.querySelector('[data-crmleaf-form]');
        var output = root.querySelector('[data-crmleaf-output]');

        if (!form || !output) {
            return;
        }

        form.addEventListener('submit', function (event) {
            var scope = engine();

            if (!scope) {
                return; // No browser build: let the form post to the server.
            }

            event.preventDefault();

            try {
                render(output, scope[CALCULATOR](payload(form)));
            } catch (error) {
                output.textContent = error && error.message ? error.message : String(error);
                output.hidden = false;
            }
        });
    }

    function start() {
        Array.prototype.forEach.call(
            document.querySelectorAll('[data-crmleaf-tool="' + TOOL + '"]'),
            bind
        );
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})();
