@extends('layouts.app')

@section('content')
    <div class="container">
        <header class="mb-6">
            @if (Route::has('login'))
                <nav class="navbar navbar-expand-lg navbar-light bg-light">
                    <div class="container-fluid">
                        <a class="navbar-brand" href="#">Trading Portal</a>
                        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                            <span class="navbar-toggler-icon"></span>
                        </button>
                        <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
                            <ul class="navbar-nav">
                                @auth
                                    <li class="nav-item">
                                        <a class="nav-link" href="{{ url('/dashboard') }}">Dashboard</a>
                                    </li>
                                @else
                                    <li class="nav-item">
                                        <a class="nav-link" href="{{ route('login') }}">Log in</a>
                                    </li>
                                    @if (Route::has('register'))
                                        <li class="nav-item">
                                            <a class="nav-link" href="{{ route('register') }}">Register</a>
                                        </li>
                                    @endif
                                @endauth
                            </ul>
                        </div>
                    </div>
                </nav>
            @endif
        </header>

        <main class="text-center">
            <h1 class="mb-4">Welcome to Trading Portal</h1>
            <p class="mb-6 text-muted">Monitor and manage your trading bots with ease.</p>
            <a href="https://laravel.com/docs" target="_blank" class="btn btn-primary me-2">Read Documentation</a>
            <a href="https://laracasts.com" target="_blank" class="btn btn-secondary">Watch Tutorials</a>
        </main>
    </div>
@endsection
