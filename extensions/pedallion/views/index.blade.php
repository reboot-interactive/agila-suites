@extends('layouts.app')
@section('breadcrumb', 'Marketplace / Pedallion')

@section('content')
<div class="page-header">
    <h2>Pedallion Settings</h2>
</div>

@php
    $hasApiKey = !empty($setting->api_key ?? '');
    $logCount = count($logs);
@endphp

{{-- Tabs --}}
<div class="tabs mb-12" style="justify-content:flex-start;">
    <button class="tab active" data-tab="ped-tab-settings" type="button">Settings</button>
    <button class="tab" data-tab="ped-tab-explorer" type="button">API Explorer</button>
    <button class="tab" data-tab="ped-tab-logs" type="button">
        API Logs
        @if($logCount > 0)
            <span style="margin-left:6px; background:var(--surface); color:var(--text-secondary); font-size:11px; padding:1px 7px; border-radius:99px; font-weight:600;">{{ $logCount }}</span>
        @endif
    </button>
</div>

{{-- ═══ TAB 1 — SETTINGS ═══ --}}
<div id="ped-tab-settings">

    {{-- Connection Card --}}
    <div class="card mb-12">
        <h3 class="section-title mt-0">Connection</h3>

        @if($hasApiKey)
            <div style="display:inline-flex; align-items:center; gap:6px; margin-bottom:14px;">
                <span class="badge badge-green">Configured</span>
            </div>
        @else
            <div style="display:inline-flex; align-items:center; gap:6px; margin-bottom:14px;">
                <span class="badge badge-gray">Not configured</span>
            </div>
        @endif

        <form method="POST" action="{{ route('ext.pedallion.save') }}">
            @csrf
            <div style="display:flex; flex-direction:column; gap:14px;">
                <div>
                    <label>Base URL</label>
                    <input class="input" name="base_url" value="{{ old('base_url', $setting->base_url ?? 'https://api.pedallion.com/api/v1') }}">
                    <div class="hint">Pedallion API base URL.</div>
                </div>
                <div>
                    <label>API Key</label>
                    <input class="input" name="api_key" type="password" value="{{ old('api_key', $setting->api_key ?? '') }}">
                    <div class="hint">From your Pedallion Seller Dashboard → API Keys.</div>
                </div>
                <div>
                    <label class="d-flex items-center gap-8">
                        <input type="checkbox" name="enabled" value="1" {{ ($setting->enabled ?? false) ? 'checked' : '' }}>
                        <span>Enabled</span>
                    </label>
                </div>
                <div class="d-flex gap-8">
                    <button class="btn" type="submit">Save Settings</button>
                    <button class="btn secondary" type="button" id="ped-test-btn">Test Connection</button>
                </div>
            </div>
        </form>

        <div id="ped-test-result" class="mt-8 hidden">
            <pre style="background:var(--surface); padding:12px; border-radius:var(--radius-md); font-size:12px; max-height:200px; overflow:auto;"></pre>
        </div>
    </div>

    {{-- Sync Settings --}}
    <div class="card mb-12">
        <h3 class="section-title mt-0">Sync Settings</h3>
        <form method="POST" action="{{ route('ext.pedallion.sync_days') }}">
            @csrf
            <div class="d-flex gap-10 items-end">
                <div>
                    <label>Sync Window (days)</label>
                    <input class="input" type="number" name="sync_last_days" value="{{ $setting->sync_last_days ?? 14 }}" min="1" max="365" style="width:100px;">
                </div>
                <button class="btn" type="submit">Save</button>
            </div>
            <div class="hint mt-4">How many days back to fetch orders. Default: 14.</div>
        </form>

    </div>

    {{-- Cronjob Commands --}}
    <div class="card mb-12">
        <h3 class="section-title mt-0">Cronjob Commands</h3>
        <div class="hint" style="margin-bottom:14px;">
            Add these commands to your hosting panel's scheduled tasks (HestiaCP, cPanel, etc.).
        </div>

        @php
            $artisanPath = '/usr/bin/php ' . base_path('artisan');
        @endphp

        <div style="display:flex; flex-direction:column; gap:12px; margin-bottom:16px;">
            <div>
                <label class="text-xs" style="margin-bottom:4px; display:flex; align-items:center; gap:6px;">
                    <span style="font-weight:600; color:var(--text-primary);">Sync Orders</span>
                    <span class="text-muted">— Fetch orders from Pedallion</span>
                    <span style="margin-left:auto; color:var(--text-muted);">every 30 min</span>
                </label>
                <div style="display:flex; align-items:center; gap:8px; background:#0f172a; border:1px solid #334155; border-radius:var(--radius-sm); padding:10px 12px; overflow-x:auto;">
                    <code id="ped-cron-sync" style="flex:1; font-family:ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; font-size:12.5px; color:#e2e8f0; white-space:nowrap; user-select:all;">{{ $artisanPath }} pedallion:sync-orders</code>
                    <button type="button" class="btn small secondary" onclick="pedCopy('ped-cron-sync', this)" title="Copy" style="flex-shrink:0; height:28px; padding:0 10px; font-size:11px;">Copy</button>
                </div>
            </div>
            <div>
                <label class="text-xs" style="margin-bottom:4px; display:flex; align-items:center; gap:6px;">
                    <span style="font-weight:600; color:var(--text-primary);">Push Stock</span>
                    <span class="text-muted">— Push ERP stock quantities to Pedallion</span>
                    <span style="margin-left:auto; color:var(--text-muted);">every 15 min</span>
                </label>
                <div style="display:flex; align-items:center; gap:8px; background:#0f172a; border:1px solid #334155; border-radius:var(--radius-sm); padding:10px 12px; overflow-x:auto;">
                    <code id="ped-cron-push" style="flex:1; font-family:ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; font-size:12.5px; color:#e2e8f0; white-space:nowrap; user-select:all;">{{ $artisanPath }} pedallion:push-stock</code>
                    <button type="button" class="btn small secondary" onclick="pedCopy('ped-cron-push', this)" title="Copy" style="flex-shrink:0; height:28px; padding:0 10px; font-size:11px;">Copy</button>
                </div>
            </div>
        </div>

        <details>
            <summary style="cursor:pointer; font-size:12.5px; font-weight:600; color:var(--text-secondary); user-select:none;">How to set up cronjobs</summary>
            <ol style="margin:10px 0 0; padding-left:20px; font-size:13px; color:var(--text-secondary); line-height:1.8;">
                <li>Go to your hosting panel (HestiaCP, cPanel, etc.)</li>
                <li>Find <strong style="color:var(--text-primary);">Cron Jobs</strong> or <strong style="color:var(--text-primary);">Scheduled Tasks</strong></li>
                <li>Set the interval (e.g. <code style="font-family:ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; font-size:12px; background:#f1f5f9; padding:1px 6px; border-radius:4px; color:#0f172a;">*/30 * * * *</code> for every 30 min)</li>
                <li>Paste the command into the command field</li>
                <li>Save</li>
            </ol>
        </details>
    </div>

    {{-- Sync Timestamps --}}
    @if($setting->id ?? false)
    <div class="card">
        <h3 class="section-title mt-0">Last Sync Timestamps</h3>
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:16px;">
            <div>
                <div class="text-xs text-muted">Categories</div>
                <div class="text-sm">{{ $setting->last_category_sync_at?->diffForHumans() ?? 'Never' }}</div>
            </div>
            <div>
                <div class="text-xs text-muted">Manufacturers</div>
                <div class="text-sm">{{ $setting->last_manufacturer_sync_at?->diffForHumans() ?? 'Never' }}</div>
            </div>
            <div>
                <div class="text-xs text-muted">Products</div>
                <div class="text-sm">{{ $setting->last_product_sync_at?->diffForHumans() ?? 'Never' }}</div>
            </div>
            <div>
                <div class="text-xs text-muted">Orders</div>
                <div class="text-sm">{{ $setting->last_order_sync_at?->diffForHumans() ?? 'Never' }}</div>
            </div>
            <div>
                <div class="text-xs text-muted">Stock Push</div>
                <div class="text-sm">{{ $setting->last_stock_push_at?->diffForHumans() ?? 'Never' }}</div>
            </div>
        </div>
    </div>
    @endif
</div>

{{-- ═══ TAB 2 — API EXPLORER ═══ --}}
<div id="ped-tab-explorer" class="hidden">
    <div class="card">
        <h3 class="section-title mt-0">API Explorer</h3>
        <p class="text-sm text-muted mb-12">Send requests to the Pedallion API for testing and debugging.</p>

        {{-- Quick buttons --}}
        <div class="text-xs text-muted mb-6">Quick Access</div>
        <div class="d-flex gap-6 flex-wrap mb-12">
            <button type="button" class="btn btn-sm secondary" onclick="pedExplorer('GET','categories?per_page=5')">Categories</button>
            <button type="button" class="btn btn-sm secondary" onclick="pedExplorer('GET','categories/tree')">Category Tree</button>
            <button type="button" class="btn btn-sm secondary" onclick="pedExplorer('GET','manufacturers?per_page=5')">Manufacturers</button>
            <button type="button" class="btn btn-sm secondary" onclick="pedExplorer('GET','references')">References (Order Statuses)</button>
            <button type="button" class="btn btn-sm secondary" onclick="pedExplorer('GET','products?per_page=5')">Products</button>
            <button type="button" class="btn btn-sm secondary" onclick="pedExplorer('GET','orders?per_page=5')">Orders</button>
        </div>

        <div style="display:flex; gap:10px; align-items:flex-start; flex-wrap:wrap;">
            <div>
                <label class="text-xs text-muted">Method</label>
                <select class="input" id="ped-ex-method" style="width:100px;">
                    <option>GET</option>
                    <option>POST</option>
                    <option>PUT</option>
                    <option>PATCH</option>
                    <option>DELETE</option>
                </select>
            </div>
            <div style="flex:1; min-width:200px;">
                <label class="text-xs text-muted">Path</label>
                <input class="input" id="ped-ex-path" placeholder="e.g. products?per_page=10">
            </div>
        </div>

        <div class="mt-8">
            <label class="text-xs text-muted">Request Body (JSON)</label>
            <textarea class="input" id="ped-ex-body" rows="4" placeholder="{}"></textarea>
        </div>

        <div class="mt-8">
            <button class="btn" id="ped-ex-run" type="button">Send Request</button>
        </div>

        <div id="ped-ex-result" class="mt-12 hidden">
            <label class="text-xs text-muted">Response</label>
            <pre style="background:var(--surface); padding:12px; border-radius:var(--radius-md); font-size:12px; max-height:500px; overflow:auto; white-space:pre-wrap;"></pre>
        </div>
    </div>
</div>

{{-- ═══ TAB 3 — API LOGS ═══ --}}
<div id="ped-tab-logs" class="hidden">

    {{-- Logging toggle --}}
    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:16px;">
        <div>
            <span class="font-semibold text-sm">API Logging</span>
            <span class="hint" style="margin-left:8px;">Save API requests & responses for debugging.</span>
        </div>
        <label class="d-flex items-center gap-10" style="cursor:pointer;" id="ped-log-toggle">
            <span style="position:relative; display:inline-block; width:44px; height:24px;">
                <input type="checkbox" id="ped-log-cb" value="1"
                       {{ ($setting->logging_enabled ?? false) ? 'checked' : '' }}
                       style="position:absolute; opacity:0; width:0; height:0;">
                <span id="ped-log-track" style="position:absolute; inset:0; background:{{ ($setting->logging_enabled ?? false) ? 'var(--accent)' : 'var(--border)' }}; border-radius:12px; transition:background 0.2s;"></span>
                <span id="ped-log-knob" style="position:absolute; top:2px; left:{{ ($setting->logging_enabled ?? false) ? '22px' : '2px' }}; width:20px; height:20px; background:#fff; border-radius:50%; transition:left 0.2s; box-shadow:0 1px 3px rgba(0,0,0,.2);"></span>
            </span>
            <span id="ped-log-label" class="text-sm {{ ($setting->logging_enabled ?? false) ? 'font-bold' : 'text-muted' }}">
                {{ ($setting->logging_enabled ?? false) ? 'Enabled' : 'Disabled' }}
            </span>
        </label>
    </div>

    <div class="card">
        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:12px;">
            <h3 class="section-title mt-0" style="margin-bottom:0; border:none; padding:0;">API Logs</h3>
            @if($logCount > 0)
                <form method="POST" action="{{ route('ext.pedallion.clear_api_logs') }}" data-confirm="Delete all Pedallion API logs?">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn small danger">Delete All Logs</button>
                </form>
            @endif
        </div>

        @if($logCount > 0)
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width:140px;">Time</th>
                            <th style="width:70px;">Method</th>
                            <th>Endpoint</th>
                            <th style="width:80px;">Status</th>
                            <th style="width:70px;">Duration</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($logs as $log)
                            <tr>
                                <td class="text-sm">{{ $log->created_at->format('M d H:i:s') }}</td>
                                <td><span class="badge {{ $log->status_code >= 200 && $log->status_code < 300 ? 'badge-green' : 'badge-red' }}">{{ $log->method }}</span></td>
                                <td>
                                    <div><code>{{ $log->endpoint }}</code></div>
                                    <details style="margin-top:6px;">
                                        <summary>View</summary>
                                        <div style="margin-top:8px;">
                                            <div class="hint" style="margin-bottom:6px;">Request Body</div>
                                            <pre style="background:#0b1220; color:#e2e8f0; padding:12px; border-radius:var(--radius-md); overflow:auto; font-size:13px; max-height:220px;">{{ json_encode($log->request_body, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                            <div class="hint" style="margin:10px 0 6px;">Response</div>
                                            <pre style="background:#0b1220; color:#e2e8f0; padding:12px; border-radius:var(--radius-md); overflow:auto; font-size:13px; max-height:220px;">{{ json_encode($log->response_body, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                        </div>
                                    </details>
                                </td>
                                <td>{{ $log->status_code }}</td>
                                <td class="text-sm text-muted">{{ $log->duration_ms }}ms</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="text-center text-muted" style="padding:32px 0;">No API logs yet.</div>
        @endif
    </div>
</div>

@endsection

@push('styles')
<style>
.marketplace-settings-grid { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
@media(max-width:768px){ .marketplace-settings-grid { grid-template-columns:1fr; } }
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    var tabIds = ['ped-tab-settings','ped-tab-explorer','ped-tab-logs'];
    var storageKey = 'ped-active-tab';

    function switchTab(tabId) {
        document.querySelectorAll('[data-tab]').forEach(b => b.classList.toggle('active', b.dataset.tab === tabId));
        tabIds.forEach(id => document.getElementById(id).classList.toggle('hidden', id !== tabId));
    }

    // Tab clicking
    document.querySelectorAll('[data-tab]').forEach(btn => {
        btn.addEventListener('click', function() {
            switchTab(this.dataset.tab);
            localStorage.setItem(storageKey, this.dataset.tab);
        });
    });

    // Restore tab: URL param takes priority, then localStorage
    var urlTab = new URLSearchParams(window.location.search).get('tab');
    var savedTab = urlTab ? ('ped-tab-' + urlTab) : localStorage.getItem(storageKey);
    if (savedTab && tabIds.indexOf(savedTab) !== -1) {
        switchTab(savedTab);
    }

    // Test Connection
    document.getElementById('ped-test-btn')?.addEventListener('click', function() {
        const form = this.closest('form');
        const baseUrl = form.querySelector('[name=base_url]').value;
        const apiKey = form.querySelector('[name=api_key]').value;
        const resultDiv = document.getElementById('ped-test-result');
        const pre = resultDiv.querySelector('pre');

        pre.textContent = 'Testing...';
        resultDiv.classList.remove('hidden');

        fetch('{{ route("ext.pedallion.test") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                'Accept': 'application/json',
            },
            body: JSON.stringify({ base_url: baseUrl, api_key: apiKey })
        })
        .then(r => r.json())
        .then(data => { pre.textContent = JSON.stringify(data, null, 2); })
        .catch(err => { pre.textContent = 'Error: ' + err.message; });
    });

    // API Explorer
    document.getElementById('ped-ex-run')?.addEventListener('click', function() {
        const method = document.getElementById('ped-ex-method').value;
        const path = document.getElementById('ped-ex-path').value;
        const body = document.getElementById('ped-ex-body').value;
        const resultDiv = document.getElementById('ped-ex-result');
        const pre = resultDiv.querySelector('pre');

        pre.textContent = 'Sending...';
        resultDiv.classList.remove('hidden');

        fetch('{{ route("ext.pedallion.explorer_run") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                'Accept': 'application/json',
            },
            body: JSON.stringify({ method, path, body })
        })
        .then(r => r.json())
        .then(data => { pre.textContent = JSON.stringify(data, null, 2); })
        .catch(err => { pre.textContent = 'Error: ' + err.message; });
    });

});

function pedExplorer(method, path) {
    document.getElementById('ped-ex-method').value = method;
    document.getElementById('ped-ex-path').value = path;
    document.getElementById('ped-ex-body').value = '';
    document.getElementById('ped-ex-run').click();
}

// API Logging toggle (no refresh)
(function(){
    var cb = document.getElementById('ped-log-cb');
    if (!cb) return;
    var track = document.getElementById('ped-log-track');
    var knob = document.getElementById('ped-log-knob');
    var label = document.getElementById('ped-log-label');

    cb.addEventListener('change', function(){
        var enabled = cb.checked;
        // Instant visual update
        track.style.background = enabled ? 'var(--accent)' : 'var(--border)';
        knob.style.left = enabled ? '22px' : '2px';
        label.textContent = enabled ? 'Enabled' : 'Disabled';
        label.className = 'text-sm ' + (enabled ? 'font-bold' : 'text-muted');

        fetch('{{ route("ext.pedallion.toggle_logging") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                'Accept': 'application/json',
            },
            body: JSON.stringify({ logging_enabled: enabled ? 1 : 0 })
        }).catch(function(){
            // Revert on failure
            cb.checked = !enabled;
            track.style.background = !enabled ? 'var(--accent)' : 'var(--border)';
            knob.style.left = !enabled ? '22px' : '2px';
            label.textContent = !enabled ? 'Enabled' : 'Disabled';
            label.className = 'text-sm ' + (!enabled ? 'font-bold' : 'text-muted');
        });
    });
})();

function pedCopy(id, btn) {
    var el = document.getElementById(id);
    if (!el) return;
    navigator.clipboard.writeText(el.textContent.trim()).then(function() {
        var orig = btn.textContent;
        btn.textContent = 'Copied!';
        setTimeout(function() { btn.textContent = orig; }, 1500);
    });
}
</script>
@endpush
