@extends('layouts.tchub_app')

@section('content')
    <div class="row">
        <div class="col-md-12">
            @include('layouts.flash')
            <a href="{{ route('tchub.dashboard') }}" class="btn btn-link mb-2">&lt; {{ __('Back') }}</a>
            <h1>{{ __('TCHUB Pending Orders') }}</h1>
            <p>{{ __('Show all pending orders from ') }} <a href="https://tchub.sg" target="_blank">tchub.sg</a></p>

            <table class="mt-5 table">
                <thead>
                  <tr>
                    <th scope="col">{{ __('Reference ID') }}</th>
                    <th scope="col">{{ __('Purchase Date') }}</th>
                    <th scope="col">{{ __('Bill-to Name') }}</th>
                    <th scope="col">{{ __('Ship-to Name') }}</th>
                    <th scope="col">{{ __('Grand Total') }}</th>
                    <th scope="col">{{ __('Generate Sales Order') }}</th>
                  </tr>
                </thead>
                <tbody>
                    @forelse ($items['items'] as $order)
                        <tr>
                            <td>{{ $order['increment_id'] }}</td>
                            <td>{{ $order['created_at'] }}</td>
                            <td>{{ $order['billing_address']['firstname'] }} {{ $order['billing_address']['lastname'] }}</td>
                            <td>{{ $order['billing_address']['firstname'] }} {{ $order['billing_address']['lastname'] }}</td>
                            <td>{{ $order['global_currency_code'] }} {{ number_format($order['grand_total'], 2) }}</td>
                            <td>
                                <form action="{{ route('tchub.pending.order.store', $order['entity_id']) }}" method="post" onsubmit="submitBtn.disabled=true">
                                    @csrf
                                    <button name="submitBtn" onclick="return confirm('You are about to generate sales order on SAP B1 ref#{{$order['increment_id']}}. Continue?')" type="submit" class="btn btn-sm btn-dark">{{ __('Generate') }}</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center">
                                <img class="card-img-top" width="286px" height="180px" src="{{ asset('images/no_data.svg') }}" alt="Sales order" oncontextmenu="return false">
                                {{ __('No pending orders on') }} <a href="https://tchub.sg" target="_blank">tchub.sg</a></td>
                        </tr>
                    @endforelse
                </tbody>
              </table>
        </div>
    </div>
@endsection