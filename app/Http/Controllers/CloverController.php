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
        Log::info('Received code: ' . $code);
    
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
            $data = $response->json();
            Log::info('Token response: ' . json_encode($data));
    
            // Check and log the presence of refresh_token and expires_in
            $hasRefreshToken = isset($data['refresh_token']);
            $hasExpiresIn = isset($data['expires_in']);
    
            if (!$hasRefreshToken) {
                Log::warning('Refresh token not provided in response.');
            }
    
            if (!$hasExpiresIn) {
                Log::warning('Expires_in not provided in response.');
            }
    
            // Store tokens and expiration if available
            session([
                'clover_access_token' => $data['access_token'],
                'clover_refresh_token' => $data['refresh_token'] ?? null,
                'clover_token_expires_in' => $hasExpiresIn ? now()->addSeconds($data['expires_in']) : null,
            ]);
    
            Log::info('Access token retrieved successfully.');
            return redirect()->route('clover.create_order');
        } else {
            Log::error('Failed to retrieve access token from Clover', ['response' => $response->body()]);
            return response()->json(['message' => 'Failed to retrieve access token'], 500);
        }
    }
    
    public function refreshCloverToken()
    {
        $refreshToken = session('clover_refresh_token');
        $clientId = config('services.clover.app_id');
        $clientSecret = config('services.clover.app_secret');
        $tokenUrl = "https://sandbox.dev.clover.com/oauth/token";
    
        $response = Http::asForm()->post($tokenUrl, [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token',
        ]);
    
        if ($response->successful()) {
            $data = $response->json();
            session([
                'clover_access_token' => $data['access_token'],
                'clover_refresh_token' => $data['refresh_token'], // refresh_token may also change
                'clover_token_expires_in' => now()->addSeconds($data['expires_in']),
            ]);
    
            Log::info('Access token refreshed successfully.');
            return true;
        } else {
            Log::error('Failed to refresh access token', ['response' => $response->body()]);
            return false;
        }
    }
        


    // Create a simple Clover order
    public function createCloverOrder(Request $request)
{
    $tokenExpiresAt = session('clover_token_expires_in');

    // If token expiration is known, check it
    if ($tokenExpiresAt && now()->greaterThan($tokenExpiresAt)) {
        if (!$this->refreshCloverToken()) {
            return response()->json(['message' => 'Failed to refresh access token'], 500);
        }
    }

    $accessToken = session('clover_access_token');
    $merchantId = config('services.clover.merchant_id');
    $orderUrl = "https://sandbox.dev.clover.com/v3/merchants/{$merchantId}/orders";

    $response = Http::withToken($accessToken)->post($orderUrl, [
        'merchant' => ['id' => $merchantId],
        'state' => 'open',
    ]);

    if ($response->successful()) {
        $orderId = $response->json()['id'];
        session(['clover_order_id' => $orderId]);

        Log::info('Order created successfully: ' . $orderId);
        return response()->json(['message' => 'Order created successfully!', 'order_id' => $orderId]);
    } else {
        Log::error('Failed to create order', ['response' => $response->body()]);
        return response()->json(['message' => 'Order creation failed!'], 500);
    }
}

    
    // Add item to the created Clover order
    public function addItemToOrder(Request $request)
    {
        $accessToken = session('clover_access_token');
        $orderId = session('clover_order_id');

        if (!$accessToken || !$orderId) {
            Log::error('Required data missing: Access token or Order ID not found.');
            return response()->json(['message' => 'Required data missing'], 400);
        }

        $merchantId = config('services.clover.merchant_id');
        $orderItemUrl = "https://sandbox.dev.clover.com/v3/merchants/{$merchantId}/orders/{$orderId}/line_items";

        $response = Http::withToken($accessToken)->post($orderItemUrl, [
            'name' => 'Test Item', // Example item name
            'price' => 100, // Example price in cents
            'quantity' => 1,
        ]);

        Log::info('Add Item Response Status: ' . $response->status());
        Log::info('Add Item Response Body: ' . $response->body());

        if ($response->successful()) {
            $itemId = $response->json()['id'];
            Log::info('Item added to order successfully. Item ID: ' . $itemId);
            return response()->json(['message' => 'Item added successfully!', 'item_id' => $itemId]);
        } else {
            Log::error('Failed to add item to order', ['response' => $response->body()]);
            return response()->json(['message' => 'Failed to add item to order!'], 500);
        }
    }

    // Make a simple payment for the created Clover order
    public function makePayment()
    {
        $accessToken = session('clover_access_token');
        $orderId = session('clover_order_id');
    
        Log::info('Session Data', ['access_token' => $accessToken, 'order_id' => $orderId]);
    
        if (!$accessToken || !$orderId) {
            Log::error('Required data missing: Access token or Order ID not found.');
            return response()->json(['message' => 'Required data missing'], 400);
        }

        $merchantId = config('services.clover.merchant_id');
        $employeeId = config('services.clover.employee_id');
        $paymentUrl = "https://sandbox.dev.clover.com/v2/merchant/{$merchantId}/pay";

        $response = Http::withToken($accessToken)->post($paymentUrl, [
            'amount' => 100, // Example amount in cents
            'currency' => 'USD',
            'tipAmount' => 0,
            'orderId' => $orderId,
            'employeeId' => $employeeId,
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
        $scopes = 'READ_ORDERS WRITE_ORDERS'; // Example scopes
    
        $url = "https://sandbox.dev.clover.com/oauth/authorize?client_id={$clientId}&redirect_uri={$redirectUri}&response_type=code&scope={$scopes}";
    
        Log::info('Redirecting to Clover OAuth: ' . $url);
        return redirect($url);
    }
    
}
