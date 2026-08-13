/*
 * @crmleaf/payslip-generator - a re-export, not a reimplementation.
 *
 * The arithmetic lives once, in @crmleaf/payroll-js, so a slab change cannot
 * land in one package and miss another. This package exists so a project that
 * only wants Payslip Generator can install only Payslip Generator and still get the
 * identical function it would have got from the suite.
 */

export { payslip, payslip as calculate, Money } from '@crmleaf/payroll-js';

export { payslip as default } from '@crmleaf/payroll-js';
