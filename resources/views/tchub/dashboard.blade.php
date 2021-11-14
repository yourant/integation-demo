@extends('layouts.tchub_app')

@section('content')
<div class="row justify-content-center">
    <div class="card border-dark">
        <div class="card-header">
            <strong>{{ __('DASHBOARD') }}</strong>
        </div>
        <div class="card-body">
            <div class="card-deck">
                <div class="card">
                    <img class="card-img-top" width="286px" height="180px" src="{{ asset('images/sales_order.svg') }}" alt="Sales order" oncontextmenu="return false">
                    <div class="card-body">
                        <h5 class="card-title">{{ __('Sales Order') }} <img src="{{ asset('images/question_mark.svg') }}" alt="description" width="16px;" height="16px;" data-toggle="tooltip" data-placement="top" title="Generate sales order from TCHUB to SAP B1" oncontextmenu="return false"></h5>
                        <p class="card-text">{{ __('Generate sales order from tchub.sg') }}</p>
                        <a href="#" class="btn btn-dark btn-block">{{ __('Generate Sales Order') }}</a>
                    </div>
                </div>
                <div class="card">
                    <img class="card-img-top" width="286" height="180" src="{{ asset('images/delivery_order.svg') }}" alt="Delivery order" oncontextmenu="return false">
                    <div class="card-body">
                        <h5 class="card-title">{{ __('Delivery Order') }} <img src="{{ asset('images/question_mark.svg') }}" alt="description" width="16px;" height="16px;" data-toggle="tooltip" data-placement="top" title="Update TCHUB pending orders status to processing" oncontextmenu="return false"></h5>
                        <p class="card-text">{{ __('Update order status to processing') }}</p>
                        <a href="#" class="btn btn-dark btn-block">{{ __('Update Status') }}</a>
                    </div>
                </div>
                <div class="card">
                    <img class="card-img-top" width="286" height="180" src="{{ asset('images/invoice.svg') }}" alt="Invoice" oncontextmenu="return false">
                    <div class="card-body">
                        <h5 class="card-title">{{ __('Invoice') }} <img src="{{ asset('images/question_mark.svg') }}" alt="description" width="16px;" height="16px;" data-toggle="tooltip" data-placement="top" title="Update TCHUB processing orders status to completed" oncontextmenu="return false"></h5>
                        <p class="card-text">{{ __('Update order status to completed') }}</p>
                        <a href="#" class="btn btn-dark btn-block">{{ __('Update Status') }}</a>
                    </div>
                </div>
            </div>
            
            
            <div class="card-deck mt-4">
                <div class="card" style="width: 18rem;">
                    <img class="card-img-top" width="286px" height="180px" src="{{ asset('images/cancel.svg') }}" alt="Cancel order" oncontextmenu="return false">
                    <div class="card-body">
                        <h5 class="card-title">{{ __('Cancel Order') }} <img src="{{ asset('images/question_mark.svg') }}" alt="description" width="16px;" height="16px;" data-toggle="tooltip" data-placement="top" title="Update sales order to closed in SAP B1 if orders status in TCHUB is canceled" oncontextmenu="return false"></h5>
                        <p class="card-text">{{ __('Cancel pending order') }}</p>
                        <a href="#" class="btn btn-dark btn-block">{{ __('Cancel Order') }}</a>
                    </div>
                </div>
                <div class="card" style="width: 18rem;">
                    <img class="card-img-top" width="286" height="180" src="{{ asset('images/item_master_sync.svg') }}" alt="Item sync" oncontextmenu="return false">
                    <div class="card-body">
                        <h5 class="card-title">{{ __('Item Sync') }} <img src="{{ asset('images/question_mark.svg') }}" alt="description" width="16px;" height="16px;" data-toggle="tooltip" data-placement="top" title="Sync SAP B1 item master to TCHUB" oncontextmenu="return false"></h5>
                        <p class="card-text">{{ __('Sync item from SAP to tchub.sg') }}</p>
                        <a href="#" class="btn btn-dark btn-block">{{ __('Sync Item') }}</a>
                    </div>
                </div>
                <div class="card" style="width: 18rem;">
                    <img class="card-img-top" width="286" height="180" src="{{ asset('images/stock_and_price.svg') }}" alt="Update price and stock" oncontextmenu="return false">
                    <div class="card-body">
                        <h5 class="card-title">{{ __('Update Price and Stock') }} <img src="{{ asset('images/question_mark.svg') }}" alt="description" width="16px;" height="16px;" data-toggle="tooltip" data-placement="top" title="Manually update price and stock from SAP B1 to TCHUB" oncontextmenu="return false"></h5>
                        <p class="card-text">{{ __('Update price and stock to tchub.sg') }}</p>
                        <a href="#" class="btn btn-dark btn-block">{{ __('Update Stock and Price') }}</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
  
@endsection

@push('scripts')
    <script>
        $(function () {
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });
            
            $('[data-toggle="tooltip"]').tooltip()
        })
    </script>
@endpush