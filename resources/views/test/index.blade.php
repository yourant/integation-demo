<html>
    <head></head>
    <body>
        <form method="POST" action="{{ route('test.login2') }}">
            @csrf
            <input type="submit" value="Get BP">

            @if (!empty($person))
                Card Code: {{ $person['CardCode'] }}
            @endif
        </form>
    </body>
</html>