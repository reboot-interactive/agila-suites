@props([
    'invoice' => null,   // The buyer_invoice array, or null if never queried
])

@php
    $state = 'unavailable';
    if (is_array($invoice)) {
        if (($invoice['is_requested'] ?? null) === true)       $state = 'requested';
        elseif (($invoice['is_requested'] ?? null) === false)  $state = 'declined';
    }

    $name     = is_array($invoice) ? (string) ($invoice['name']  ?? '') : '';
    $type     = is_array($invoice) ? (string) ($invoice['type']  ?? '') : '';
    $tin      = is_array($invoice) ? (string) ($invoice['tin']   ?? '') : '';
    $email    = is_array($invoice) ? (string) ($invoice['email'] ?? '') : '';
    $phone    = is_array($invoice) ? (string) ($invoice['phone'] ?? '') : '';
    $address  = is_array($invoice) && is_array($invoice['address'] ?? null) ? $invoice['address'] : [];
    $fullAddr = (string) ($address['full'] ?? '');
@endphp

<div class="card mt-16">
    <h3 class="section-title mt-0">Invoice Request</h3>

    @if($state === 'requested')
        <div class="inv-card">
            <div class="inv-card__header">
                <span class="inv-card__chip">
                    <svg width="11" height="11" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M3 1.5h7.5L13 4v10a.5.5 0 0 1-.5.5h-9a.5.5 0 0 1-.5-.5V2a.5.5 0 0 1 .5-.5z"/>
                        <path d="M5.5 6.5h5"/><path d="M5.5 9h5"/><path d="M5.5 11.5h3"/>
                    </svg>
                    Requested by buyer
                </span>
            </div>

            <div class="inv-modal-body" style="max-width:640px;">
                <div class="inv-row">
                    <span class="inv-row__label">Buyer</span>
                    <span class="inv-row__value">{{ $name !== '' ? $name : '—' }}</span>
                    <span></span>
                </div>
                @if($type !== '')
                    <div class="inv-row">
                        <span class="inv-row__label">Type</span>
                        <span class="inv-row__value">{{ ucfirst($type) }}</span>
                        <span></span>
                    </div>
                @endif
                <div class="inv-row">
                    <span class="inv-row__label">TIN</span>
                    <span class="inv-row__value inv-row__value--mono">{{ $tin !== '' ? $tin : '—' }}</span>
                    @if($tin !== '')
                        <button type="button" class="inv-copy-btn" data-copy="{{ $tin }}">Copy</button>
                    @else
                        <span></span>
                    @endif
                </div>
                <div class="inv-row">
                    <span class="inv-row__label">Email</span>
                    <span class="inv-row__value {{ $email === '' ? 'inv-row__value--muted' : '' }}">{{ $email !== '' ? $email : '—' }}</span>
                    @if($email !== '')
                        <button type="button" class="inv-copy-btn" data-copy="{{ $email }}">Copy</button>
                    @else
                        <span></span>
                    @endif
                </div>
                <div class="inv-row">
                    <span class="inv-row__label">Phone</span>
                    <span class="inv-row__value {{ $phone === '' ? 'inv-row__value--muted' : '' }}">{{ $phone !== '' ? $phone : '—' }}</span>
                    <span></span>
                </div>
                <div class="inv-row">
                    <span class="inv-row__label">Address</span>
                    <span class="inv-row__value">{{ $fullAddr !== '' ? $fullAddr : '—' }}</span>
                    @if($fullAddr !== '')
                        <button type="button" class="inv-copy-btn" data-copy="{{ $fullAddr }}">Copy</button>
                    @else
                        <span></span>
                    @endif
                </div>
            </div>
        </div>
    @elseif($state === 'declined')
        <div class="inv-card inv-card--empty">Buyer did not request an invoice.</div>
    @else
        <div class="inv-card inv-card--empty">Invoice information not available from Shopee.</div>
    @endif
</div>
