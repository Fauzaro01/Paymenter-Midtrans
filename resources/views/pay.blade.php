@php
    $formattedAmount = number_format($amount, 0, ',', '.');
    $debugLabel = $debugMode ? ' (Sandbox Mode)' : '';
    $invoiceCode = $invoice->invoice_number ?? ('INV-' . $invoice->id);
@endphp

<div class="midtrans-payment-container">
    <div class="midtrans-card" id="midtrans-card-{{ $invoice->id }}">
        <div class="midtrans-card-header">
            <h5 class="mb-1">Pembayaran Midtrans{{ $debugLabel }}</h5>
            <p class="text-muted mb-0">Invoice {{ $invoiceCode }}</p>
        </div>

        <div class="midtrans-amount">Rp {{ $formattedAmount }}</div>

        <div class="midtrans-status" id="midtrans-status-{{ $invoice->id }}">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading Midtrans Snap...</span>
            </div>
            <p class="mt-3 text-muted mb-0">Menyiapkan gateway pembayaran...</p>
        </div>

        <div class="midtrans-error d-none" id="midtrans-error-{{ $invoice->id }}"></div>

        <div class="midtrans-actions" id="midtrans-actions-{{ $invoice->id }}">
            <button type="button" class="btn btn-primary" id="midtrans-pay-btn-{{ $invoice->id }}" disabled>
                Bayar Sekarang
            </button>
            <button type="button" class="btn btn-outline-secondary d-none" id="midtrans-retry-btn-{{ $invoice->id }}">
                Coba Lagi
            </button>
            <button type="button" class="btn btn-light" id="midtrans-back-btn-{{ $invoice->id }}">
                Kembali
            </button>
        </div>
    </div>
</div>

<style>
    .midtrans-payment-container {
        display: flex;
        justify-content: center;
        align-items: center;
        min-height: 260px;
        padding: 20px;
    }

    .midtrans-card {
        width: 100%;
        max-width: 560px;
        background: #fff;
        border: 1px solid #e9ecef;
        border-radius: 14px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.06);
        padding: 22px;
    }

    .midtrans-card-header h5 {
        font-weight: 700;
    }

    .midtrans-amount {
        margin-top: 14px;
        margin-bottom: 16px;
        font-size: 1.5rem;
        font-weight: 700;
        color: #0d6efd;
    }

    .midtrans-status {
        text-align: center;
        padding: 8px 0 4px;
    }

    .midtrans-status .spinner-border {
        width: 3rem;
        height: 3rem;
    }

    .midtrans-error {
        margin-top: 12px;
    }

    .midtrans-actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        margin-top: 16px;
    }

    .midtrans-actions .btn {
        min-width: 130px;
    }
</style>

@script
    eval(`
        window.queueMicrotask(function () {
            var invoiceId = "{{ $invoice->id }}";
            var guardKey = '__midtransSnapInitDone_' + invoiceId;
            if (window[guardKey]) return;
            window[guardKey] = true;

            var statusEl = document.getElementById('midtrans-status-' + invoiceId);
            var errorEl = document.getElementById('midtrans-error-' + invoiceId);
            var payBtn = document.getElementById('midtrans-pay-btn-' + invoiceId);
            var retryBtn = document.getElementById('midtrans-retry-btn-' + invoiceId);
            var backBtn = document.getElementById('midtrans-back-btn-' + invoiceId);

            if (!statusEl || !errorEl || !payBtn || !retryBtn || !backBtn) return;

            var setError = function (message) {
                errorEl.classList.remove('d-none');
                errorEl.textContent = '';
                var alertBox = document.createElement('div');
                alertBox.className = 'alert alert-danger mb-0';
                alertBox.textContent = message;
                errorEl.appendChild(alertBox);
                statusEl.classList.add('d-none');
                payBtn.disabled = true;
                retryBtn.classList.remove('d-none');
            };

            var setReady = function () {
                statusEl.classList.add('d-none');
                errorEl.classList.add('d-none');
                payBtn.disabled = false;
                retryBtn.classList.add('d-none');
            };

            var runPay = function () {
                if (!window.snap) {
                    setError('Gateway belum siap. Klik "Coba Lagi" untuk memuat ulang.');
                    return;
                }

                payBtn.disabled = true;

                var callbacks = new Object();
                callbacks.onSuccess = function (result) {
                    console.log('Payment Success:', result);
                    window.location.href = "{{ route('invoices.show', $invoice) }}?checkPayment=true&midtrans=success";
                };
                callbacks.onPending = function (result) {
                    console.log('Payment Pending:', result);
                    window.location.href = "{{ route('invoices.show', $invoice) }}?checkPayment=true&midtrans=pending";
                };
                callbacks.onError = function (result) {
                    console.error('Payment Error:', result);
                    var errorMsg = 'Pembayaran gagal. Silakan coba lagi.';
                    if (result && result.status_message) {
                        errorMsg = result.status_message;
                    }
                    setError(errorMsg);
                };
                callbacks.onClose = function () {
                    console.warn('Payment popup closed by user');
                    payBtn.disabled = false;
                };

                window.snap.pay("{{ $snapToken }}", callbacks);
            };

            var ensureSnapLoaded = function () {
                var existingScript = document.querySelector('script[data-midtrans-snap="1"]');
                if (window.snap) {
                    setReady();
                    return;
                }

                if (existingScript) {
                    var onExistingLoad = function () {
                        existingScript.removeEventListener('load', onExistingLoad);
                        existingScript.removeEventListener('error', onExistingError);
                        setReady();
                    };
                    var onExistingError = function () {
                        existingScript.removeEventListener('load', onExistingLoad);
                        existingScript.removeEventListener('error', onExistingError);
                        setError('Gagal memuat Midtrans Snap. Periksa koneksi lalu coba lagi.');
                    };
                    existingScript.addEventListener('load', onExistingLoad);
                    existingScript.addEventListener('error', onExistingError);
                    return;
                }

                var snapScript = document.createElement('script');
                snapScript.src = "https://app{{ $debugMode ? '.sandbox' : '' }}.midtrans.com/snap/snap.js";
                snapScript.setAttribute('data-client-key', "{{ $clientKey }}");
                snapScript.setAttribute('data-midtrans-snap', '1');
                snapScript.async = true;

                snapScript.onload = setReady;
                snapScript.onerror = function () {
                    setError('Gagal memuat Midtrans Snap. Periksa koneksi lalu coba lagi.');
                };

                document.body.appendChild(snapScript);
            };

            payBtn.addEventListener('click', runPay);
            retryBtn.addEventListener('click', function () {
                statusEl.classList.remove('d-none');
                errorEl.classList.add('d-none');
                retryBtn.classList.add('d-none');
                ensureSnapLoaded();
            });
            backBtn.addEventListener('click', function () {
                window.history.back();
            });

            ensureSnapLoaded();

            console.log('Midtrans Payment Info: orderId={{ $orderId }}, amount={{ $formattedAmount }}, invoiceId=' + invoiceId + ', debugMode={{ $debugMode ? 'true' : 'false' }}');
        });
    `);
@endscript
