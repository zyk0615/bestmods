<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    @include('includes.head')
    <body>
        @include('includes.background')

        <div class="mx-auto">
            @include('includes.header')
            @yield('content')
        </div>
    </body>
</html>
