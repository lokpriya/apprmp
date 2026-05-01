<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @php
        $lgGuest = \App\Models\LocalGovernment::select('id','name_en','name_np','logo','district_id')
            ->with('district.province')
            ->first();
    @endphp

    <title>{{ $lgGuest?->name_en ?? config('app.name') }} — Staff Portal</title>

    @vite(['resources/css/guest.css'])
    @livewireStyles

    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #1e3a5f 0%, #1565c0 50%, #0d47a1 100%);
            min-height: 100vh;
            position: relative;
        }
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background:
                radial-gradient(ellipse at 20% 80%, rgba(255,255,255,0.05) 0%, transparent 50%),
                radial-gradient(ellipse at 80% 20%, rgba(255,255,255,0.08) 0%, transparent 50%);
            pointer-events: none;
        }
        .login-card {
            border-radius: 16px;
            border: 1px solid rgba(255,255,255,0.15);
            box-shadow: 0 20px 60px rgba(0,0,0,0.35), 0 4px 20px rgba(0,0,0,0.2);
            backdrop-filter: blur(10px);
            background: rgba(255,255,255,0.97);
            width: 100%;
            max-width: 420px;
        }
        .logo-ring {
            width: 88px;
            height: 88px;
            border-radius: 50%;
            border: 3px solid rgba(21,101,192,0.25);
            box-shadow: 0 4px 20px rgba(21,101,192,0.3);
            object-fit: contain;
            background: #fff;
            padding: 6px;
        }
        .logo-placeholder {
            width: 88px;
            height: 88px;
            border-radius: 50%;
            background: linear-gradient(135deg, #1565c0, #0d47a1);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: 700;
            color: #fff;
            box-shadow: 0 4px 20px rgba(21,101,192,0.4);
            flex-shrink: 0;
        }
        .login-card .card-header {
            background: linear-gradient(135deg, #1565c0, #0d47a1);
            border-radius: 16px 16px 0 0 !important;
            border: none;
            padding: 28px 28px 24px;
            text-align: center;
        }
        .form-control, .form-select {
            border-radius: 8px;
            border: 1.5px solid #d1d5db;
            padding: 10px 14px;
            font-size: 0.875rem;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .form-control:focus, .form-select:focus {
            border-color: #1565c0;
            box-shadow: 0 0 0 3px rgba(21,101,192,0.12);
        }
        .input-group-text {
            border-radius: 8px 0 0 8px;
            border: 1.5px solid #d1d5db;
            border-right: none;
            background: #f8fafc;
            color: #6b7280;
        }
        .input-group .form-control {
            border-radius: 0 8px 8px 0;
            border-left: none;
        }
        .input-group .form-control:focus {
            border-left: none;
            box-shadow: none;
        }
        .input-group:focus-within .input-group-text {
            border-color: #1565c0;
        }
        .input-group:focus-within .form-control {
            border-color: #1565c0;
            box-shadow: 0 0 0 3px rgba(21,101,192,0.12);
        }
        .btn-login {
            background: linear-gradient(135deg, #1565c0, #0d47a1);
            border: none;
            border-radius: 8px;
            padding: 11px;
            font-weight: 600;
            font-size: 0.9rem;
            letter-spacing: 0.3px;
            transition: all 0.2s;
            box-shadow: 0 4px 12px rgba(21,101,192,0.35);
        }
        .btn-login:hover {
            background: linear-gradient(135deg, #0d47a1, #093a8c);
            box-shadow: 0 6px 18px rgba(21,101,192,0.45);
            transform: translateY(-1px);
        }
        .btn-login:active {
            transform: translateY(0);
        }
        .form-check-input:checked {
            background-color: #1565c0;
            border-color: #1565c0;
        }
        .form-label {
            font-size: 0.8rem;
            font-weight: 600;
            color: #374151;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
        }
        .invalid-feedback { font-size: 0.78rem; }
        .nepal-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: rgba(255,255,255,0.12);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 20px;
            padding: 3px 10px;
            font-size: 0.7rem;
            color: rgba(255,255,255,0.85);
            margin-top: 4px;
        }
        .card-footer {
            border-radius: 0 0 16px 16px !important;
            border-top: 1px solid #f3f4f6;
        }
        .toggle-pw { cursor: pointer; }
    </style>
</head>
<body>
    <div class="min-vh-100 d-flex align-items-center justify-content-center p-3">
        <div class="login-card">

            {{-- ── Card Header with LG branding ── --}}
            <div class="card-header">
                <div class="d-flex flex-column align-items-center gap-2">
                    @if($lgGuest?->logo && file_exists(storage_path('app/public/'.$lgGuest->logo)))
                        <img src="{{ Storage::url($lgGuest->logo) }}" alt="Logo" class="logo-ring">
                    @else
                        <div class="logo-placeholder">
                            {{ mb_substr($lgGuest?->name_en ?? 'L', 0, 1) }}
                        </div>
                    @endif

                    <div class="mt-1">
                        <h5 class="text-white fw-bold mb-0 lh-sm">
                            {{ $lgGuest?->name_en ?? config('app.name') }}
                        </h5>
                        @if($lgGuest?->name_np)
                        <p class="text-white opacity-75 mb-0" style="font-size:0.85rem;">
                            {{ $lgGuest->name_np }}
                        </p>
                        @endif
                        <div class="d-flex justify-content-center mt-1">
                            <span class="nepal-badge">
                                <i class="bi bi-geo-alt-fill"></i>
                                @if($lgGuest?->district?->province)
                                    {{ $lgGuest->district->province->name_en }} &mdash;
                                @endif
                                {{ $lgGuest?->district?->name_en ?? 'Nepal' }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ── Card Body with login form ── --}}
            <div class="card-body px-4 pt-4 pb-2">
                <p class="text-center text-muted mb-4" style="font-size:0.82rem; font-weight:500; letter-spacing:0.3px;">
                    <i class="bi bi-shield-lock me-1"></i>STAFF PORTAL — SIGN IN
                </p>

                {{ $slot }}
            </div>

            {{-- ── Card Footer ── --}}
            <div class="card-footer bg-transparent text-center py-3 px-4">
                <a href="{{ route('public.dashboard') }}"
                   class="text-decoration-none d-inline-flex align-items-center gap-1"
                   style="font-size:0.78rem; color:#1565c0;">
                    <i class="bi bi-globe2"></i>
                    View Public Portal
                </a>
            </div>
        </div>
    </div>

    @livewireScripts
</body>
</html>
