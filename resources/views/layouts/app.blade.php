<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        <title>{{ $title ?? config('app.name') }}</title>

        {{-- @vite(['resources/css/app.css', 'resources/js/app.js']) --}}

        @livewireStyles
        @include('partials.css')
    </head>
    <body class="layout-fixed sidebar-expand-lg sidebar-open bg-body-tertiary">
        <div class="app-wrapper">
            @include('partials.navbar')
            @include('partials.sidebar')
            <main class="app-main">
                {{ $slot }}
            </main>
            @include('partials.footer')
        </div>
        @include('partials.js')
        

        @livewireScripts
         <!-- SweetAlert Script -->
        <script>
            // Handle SweetAlert dari session flash
            document.addEventListener('DOMContentLoaded', function() {
                @if(session('swal'))
                    Swal.fire({
                        icon: '{{ session('swal')['icon'] }}',
                        title: '{{ session('swal')['title'] }}',
                        text: '{{ session('swal')['text'] }}',
                        timer: 3000,
                        showConfirmButton: false
                    });
                @endif
                
                // Handle semua SweetAlert dari Livewire events
                window.addEventListener('swal', event => {
                    Swal.fire({
                        icon: event.detail.icon,
                        title: event.detail.title,
                        text: event.detail.text,
                        timer: 3000,
                        showConfirmButton: false
                    });
                });
            });
        </script>
    </body>
</html>
