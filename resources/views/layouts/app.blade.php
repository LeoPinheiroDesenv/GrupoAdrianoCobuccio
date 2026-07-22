<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Carteira Financeira')</title>
    <script src="https://unpkg.com/vue@3/dist/vue.global.prod.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f7fa; color: #333; min-height: 100vh; }
        .navbar { background: #1a1a2e; padding: 1rem 2rem; display: flex; justify-content: space-between; align-items: center; }
        .navbar h1 { color: #fff; font-size: 1.2rem; }
        .navbar nav a { color: #ccc; text-decoration: none; margin-left: 1.5rem; transition: color .2s; }
        .navbar nav a:hover { color: #fff; }
        .navbar .btn-logout { background: #e74c3c; color: #fff; border: none; padding: .4rem 1rem; border-radius: 4px; cursor: pointer; font-size: .85rem; }
        .container { max-width: 900px; margin: 2rem auto; padding: 0 1rem; }
        .card { background: #fff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,.08); padding: 2rem; margin-bottom: 1.5rem; }
        .card h2 { margin-bottom: 1rem; color: #1a1a2e; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; margin-bottom: .3rem; font-weight: 500; font-size: .9rem; }
        .form-group input { width: 100%; padding: .6rem; border: 1px solid #ddd; border-radius: 4px; font-size: 1rem; }
        .form-group input:focus { outline: none; border-color: #3498db; }
        .btn { padding: .7rem 1.5rem; border: none; border-radius: 4px; font-size: 1rem; cursor: pointer; transition: background .2s; }
        .btn-primary { background: #3498db; color: #fff; }
        .btn-primary:hover { background: #2980b9; }
        .btn-success { background: #27ae60; color: #fff; }
        .btn-success:hover { background: #219a52; }
        .btn-danger { background: #e74c3c; color: #fff; }
        .btn-danger:hover { background: #c0392b; }
        .btn-sm { padding: .4rem .8rem; font-size: .8rem; }
        .alert { padding: .8rem 1rem; border-radius: 4px; margin-bottom: 1rem; font-size: .9rem; }
        .alert-error { background: #fde8e8; color: #c0392b; border: 1px solid #f5c6cb; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .balance-display { font-size: 2.5rem; font-weight: 700; color: #27ae60; text-align: center; padding: 1rem; }
        .balance-display.negative { color: #e74c3c; }
        table { width: 100%; border-collapse: collapse; }
        table th, table td { padding: .7rem; text-align: left; border-bottom: 1px solid #eee; font-size: .9rem; }
        table th { background: #f8f9fa; font-weight: 600; }
        .badge { display: inline-block; padding: .2rem .5rem; border-radius: 3px; font-size: .75rem; font-weight: 600; }
        .badge-deposit { background: #d4edda; color: #155724; }
        .badge-transfer_sent { background: #fff3cd; color: #856404; }
        .badge-transfer_received { background: #cce5ff; color: #004085; }
        .badge-reversal { background: #f8d7da; color: #721c24; }
        .tabs { display: flex; gap: .5rem; margin-bottom: 1.5rem; }
        .tabs button { padding: .5rem 1rem; border: 1px solid #ddd; background: #fff; border-radius: 4px; cursor: pointer; }
        .tabs button.active { background: #3498db; color: #fff; border-color: #3498db; }
        .auth-container { max-width: 400px; margin: 4rem auto; padding: 0 1rem; }
        .auth-link { text-align: center; margin-top: 1rem; font-size: .9rem; }
        .auth-link a { color: #3498db; text-decoration: none; }
        .loading { opacity: .6; pointer-events: none; }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; }
        @media (max-width: 600px) { .grid-2 { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    @yield('content')
</body>
</html>
