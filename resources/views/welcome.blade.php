<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>Laravel</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,600&display=swap" rel="stylesheet" />

    </head>
    <body class="antialiased">

            <a href="https://www.clover.com/global-developer-home/coming-soon" target="_blank" rel="noopener noreferrer"><img src="https://sandbox.dev.clover.com/assets/images/clover-app-market-button.svg" alt="Install From Clover App Market"/></a>

            <form action="{{ url('/clover/payment') }}" method="POST">
                @csrf
                <input type="text" name="amount" placeholder="Amount">
                <button type="submit">Pay Now</button>
            </form>
            
    </body>
</html>
