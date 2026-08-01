<x-filament-panels::page>
    <form wire:submit.prevent="applyFilters">
        {{ $this->form }}
    </form>

    @php
        $s = $summary;
        $fmt = fn ($v) => 'Rp' . number_format((float) $v, 0, ',', '.');
        $t = fn (string $key, array $replace = []) => __("dashboard.tiles.{$key}", $replace);

        $tileGrid = 'display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem;margin-top:1.5rem;';
        $tile = function (string $bg, string $fg, string $label, string $value, string $sub = '') {
            $style = "background:{$bg};color:{$fg};border-radius:0.5rem;padding:1rem;text-align:center;";
            return "<div style=\"{$style}\">"
                .  "<div style=\"font-size:0.8rem;font-weight:600;opacity:0.85;\">{$label}</div>"
                .  "<div style=\"font-size:1.5rem;font-weight:700;margin-top:0.25rem;\">{$value}</div>"
                .  ($sub ? "<div style=\"font-size:0.7rem;opacity:0.75;margin-top:0.25rem;\">{$sub}</div>" : '')
                .  "</div>";
        };
    @endphp

    <div style="{{ $tileGrid }}">
        {!! $tile('#dbeafe', '#1e3a8a', $t('stock_awal_qty'), number_format($s['stock_awal_qty']), $t('stock_awal_qty_sub')) !!}
        {!! $tile('#dbeafe', '#1e3a8a', $t('stock_awal_value'), $fmt($s['stock_awal_value']), $t('stock_awal_value_sub')) !!}
        {!! $tile('#bae6fd', '#0c4a6e', $t('stock_akhir_qty'), number_format($s['stock_akhir_qty']), $t('stock_akhir_qty_sub')) !!}
        {!! $tile('#bae6fd', '#0c4a6e', $t('stock_akhir_value'), $fmt($s['stock_akhir_value']), $t('stock_akhir_value_sub')) !!}
    </div>

    <div style="{{ $tileGrid }}">
        {!! $tile('#166534', '#ffffff', $t('total_sales'), $fmt($s['total_sales_gross']), $t('total_sales_sub')) !!}
        {!! $tile('#92400e', '#ffffff', $t('total_diskon'), $fmt($s['total_diskon']), $t('total_diskon_sub')) !!}
        {!! $tile('#991b1b', '#ffffff', $t('total_returns'), $fmt($s['total_returns']), $t('total_returns_sub')) !!}
        {!! $tile('#059669', '#ffffff', $t('total_omzet'), $fmt($s['total_omzet_net']), $t('total_omzet_sub')) !!}
        {!! $tile('#a16207', '#ffffff', $t('biaya_pengembangan'), $fmt($s['biaya_pengembangan']), $t('biaya_pengembangan_sub')) !!}
    </div>

    {{-- Margin numbers derive from Product.cost_price, which only Admin/Manager can
         see/edit on the Product resource (RoleGatedPolicy). Keep this row behind the
         same boundary so a Supervisor's branch dashboard doesn't leak cost data. --}}
    @if (auth()->user()->hasAnyRole(['Admin', 'Manager']))
        <div style="{{ $tileGrid }}">
            {!! $tile('#7c2d12', '#ffffff', $t('total_cogs'), $fmt($s['total_cogs']), $t('total_cogs_sub')) !!}
            {!! $tile('#065f46', '#ffffff', $t('total_gross_profit'), $fmt($s['total_gross_profit']), $t('total_gross_profit_sub', ['margin' => number_format($s['gross_margin_pct'], 1)])) !!}
        </div>
    @endif

    <div style="{{ $tileGrid }}">
        {{-- PDF dashboard semantics: the headline number is the ACTIVE gerai count
             ("Total Gerai: 2, Gerai Aktif"); registered-but-dormant mitra stay in the
             subtitle so the tile still discloses the full roster size. --}}
        {!! $tile('#334155', '#ffffff', $t('total_gerai'), (string) $s['gerai_aktif'], $t('total_gerai_sub', ['total' => $s['total_gerai']])) !!}
        {!! $tile('#1d4ed8', '#ffffff', $t('operasional_gerai'), $fmt($s['operasional_gerai'])) !!}
        {!! $tile('#3b82f6', '#ffffff', $t('biaya_sdm'), $fmt($s['biaya_sdm']), $t('biaya_sdm_sub', ['count' => $s['jumlah_sdm']])) !!}
        {!! $tile('#a16207', '#ffffff', $t('saldo_kas'), $fmt($s['saldo_kas'])) !!}
    </div>

    <div style="margin-top:1.5rem;overflow-x:auto;border-radius:0.5rem;border:1px solid #e5e7eb;">
        <table style="width:100%;font-size:0.875rem;border-collapse:collapse;">
            <thead>
                <tr style="background:#fbbf24;color:#000;">
                    <th style="padding:0.5rem;text-align:left;">{{ __('dashboard.cash_table.account') }}</th>
                    <th style="padding:0.5rem;text-align:left;">{{ __('dashboard.cash_table.holder') }}</th>
                    <th style="padding:0.5rem;text-align:right;">{{ __('dashboard.cash_table.balance') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($s['ringkasan_kas'] as $account)
                    <tr style="border-top:1px solid #e5e7eb;">
                        <td style="padding:0.5rem;">{{ $account->name }}</td>
                        <td style="padding:0.5rem;">{{ $account->holder_name }}</td>
                        <td style="padding:0.5rem;text-align:right;">{{ $fmt($account->balance) }}</td>
                    </tr>
                @endforeach
                <tr style="border-top:2px solid #9ca3af;font-weight:700;background:#000;color:#fff;">
                    <td style="padding:0.5rem;" colspan="2">{{ __('dashboard.cash_table.total') }}</td>
                    <td style="padding:0.5rem;text-align:right;">{{ $fmt($s['saldo_kas']) }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</x-filament-panels::page>
