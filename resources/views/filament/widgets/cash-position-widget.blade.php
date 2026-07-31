<div class="filament-widgets">
    <div style="padding:1rem;border-radius:8px;background:#fff;border:1px solid #eee;">
        <h3 style="margin:0;font-size:0.9rem;color:#333;">Sales Revenue (balance)</h3>
        <div style="font-size:1.6rem;font-weight:600;">Rp {{ number_format($salesRevenue, 2) }}</div>
    </div>

    <div style="margin-top:0.75rem;padding:1rem;border-radius:8px;background:#fff;border:1px solid #eee;">
        <h4 style="margin:0 0 0.5rem 0;font-size:0.85rem;color:#333;">Top accounts</h4>
        <ul style="margin:0;padding-left:1rem;">
            @foreach($cashAccounts as $a)
                <li>{{ $a->account_code }} — Rp {{ number_format($a->balance, 2) }}</li>
            @endforeach
        </ul>
    </div>
</div>
