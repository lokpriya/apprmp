<x-guest-layout>

    @if (session('status'))
    <div class="alert alert-success d-flex align-items-center gap-2 mb-3 py-2 px-3" style="font-size:0.82rem; border-radius:8px;">
        <i class="bi bi-check-circle-fill"></i>
        {{ session('status') }}
    </div>
    @endif

    <p class="text-muted text-center mb-4" style="font-size:0.82rem;">
        Enter your staff email address and we'll send you a password reset link.
    </p>

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <div class="mb-4">
            <label for="email" class="form-label">Email Address</label>
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                <input
                    id="email"
                    type="email"
                    name="email"
                    value="{{ old('email') }}"
                    class="form-control @error('email') is-invalid @enderror"
                    placeholder="staff@municipality.gov.np"
                    required
                    autofocus
                >
                @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div class="d-grid mb-3">
            <button type="submit" class="btn btn-login btn-primary text-white">
                <i class="bi bi-send me-1"></i> Send Reset Link
            </button>
        </div>

        <div class="text-center">
            <a href="{{ route('login') }}" class="text-decoration-none" style="font-size:0.8rem; color:#1565c0;">
                <i class="bi bi-arrow-left me-1"></i>Back to Sign In
            </a>
        </div>
    </form>

</x-guest-layout>
