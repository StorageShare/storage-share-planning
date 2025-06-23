<x-guest-layout>
    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    @if (session('error'))
        <div class="mb-4 font-medium text-sm text-red-600 dark:text-red-400 bg-red-100 dark:bg-red-900/50 border border-red-200 dark:border-red-800/50 rounded-md p-4">
            {{ session('error') }}
        </div>
    @endif

    <div class="py-4 text-center">
        <a href="/">
            <img src="{{ asset('images/logo-staand-dark-blue.png') }}" alt="Storage Share Logo" class="h-20 w-auto inline-block mb-4">
        </a>
        <p class="text-gray-600 dark:text-gray-400 mb-4">
            Inloggen is alleen mogelijk met een geautoriseerd @storage-share.nl Google account.
        </p>
        <a href="{{ route('auth.google.redirect') }}" class="inline-flex items-center justify-center px-4 py-2 bg-gray-800 dark:bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-white dark:text-gray-800 uppercase tracking-widest hover:bg-gray-700 dark:hover:bg-white focus:bg-gray-700 dark:focus:bg-white active:bg-gray-900 dark:active:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
            <svg class="w-4 h-4 mr-2" viewBox="0 0 48 48">
                <path fill="#4285F4" d="M24 9.5c3.9 0 6.9 1.6 9.1 3.6l6.8-6.8C35.9 2.5 30.5 0 24 0 14.9 0 7.3 5.4 3 13.2l8.3 6.5C13.1 13.2 18.1 9.5 24 9.5z"></path>
                <path fill="#34A853" d="M46.2 25.4c0-1.7-.1-3.3-.4-4.9H24v9.4h12.4c-.5 3-2.2 5.5-4.8 7.3l7.6 5.9c4.4-4.1 7-10.1 7-17.7z"></path>
                <path fill="#FBBC05" d="M11.3 26.2c-.4-1.2-.6-2.5-.6-3.8s.2-2.6.6-3.8l-8.3-6.5C1.1 15.6 0 19.7 0 24s1.1 8.4 3 11.9l8.3-6.5z"></path>
                <path fill="#EA4335" d="M24 48c6.5 0 11.9-2.1 15.8-5.7l-7.6-5.9c-2.1 1.4-4.8 2.3-7.9 2.3-5.9 0-10.9-3.7-12.7-8.8l-8.3 6.5C7.3 42.6 14.9 48 24 48z"></path>
                <path fill="none" d="M0 0h48v48H0z"></path>
            </svg>
            {{ __('Log in with Google') }}
        </a>
    </div>
</x-guest-layout>
