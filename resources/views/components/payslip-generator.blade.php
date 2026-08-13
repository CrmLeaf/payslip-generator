@props([
    'action' => null,
    'method' => 'post',
    'defaults' => [],
    'input' => [],
    'result' => null,
    'error' => null,
    'heading' => 'Payslip Generator',
    'tagline' => 'Compliance-ready payslip PDFs with your company branding.',
    'showWorking' => true,
])

<section class="crmleaf-tool crmleaf-tool--payslip-generator" data-crmleaf-tool="payslip-generator">
    <header class="crmleaf-tool__header">
        <h2 class="crmleaf-tool__heading">{{ $heading }}</h2>
        <p class="crmleaf-tool__tagline">{{ $tagline }}</p>
    </header>

    @if ($error)
        <p class="crmleaf-tool__error" role="alert">{{ $error }}</p>
    @endif

    <form class="crmleaf-tool__form"
          method="{{ strtolower($method) === 'get' ? 'get' : 'post' }}"
          action="{{ $action }}"
          data-crmleaf-form>
        @if (strtolower($method) !== 'get')
            @csrf
        @endif

        <label class="crmleaf-field">
            <span>Employee name</span>
            <input type="text" name="employee_name" value="{{ old('employee_name', $input['employee_name'] ?? ($defaults['employee_name'] ?? '')) }}" required>
        </label>

        <label class="crmleaf-field">
            <span>Employee code</span>
            <input type="text" name="employee_code" value="{{ old('employee_code', $input['employee_code'] ?? ($defaults['employee_code'] ?? '')) }}">
        </label>

        <label class="crmleaf-field">
            <span>Designation</span>
            <input type="text" name="designation" value="{{ old('designation', $input['designation'] ?? ($defaults['designation'] ?? '')) }}">
        </label>

        <label class="crmleaf-field">
            <span>Monthly gross</span>
            <input type="number" step="0.01" min="0" inputmode="decimal" name="monthly_gross" value="{{ old('monthly_gross', $input['monthly_gross'] ?? ($defaults['monthly_gross'] ?? '')) }}" required>
        </label>

        <label class="crmleaf-field">
            <span>Monthly basic (basic + DA)</span>
            <input type="number" step="0.01" min="0" inputmode="decimal" name="monthly_basic" value="{{ old('monthly_basic', $input['monthly_basic'] ?? ($defaults['monthly_basic'] ?? '')) }}" required>
        </label>

        <label class="crmleaf-field">
            <span>Wage month</span>
            <input type="date" name="pay_month" value="{{ old('pay_month', $input['pay_month'] ?? ($defaults['pay_month'] ?? '')) }}" required>
            <small>Any date within the month being paid; the first of the month is conventional.</small>
        </label>

        <label class="crmleaf-field">
            <span>Days payable</span>
            <input type="number" step="1" inputmode="numeric" name="days_payable" value="{{ old('days_payable', $input['days_payable'] ?? ($defaults['days_payable'] ?? '')) }}">
        </label>

        <label class="crmleaf-field">
            <span>Loss-of-pay days</span>
            <input type="number" step="1" inputmode="numeric" name="lop_days" value="{{ old('lop_days', $input['lop_days'] ?? ($defaults['lop_days'] ?? '')) }}">
        </label>

        <label class="crmleaf-field">
            <span>State</span>
            <input type="text" name="state" value="{{ old('state', $input['state'] ?? ($defaults['state'] ?? '')) }}">
            <small>Decides which professional tax schedule applies.</small>
        </label>

        <label class="crmleaf-field">
            <span>Rates as on</span>
            <input type="date" name="as_of" value="{{ old('as_of', $input['as_of'] ?? ($defaults['as_of'] ?? '')) }}">
            <small>Leave blank for current rates. Set it to recompute an old payslip on the rates that were in force then.</small>
        </label>

        <input type="hidden" name="tool" value="payslip-generator">

        <div class="crmleaf-tool__actions">
            <button type="submit" class="crmleaf-tool__submit">Calculate</button>
        </div>
    </form>

    {{-- The client-side path writes its answer here; the server-side path fills it below. --}}
    <div class="crmleaf-tool__output" data-crmleaf-output hidden></div>

    @if ($result)
        <div class="crmleaf-tool__result">
            <p class="crmleaf-tool__explain"><code>{{ $result->explain() }}</code></p>

            <table class="crmleaf-tool__figures">
                <tbody>
                @foreach ($result->toArray() as $key => $value)
                    @continue(is_array($value) || str_ends_with((string) $key, '_formatted'))
                    <tr>
                        <th scope="row">{{ ucfirst(str_replace('_', ' ', (string) $key)) }}</th>
                        <td>{{ $result->toArray()[$key.'_formatted'] ?? (is_bool($value) ? ($value ? 'Yes' : 'No') : $value) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>

            @if ($showWorking && count($result->steps()))
                <details class="crmleaf-tool__working" open>
                    <summary>How this was worked out</summary>
                    <ol>
                        @foreach ($result->steps() as $step)
                            <li>
                                <span class="crmleaf-step__label">{{ $step->label }}</span>
                                @if ($step->amount)
                                    <span class="crmleaf-step__amount">{{ $step->amount->format() }}</span>
                                @endif
                                @if ($step->formula)
                                    <code class="crmleaf-step__formula">{{ $step->formula }}</code>
                                @endif
                                @if ($step->citation)
                                    <small class="crmleaf-step__citation">{{ $step->citation }}</small>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                </details>
            @endif

            @if (count($result->citations()))
                <ul class="crmleaf-tool__citations">
                    @foreach ($result->citations() as $citation)
                        <li>{{ $citation }}</li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endif
</section>
