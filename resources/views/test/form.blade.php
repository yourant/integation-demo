<html>
    <head></head>
    <body>
        <form method="POST" action="{{ route('test.login') }}">
            @csrf
            DB Name <input type="text" name="db"><br>
            User Name <input type="text" name="uname"><br>
            Password <input type="password" name="pword"><br>
            <input type="submit">
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <input type="submit" value="Logout">
        </form>
    </body>
</html>