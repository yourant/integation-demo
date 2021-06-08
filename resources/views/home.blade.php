@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">{{ __('Dashboard') }}</div>

                <div class="card-body">
                    @if (session('status'))
                        <div class="alert alert-success" role="alert">
                            {{ session('status') }}
                        </div>
                    @endif

                    {{ __('You are logged in!') }}

                    <form method="POST" action="{{ route('test.index') }}">
                        @csrf
                        <input type="submit" value="Get Data">
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
