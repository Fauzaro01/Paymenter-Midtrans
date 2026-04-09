@php
    $error = $error ?? 'An unexpected error occurred while processing your payment.';
@endphp

<div class="midtrans-error-container">
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <div class="alert-header">
            <h4 class="alert-title mb-0">
                <i class="fas fa-exclamation-circle"></i> Payment Error
            </h4>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <div class="alert-body mt-3">
            <p class="mb-2"><strong>{{ $error }}</strong></p>
            <hr>
            <p class="mb-0 text-muted small">
                <i class="fas fa-info-circle"></i> 
                <span id="error-detail"></span>
            </p>
        </div>
    </div>

    <div class="midtrans-error-actions mt-4">
        <button class="btn btn-primary" onclick="window.history.back()">
            <i class="fas fa-arrow-left"></i> Go Back
        </button>
        <a href="{{ route('invoices.index') }}" class="btn btn-secondary">
            <i class="fas fa-list"></i> View All Invoices
        </a>
    </div>

    <div class="midtrans-error-info mt-4 p-3 bg-light rounded">
        <h6 class="text-muted">
            <i class="fas fa-lightbulb"></i> Troubleshooting Tips:
        </h6>
        <ul class="mb-0 small text-muted">
            <li>Midtrans only accepts <strong>IDR</strong> currency. Please ensure your product is configured with IDR.</li>
            <li>Minimum payment amount is <strong>IDR 5,000</strong>.</li>
            <li>If you're testing, make sure <strong>Sandbox Mode</strong> is enabled in gateway settings.</li>
            <li>Contact support if the error persists.</li>
        </ul>
    </div>
</div>

<style>
    .midtrans-error-container {
        padding: 20px;
        max-width: 600px;
        margin: 20px auto;
    }

    .alert-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .midtrans-error-actions {
        display: flex;
        gap: 10px;
        justify-content: center;
    }

    .midtrans-error-info {
        border-left: 4px solid #ffc107;
    }

    .midtrans-error-info h6 {
        margin-bottom: 10px;
    }

    .midtrans-error-info ul {
        list-style-position: inside;
    }
</style>

@script
    <script>
        // Log error details if available
        const urlParams = new URLSearchParams(window.location.search);
        const errorCode = urlParams.get('error_code');
        const errorMsg = urlParams.get('error_msg');

        if (errorCode || errorMsg) {
            document.getElementById('error-detail').textContent = 
                'Error: ' + (errorMsg || errorCode || 'Unknown error');
        } else {
            document.getElementById('error-detail').textContent = 
                'Please check that your invoice is configured with IDR currency and minimum amount of IDR 5,000.';
        }

        // Log to browser console for debugging
        console.warn('Midtrans Payment Error:', {
            error: "{{ $error }}",
            errorCode: errorCode,
            errorMsg: errorMsg
        });
    </script>
@endscript
