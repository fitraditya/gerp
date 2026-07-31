<x-filament-panels::page>
    <form wire:submit.prevent="applyFilters">
        {{ $this->form }}
    </form>

    @php
        $fmt = fn ($v) => 'Rp' . number_format((float) $v, 0, ',', '.');
        $pl = $profitAndLoss;
        $tileGrid = 'display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem;margin-top:1.5rem;';
        $tile = function (string $bg, string $fg, string $label, string $value) {
            $style = "background:{$bg};color:{$fg};border-radius:0.5rem;padding:1rem;text-align:center;";
            return "<div style=\"{$style}\">"
                .  "<div style=\"font-size:0.8rem;font-weight:600;opacity:0.85;\">{$label}</div>"
                .  "<div style=\"font-size:1.5rem;font-weight:700;margin-top:0.25rem;\">{$value}</div>"
                .  "</div>";
        };
    @endphp

    <h2 style="margin-top:1.5rem;font-weight:700;">{{ __('financial_reports.pl.title') }}</h2>
    <div style="{{ $tileGrid }}">
        {!! $tile('#166534', '#ffffff', __('financial_reports.pl.revenue'), $fmt($pl['revenue'])) !!}
        {!! $tile('#7c2d12', '#ffffff', __('financial_reports.pl.cogs'), $fmt($pl['cogs'])) !!}
        {!! $tile('#0c4a6e', '#ffffff', __('financial_reports.pl.gross_profit'), $fmt($pl['gross_profit'])) !!}
        {!! $tile('#92400e', '#ffffff', __('financial_reports.pl.operating_expenses'), $fmt($pl['operating_expenses'])) !!}
        {!! $tile('#065f46', '#ffffff', __('financial_reports.pl.net_profit'), $fmt($pl['net_profit'])) !!}
    </div>

    <h2 style="margin-top:2rem;font-weight:700;">{{ __('financial_reports.tb.title') }}</h2>
    @foreach ($trialBalance as $accountType => $accounts)
        <div style="margin-top:1rem;overflow-x:auto;border-radius:0.5rem;border:1px solid #e5e7eb;">
            <table style="width:100%;font-size:0.875rem;border-collapse:collapse;">
                <thead>
                    <tr style="background:#e2e8f0;color:#000;">
                        <th style="padding:0.5rem;text-align:left;" colspan="3">{{ ucfirst($accountType) }}</th>
                    </tr>
                    <tr style="background:#f8fafc;color:#000;">
                        <th style="padding:0.5rem;text-align:left;">{{ __('financial_reports.tb.code') }}</th>
                        <th style="padding:0.5rem;text-align:left;">{{ __('financial_reports.tb.name') }}</th>
                        <th style="padding:0.5rem;text-align:right;">{{ __('financial_reports.tb.balance') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($accounts as $account)
                        <tr style="border-top:1px solid #e5e7eb;">
                            <td style="padding:0.5rem;">{{ $account['code'] }}</td>
                            <td style="padding:0.5rem;">{{ $account['name'] }}</td>
                            <td style="padding:0.5rem;text-align:right;">{{ $fmt($account['balance']) }}</td>
                        </tr>
                    @endforeach
                    <tr style="border-top:2px solid #9ca3af;font-weight:700;">
                        <td style="padding:0.5rem;" colspan="2">{{ __('financial_reports.tb.subtotal') }}</td>
                        <td style="padding:0.5rem;text-align:right;">{{ $fmt(collect($accounts)->sum('balance')) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    @endforeach
</x-filament-panels::page>
