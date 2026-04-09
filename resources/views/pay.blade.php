@php
    $formattedAmount = number_format($amount, 0, ',', '.');
    $debugLabel = $debugMode ? ' (Sandbox Mode)' : '';
@endphp

<div class="midtrans-payment-container">
    <div class="midtrans-loading" id="midtrans-loading">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading Midtrans Snap...</span>
        </div>
        <p class="mt-3 text-muted">Preparing payment gateway{{ $debugLabel }}...</p>
    </div>

    <div id="midtrans-button-container" style="display: none;"></div>
</div>

<style>
    .midtrans-payment-container {
        display: flex;
        justify-content: center;
        align-items: center;
        min-height: 200px;
        padding: 20px;
    }

    .midtrans-loading {
        text-align: center;
    }

    .midtrans-loading .spinner-border {
        width: 3rem;
        height: 3rem;
    }
</style>

@script
    (function () {
        // Prevent duplicate init when Livewire re-renders
        if (window.__midtransSnapInitDone) return;
        window.__midtransSnapInitDone = true;

        var loadingEl = document.getElementById('midtrans-loading');

        var showInitError = function (message) {
            if (!loadingEl) return;
            loadingEl.innerHTML = '<div class="alert alert-danger">' + message + '</div>';
        };

        var startSnapPayment = function () {
            if (!window.snap) {
                console.error('Snap object not found');
                showInitError('Payment gateway failed to initialize. Please refresh and try again.');
                return;
            }

            if (loadingEl) loadingEl.style.display = 'none';

            window.snap.pay("{{ $snapToken }}", {
                onSuccess: function(result) {
                    console.log('Payment Success:', result);
                    window.location.href = "{{ route('invoices.show', $invoice) }}?checkPayment=true&midtrans=success";
                },
                onPending: function(result) {
                    console.log('Payment Pending:', result);
                    window.location.href = "{{ route('invoices.show', $invoice) }}?checkPayment=true&midtrans=pending";
                },
                onError: function(result) {
                    console.error('Payment Error:', result);
                    var errorMsg = result.status_message || 'Payment failed. Please try again.';
                    alert('Payment Error: ' + errorMsg);
                    window.history.back();
                },
                onClose: function() {
                    console.warn('Payment popup closed by user');
                    if (confirm('You closed the payment popup. Do you want to try again?')) {
                        location.reload();
                    } else {
                        window.history.back();
                    }
                }
            });
        };

        // Snap already loaded (possible after partial navigation)
        if (window.snap) {
            startSnapPayment();
            return;
        }

        var snapScript = document.createElement('script');
        snapScript.src = "https://app{{ $debugMode ? '.sandbox' : '' }}.midtrans.com/snap/snap.js";
        snapScript.setAttribute('data-client-key', "{{ $clientKey }}");
        snapScript.async = true;

        snapScript.onerror = function() {
            console.error('Failed to load Midtrans Snap library');
            showInitError('Failed to load payment gateway. Please refresh and try again.');
        };

        snapScript.onload = startSnapPayment;
        document.body.appendChild(snapScript);

        console.log('Midtrans Payment Info:', {
            orderId: "{{ $orderId }}",
            amount: "{{ $formattedAmount }}",
            invoiceId: "{{ $invoice->id }}",
            debugMode: {{ $debugMode ? 'true' : 'false' }}
        });
    })();
@endscript
