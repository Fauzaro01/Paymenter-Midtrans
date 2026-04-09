<?php

namespace Paymenter\Extensions\Gateways\Midtrans;

use App\Classes\Extension\Gateway;
use App\Helpers\ExtensionHelper;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\View;

/**
 * Midtrans Payment Gateway Extension
 * 
 * Integrates Midtrans (PT Verifik Dua Komplementer) payment processing with Paymenter.
 * Supports both live and sandbox environments via Snap integration.
 * 
 * Features:
 * - Currency: IDR only
 * - Minimum amount: IDR 5,000
 * - Payment methods: Credit Card, Bank Transfer, E-Wallet, BNPL, etc.
 * - Webhook validation for async payment notifications
 * - Customer details support
 * - Comprehensive logging and error handling
 * 
 * @version 1.3.0
 * @author Fauzaro01
 * @website https://fauzaro.web.id
 */
class Midtrans extends Gateway
{
    /**
     * Boot the extension
     * Registers routes and view namespace for Midtrans templates
     */
    public function boot()
    {
        require __DIR__ . '/routes/web.php';
        View::addNamespace('gateways.midtrans', __DIR__ . '/resources/views');
    }

    /**
     * Get metadata about this gateway
     * @return array Gateway metadata (display_name, version, author, website)
     */
    public function getMetadata(): array
    {
        return [
            'display_name' => 'Midtrans',
            'version'      => '1.3.0',
            'author'       => 'fauzaro01',
            'website'      => 'https://fauzaro.web.id'  ,
        ];
    }

    /**
     * Get configuration fields for admin panel
     * Allows admins to configure Midtrans API credentials
     * 
     * @param array $values Existing configuration values
     * @return array Configuration field definitions
     */
    public function getConfig($values = [])
    {
        return [
            [
                'name' => 'server_key',
                'label' => 'Server Key',
                'type' => 'text',
                'description' => 'Find your server key in Midtrans dashboard.',
                'required' => true,
            ],
            [
                'name' => 'merchant_id',
                'label' => 'Merchant ID',
                'type' => 'text',
                'description' => 'Your Midtrans Merchant ID.',
                'required' => true,
            ],
            [
                'name' => 'client_key',
                'label' => 'Client Key',
                'type' => 'text',
                'description' => 'Your Midtrans client key.',
                'required' => true,
            ],
            [
                'name' => 'debug_mode',
                'label' => 'Enable Sandbox Mode',
                'type' => 'checkbox',
                'description' => 'Use the Midtrans sandbox environment for testing.',
                'required' => false,
            ],
        ];
    }

    public function pay($invoice, $total)
    {
        // Validate currency - Midtrans only supports IDR
        if ($invoice->currency_code !== "IDR") {
            return view('gateways.midtrans::error', [
                'error' => 'The product currency code must be "IDR" to make payments with Midtrans!',
            ]);
        }

        // Validate minimum amount
        if ($total < 5000) {
            return view('gateways.midtrans::error', [
                'error' => 'Minimum payment amount is IDR 5,000. Your current amount is too low.',
            ]);
        }

        try {
            // Generate unique order ID
            $orderId = 'PAYMENTER-' . $invoice->id . '-' . substr(hash('sha256', time() . uniqid()), 0, 16);
            $serverKey = $this->config('server_key');
            $debugMode = $this->config('debug_mode');

            // Midtrans API endpoints
            $url = $debugMode
                ? 'https://app.sandbox.midtrans.com/snap/v1/transactions'
                : 'https://app.midtrans.com/snap/v1/transactions';

            // Format item details from invoice
            $itemDetails = collect($invoice->items)->map(function ($item) {
                return [
                    'id'       => $item->id ?? uniqid(),
                    'price'    => (int) round($item->price),
                    'quantity' => $item->quantity ?? 1,
                    'name'     => substr($item->description ?? 'Item', 0, 50), // Limit name length
                ];
            })->toArray();

            // Prepare customer details if available
            $customerDetails = [];
            if ($invoice->user) {
                $customerDetails = [
                    'first_name' => $invoice->user->first_name ?? 'Customer',
                    'last_name'  => $invoice->user->last_name ?? '',
                    'email'      => $invoice->user->email ?? '',
                ];
            }

            // Build Midtrans API payload
            $payload = [
                'transaction_details' => [
                    'order_id'     => $orderId,
                    'gross_amount' => (int) round($total), // Midtrans expects integer
                ],
                'item_details'   => $itemDetails,
                'customer_details' => $customerDetails,
                'metadata' => [
                    'invoice_id' => $invoice->id,
                    'order_id'   => $orderId,
                ],
            ];

            // Set authorization header
            $headers = [
                'Accept'        => 'application/json',
                'Content-Type'  => 'application/json',
                'Authorization' => 'Basic ' . base64_encode($serverKey . ':'),
            ];

            \Log::info("Midtrans payment initiated", [
                'order_id' => $orderId,
                'invoice_id' => $invoice->id,
                'amount' => $total,
            ]);

            // Send request to Midtrans API
            $response = Http::withHeaders($headers)
                ->timeout(30)
                ->post($url, $payload);

            $json = $response->json();

            // Handle failed response
            if ($response->failed() || !isset($json['token'])) {
                $errorMessage = $json['error_message'] ?? 'Failed to create payment transaction';
                
                \Log::error("Midtrans API error", [
                    'order_id' => $orderId,
                    'status' => $response->status(),
                    'error' => $errorMessage,
                    'response' => $json,
                ]);

                ExtensionHelper::error('Midtrans', [
                    'message' => 'Midtrans payment request failed.',
                    'error' => $errorMessage,
                    'order_id' => $orderId,
                ]);

                return view('gateways.midtrans::error', [
                    'error' => 'Unable to process your payment. Please try again later.',
                ]);
            }

            // Return payment form with snap token
            return view('gateways.midtrans::pay', [
                'invoice'   => $invoice,
                'snapToken' => $json['token'],
                'clientKey' => $this->config('client_key'),
                'debugMode' => $debugMode,
                'orderId'   => $orderId,
                'amount'    => $total,
            ]);

        } catch (\Exception $e) {
            \Log::error("Midtrans pay() exception", [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return view('gateways.midtrans::error', [
                'error' => 'An unexpected error occurred. Please contact support.',
            ]);
        }
    }

    public function webhook(Request $request)
    {
        try {
            $data = $request->json()->all();
            
            \Log::debug('Midtrans webhook received', [
                'order_id' => $data['order_id'] ?? null,
                'transaction_status' => $data['transaction_status'] ?? null,
                'status_code' => $data['status_code'] ?? null,
            ]);

            // Validate required fields
            if (!isset($data['order_id'], $data['transaction_status'], $data['status_code'])) {
                \Log::warning("Midtrans webhook: Missing required fields");
                return response('Missing required fields', 400);
            }

            $statusCode = $data['status_code'];
            $transactionStatus = $data['transaction_status'];

            // Only process successful transactions (status 200 with capture or settlement)
            if ($statusCode !== "200" || !in_array($transactionStatus, ['capture', 'settlement'])) {
                \Log::info("Midtrans webhook: Ignoring non-successful transaction", [
                    'status_code' => $statusCode,
                    'transaction_status' => $transactionStatus,
                    'order_id' => $data['order_id'],
                ]);
                return response('OK', 200); // Return 200 to acknowledge receipt
            }

            // Parse order ID (format: PAYMENTER-{invoice_id}-{hash})
            $orderIdParts = explode('-', $data['order_id']);
            if (count($orderIdParts) < 2) {
                \Log::warning("Midtrans webhook: Invalid order_id format", ['order_id' => $data['order_id']]);
                return response('Invalid order_id format', 400);
            }

            $invoiceId = (int) $orderIdParts[1];

            // Validate amount and transaction ID
            if (!isset($data['gross_amount'], $data['transaction_id'])) {
                \Log::warning("Midtrans webhook: Missing amount or transaction_id", [
                    'order_id' => $data['order_id'],
                ]);
                return response('Missing amount or transaction_id', 400);
            }

            $amount = (float) $data['gross_amount'];
            $transactionId = $data['transaction_id'];

            // Additional validation
            if ($invoiceId <= 0 || $amount <= 0) {
                \Log::warning("Midtrans webhook: Invalid invoice_id or amount", [
                    'invoice_id' => $invoiceId,
                    'amount' => $amount,
                ]);
                return response('Invalid amount or invoice_id', 400);
            }

            \Log::info("Midtrans webhook: Processing payment", [
                'invoice_id' => $invoiceId,
                'amount' => $amount,
                'transaction_id' => $transactionId,
                'status' => $transactionStatus,
            ]);

            // Record payment in Paymenter
            ExtensionHelper::addPayment($invoiceId, 'Midtrans', $amount, null, $transactionId);

            \Log::info("Midtrans webhook: Payment recorded successfully", [
                'invoice_id' => $invoiceId,
                'transaction_id' => $transactionId,
            ]);

            return response('OK', 200);

        } catch (\Exception $e) {
            \Log::error("Midtrans webhook exception", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response('Internal Server Error', 500);
        }
    }

    /**
     * Check if this gateway can be used for a specific transaction
     * Validates currency (IDR only) and minimum amount (IDR 5,000)
     * 
     * @param float $total Transaction total amount
     * @param string $currency Currency code
     * @param string $type Transaction type
     * @param array $items Transaction items
     * @return bool True if gateway is available for this transaction
     */
    public function canUseGateway($total, $currency, $type, $items = [])
    {
        // Midtrans only supports IDR currency
        if ($currency != 'IDR') return false;
        
        // Minimum transaction amount is IDR 5,000
        if ($total < 5000) return false;

        return true;
    }
}
