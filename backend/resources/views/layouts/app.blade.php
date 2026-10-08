<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('app.name'))</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
<div class="shell">
    @include('layouts.partials.sidebar')

    <div class="main">
        <header class="topbar">
            <h1>@yield('heading', config('app.name'))</h1>
            <div class="user-chip">
                {{ auth()->user()?->name }}
                @if (auth()->user()?->isAdmin())
                    <span class="badge active">Admin</span>
                @else
                    <span class="badge info">Karyawan</span>
                @endif
            </div>
        </header>

        <main class="content">
            @if (session('success'))
                <div class="alert success">{{ session('success') }}</div>
            @endif
            @if (session('error'))
                <div class="alert error">{{ session('error') }}</div>
            @endif
            @if ($errors->any() && ! isset($hideFormErrors))
                <div class="alert error">
                    <strong>Periksa kembali isian Anda:</strong>
                    <ul style="margin:6px 0 0 18px;padding:0">
                        @foreach ($errors->all() as $message)
                            <li>{{ $message }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @yield('content')
        </main>
    </div>
</div>
</body>
</html>
