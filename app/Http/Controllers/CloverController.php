<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CloverController extends Controller
{
    // Handle OAuth Callback and retrieve access token
    public function handleCloverCallback(Request $request)
    {
        $code = $request->input('code');

        $clientId = config('services.clover.app_id');
        $clientSecret = config('services.clover.app_secret');
        $redirectUri = config('services.clover.redirect');

        $tokenUrl = "https://sandbox.dev.clover.com/oauth/token";
        $response = Http::asForm()->post($tokenUrl, [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $redirectUri,
        ]);

        if ($response->successful()) {
            $accessToken = $response->json()['access_token'];
            session(['clover_access_token' => $accessToken]);

            Log::info('Access token retrieved successfully.');
            return redirect()->route('clover.make_payment'); // Redirect to payment page
        } else {
            Log::error('Failed to retrieve access token from Clover', ['response' => $response->body()]);
            return response()->json(['message' => 'Failed to retrieve access token'], 500);
        }
    }

    // Create a Clover order
    public function createCloverOrder(Request $request)
    {
        $accessToken = session('clover_access_token');

        if (!$accessToken) {
            Log::error('Access token not found.');
            return response()->json(['message' => 'Access token not found'], 401);
        }

        $merchantId = config('services.clover.merchant_id');
        $orderUrl = "https://sandbox.dev.clover.com/v3/merchants/{$merchantId}/orders";

        $response = Http::withToken($accessToken)->post($orderUrl, [
            'merchant' => [
                'id' => $merchantId
            ],
            'state' => 'open',
        ]);

        Log::info('Order Response Status: ' . $response->status());
        Log::info('Order Response Body: ' . $response->body());

        if ($response->successful()) {
            $orderId = $response->json()['id'];
            session(['clover_order_id' => $orderId]);

            Log::info('Order ID: ' . $orderId);
            return response()->json(['message' => 'Order created successfully!', 'order_id' => $orderId]);
        } else {
            Log::error('Failed to create order', ['response' => $response->body()]);
            return response()->json(['message' => 'Order creation failed!'], 500);
        }
    }

    // Make payment for the created Clover order
    public function makePayment()
    {
        $accessToken = session('clover_access_token');
        $orderId = session('clover_order_id');

        if (!$accessToken || !$orderId) {
            Log::error('Required data missing: Access token or Order ID not found.');
            return response()->json(['message' => 'Required data missing'], 400);
        }

        $merchantId = config('services.clover.merchant_id');
        $employeeId = config('services.clover.employee_id');

        $paymentUrl = "https://sandbox.dev.clover.com/v2/merchant/{$merchantId}/pay";

        $response = Http::withToken($accessToken)->post($paymentUrl, [
            'amount' => 100, // Example amount, should be dynamically set
            'currency' => 'USD',
            'tipAmount' => 0,
            'orderId' => $orderId,
            'employeeId' => $employeeId,
            'first6' => '424242',
            'last4' => '4242',
            'vaultedCard' => [
                'token' => '7297162975886668',
                'first6' => '424242',
                'last4' => '4242',
                'expirationDate' => '1224',
            ],
        ]);

        Log::info('Payment Response Status: ' . $response->status());
        Log::info('Payment Response Body: ' . $response->body());

        if ($response->successful()) {
            Log::info('Payment successful.');
            return response()->json(['message' => 'Payment successful!']);
        } else {
            Log::error('Payment failed', ['response' => $response->body()]);
            return response()->json(['message' => 'Payment failed!'], 500);
        }
    }

    // Redirect to Clover OAuth authorization URL
    public function redirectToClover()
    {
        $clientId = config('services.clover.app_id');
        $redirectUri = route('clover.callback');

        $url = "https://sandbox.dev.clover.com/oauth/authorize?client_id={$clientId}&redirect_uri={$redirectUri}&response_type=code";

        return redirect($url);
    }
}
