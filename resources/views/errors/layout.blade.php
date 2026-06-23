<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Error') — {{ config('church.name', 'Church Platform') }}</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        html, body { height: 100%; }

        body {
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background-color: #ffffff;
            color: #171717;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        .wrapper {
            min-height: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 6rem 1.5rem;
            text-align: center;
        }

        .err-code {
            font-size: 6rem;
            line-height: 1;
            font-weight: 700;
            color: #f5f5f5;
            user-select: none;
            margin-bottom: 0.5rem;
        }

        .accent {
            width: 3rem;
            height: 0.25rem;
            border-radius: 9999px;
            background-color: #1e5aa8;
            margin: 0 auto 1.5rem;
        }

        .err-heading {
            font-size: 1.5rem;
            line-height: 2rem;
            font-weight: 600;
            color: #171717;
            margin-bottom: 0.5rem;
        }

        .err-body {
            font-size: 0.875rem;
            line-height: 1.5;
            color: #737373;
            max-width: 24rem;
            margin-bottom: 2rem;
        }

        .actions {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex-wrap: wrap;
            justify-content: center;
        }

        .btn-primary {
            display: inline-flex;
            align-items: center;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            background-color: #1e5aa8;
            color: #ffffff;
            font-size: 0.875rem;
            font-weight: 500;
            text-decoration: none;
            transition: background-color 0.15s ease;
        }
        .btn-primary:hover { background-color: #184a8c; }

        .btn-secondary {
            display: inline-flex;
            align-items: center;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            border: 1px solid #e5e5e5;
            background-color: transparent;
            color: #404040;
            font-size: 0.875rem;
            font-weight: 500;
            text-decoration: none;
            transition: background-color 0.15s ease;
        }
        .btn-secondary:hover { background-color: #fafafa; }

        .err-footer {
            margin-top: 3rem;
            font-size: 0.75rem;
            color: #d4d4d4;
        }
    </style>
</head>
<body>
    <div class="wrapper">

        {{-- Large watermark code --}}
        <p class="err-code">@yield('code', '500')</p>

        {{-- Accent line --}}
        <div class="accent"></div>

        {{-- Heading --}}
        <h1 class="err-heading">@yield('heading', 'Something went wrong')</h1>

        {{-- Body copy --}}
        <p class="err-body">@yield('body', 'An unexpected error occurred. Please try again or contact your administrator if the problem persists.')</p>

        {{-- Actions --}}
        <div class="actions">
            <a href="/" class="btn-primary">Go home</a>
            <a href="/contact" class="btn-secondary">Contact us</a>
        </div>

        {{-- Footer --}}
        <p class="err-footer">{{ config('church.name', 'Church Platform') }}</p>

    </div>
</body>
</html>
