<div class="filament-widgets">
    <div style="padding:1rem;border-radius:8px;background:#fff;border:1px solid #eee;">
        <h3 style="margin:0 0 0.5rem 0;font-size:0.9rem;color:#333;">Sales (last 7 days)</h3>
        <div style="display:flex;gap:0.5rem;align-items:flex-end;">
            @foreach($series as $s)
                <div style="width:12%;text-align:center;">
                    <div style="height:{{ min(200, max(4, ($s['total'] / 10))) }}px;background:#3b82f6;border-radius:4px;margin-bottom:6px;"></div>
                    <div style="font-size:0.7rem;color:#666;">{{ 
                        \Carbon\Carbon::parse($s['day'])->format('d M') }}</div>
                </div>
            @endforeach
        </div>
    </div>
</div>
