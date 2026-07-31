<div class="filament-widgets">
    <div style="display:flex;gap:1rem;align-items:center;">
        <div style="padding:1rem;border-radius:8px;background:#fff;border:1px solid #eee;flex:1;">
            <h3 style="margin:0;font-size:0.9rem;color:#333;">Total products</h3>
            <div style="font-size:1.6rem;font-weight:600;">{{ number_format($totalProducts) }}</div>
        </div>
        <div style="padding:1rem;border-radius:8px;background:#fff;border:1px solid #eee;flex:1;">
            <h3 style="margin:0;font-size:0.9rem;color:#333;">Low stock (&le; {{ $lowStockThreshold }})</h3>
            <div style="font-size:1.6rem;font-weight:600;color:{{ $lowStock ? '#d97706' : '#059669' }};">{{ number_format($lowStock) }}</div>
        </div>
        <div style="padding:1rem;border-radius:8px;background:#fff;border:1px solid #eee;flex:1;">
            <h3 style="margin:0;font-size:0.9rem;color:#333;">Negative stock</h3>
            <div style="font-size:1.6rem;font-weight:600;color:{{ $negativeStock ? '#dc2626' : '#059669' }};">{{ number_format($negativeStock) }}</div>
        </div>
    </div>
</div>
