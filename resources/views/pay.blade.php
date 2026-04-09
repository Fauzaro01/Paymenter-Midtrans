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
    <script type="text/javascript">
        // Load Midtrans Snap library
        const snapScript = document.createElement('script');
        snapScript.src = "https://app{{ $debugMode ? '.sandbox' : '' }}.midtrans.com/snap/snap.js";
        snapScript.setAttribute('data-client-key', "{{ $clientKey }}");
        snapScript.async = true;

        snapScript.onerror = function() {
            console.error('Failed to load Midtrans Snap library');
            document.getElementById('midtrans-loading').innerHTML = 
                '<div class="alert alert-danger">Failed to load payment gateway. Please refresh and try again.</div>';
        };

        snapScript.onload = function() {
            // Hide loading spinner
            document.getElementById('midtrans-loading').style.display = 'none';

            // Trigger Snap payment
            if (window.snap) {
                window.snap.pay("{{ $snapToken }}", {
                    onSuccess: function(result) {
                        console.log('Payment Success:', result);
                        // Redirect to invoice with payment confirmation
                        window.location.href = "{{ route('invoices.show', $invoice) }}?checkPayment=true&midtrans=success";
                    },
                    onPending: function(result) {
                        console.log('Payment Pending:', result);
                        // Redirect to invoice to check payment status
                        window.location.href = "{{ route('invoices.show', $invoice) }}?checkPayment=true&midtrans=pending";
                    },
                    onError: function(result) {
                        console.error('Payment Error:', result);
                        // Show error message and provide retry option
                        const errorMsg = result.status_message || 'Payment failed. Please try again.';
                        alert('Payment Error: ' + errorMsg);
                        window.history.back();
                    },
                    onClose: function() {
                        console.warn('Payment popup closed by user');
                        // User closed the popup without completing payment
                        if (confirm('You closed the payment popup. Do you want to try again?')) {
                            location.reload();
                        } else {
                            window.history.back();
                        }
                    }
                });
            } else {
                console.error('Snap object not found');
                document.getElementById('midtrans-loading').innerHTML = 
                    '<div class="alert alert-danger">Payment gateway failed to initialize. Please refresh and try again.</div>';
            }
        };

        // Append script to document
        document.body.appendChild(snapScript);

        // Debug info
        console.log('Midtrans Payment Info:', {
            orderId: "{{ $orderId }}",
            amount: "{{ $formattedAmount }}",
            invoiceId: "{{ $invoice->id }}",
            debugMode: {{ $debugMode ? 'true' : 'false' }}
        });
    </script>
@endscript
