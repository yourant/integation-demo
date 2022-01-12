@extends('layouts.tchub_app')

@section('content')
<div class="row">
    <div aria-live="polite" aria-atomic="true" style="position: relative; min-height: 200px;">
        <div class="toast" style="position: fixed; top: 10; right: 0; z-index:500;">
          <div class="toast-header bg-success text-white">
            <img src="{{ asset('images/logo.png') }}" width="50px" height="40px;" class="rounded mr-2" alt="logo">
            <strong class="mr-auto">
                <div class="spinner-border text-dark spinner-border-sm" role="status">
                    <span class="sr-only">Loading...</span>
                </div>
                {{ __('Loading...') }}
            </strong>
          </div>
          <div class="toast-body">
             {{ __('Please wait while processing your request.') }}
          </div>
        </div>
    </div>

    <div class="col-md-12">
        @include('layouts.flash')

        <div class="card border-dark">
            <div class="card-header bg-dark text-white text-center">
                <strong>{{ __('ITEM MASTER PROCESS') }}</strong>
            </div>
            <div class="card-body">
                <div class="card-group">
                    <div class="card">
                        <img class="card-img-top" width="276" height="180" src="{{ asset('images/item_master_sync.svg') }}" alt="Update Item Status" oncontextmenu="return false">
                        <div class="card-body">
                            <h5 class="card-title">{{ __('Update Item Status') }} <img src="{{ asset('images/question_mark.svg') }}" alt="description" width="16px;" height="16px;" data-toggle="tooltip" data-placement="top" title="Update item status from SAP B1 to tchub.sg (active/inactive)" oncontextmenu="return false"></h5>
                            <p class="card-text">{{ __('Update item status active/inactive') }}</p>
                        </div>
                        <div class="card-footer">
                            <a href="{{ route('tchub.update.item.status') }}" onclick="this.disabled" class="btn btn-dark btn-block btn-lg" id="item-sync-btn">{{ __('Update Item Status') }}</a>
                        </div>
                    </div>
                    <div class="card">
                        <img class="card-img-top" width="276" height="180" src="{{ asset('images/price.svg') }}" alt="Update price and stock" oncontextmenu="return false">
                    <div class="card-body">
                        <h5 class="card-title">{{ __('Update Price') }} <img src="{{ asset('images/question_mark.svg') }}" alt="description" width="16px;" height="16px;" data-toggle="tooltip" data-placement="top" title="Manually update price from SAP B1 to tchub.sg" oncontextmenu="return false"></h5>
                        <p class="card-text">{{ __('Update price to tchub.sg') }}</p>
                    </div>
                        <div class="card-footer">
                            <a href="{{ route('tchub.update.prices') }}" class="btn btn-dark btn-block btn-lg">{{ __('Update Price') }}</a>
                        </div>
                    </div>
                    <div class="card">
                        <img class="card-img-top" width="276" height="180" src="{{ asset('images/stock.svg') }}" alt="Update price and stock" oncontextmenu="return false">
                    <div class="card-body">
                        <h5 class="card-title">{{ __('Update Stock') }} <img src="{{ asset('images/question_mark.svg') }}" alt="description" width="16px;" height="16px;" data-toggle="tooltip" data-placement="top" title="Manually update stock from SAP B1 to TCHUB" oncontextmenu="return false"></h5>
                        <p class="card-text">{{ __('Update stock to tchub.sg') }}</p>
                    </div>
                        <div class="card-footer">
                            <a href="{{ route('tchub.update.stocks') }}" class="btn btn-dark btn-block btn-lg" id="update-stock-btn">{{ __('Update Stock') }}</a>
                        </div>
                    </div>
                    <div class="card">
                        <img class="card-img-top" width="276" height="180" src="{{ asset('images/create_product.svg') }}" alt="Create Product" oncontextmenu="return false">
                        <div class="card-body">
                            <h5 class="card-title">{{ __('Create Product') }} <img src="{{ asset('images/question_mark.svg') }}" alt="description" width="16px;" height="16px;" data-toggle="tooltip" data-placement="top" title="Create product item from SAP B1 to tchub.sg" oncontextmenu="return false"></h5>
                            <p class="card-text">{{ __('Create product from SAP to tchub.sg') }}</p>
                        </div>
                        <div class="card-footer">
                            <a href="{{ route('tchub.create.product') }}" class="btn btn-dark btn-block btn-lg" id="item-sync-btn">{{ __('Create Product') }}</a>
                        </div>
                    </div>
                </div>  
            </div>
        </div>
    </div>

    <div class="col-md-12 mt-4">
        <div class="card border-dark">
            <div class="card-header bg-dark text-white text-center">
                <strong>{{ __('SALES PROCESS') }}</strong>
            </div>
            <div class="card-body">
                <div class="card-group">
                    <div class="card">
                        <img class="card-img-top" width="276px" height="180px" src="{{ asset('images/sales_order.svg') }}" alt="Sales order" oncontextmenu="return false">
                        <div class="card-body">
                            <h5 class="card-title">{{ __('Sales Order') }} <img src="{{ asset('images/question_mark.svg') }}" alt="description" width="16px;" height="16px;" data-toggle="tooltip" data-placement="top" title="Generate sales order from TCHUB to SAP B1" oncontextmenu="return false"></h5>
                            <p class="card-text">{{ __('Generate sales order from tchub.sg') }}</p>
                            <p class="text-center p-0 m-0"><a href="{{ route('tchub.pending.orders.index') }}" class="btn btn-link">View all</a>
                        </div>
                        <div class="card-footer">
                            <a href="{{ route('tchub.generate.sales.order') }}" class="btn btn-dark btn-block btn-lg" id="generate-sales-order-btn">{{ __('Generate Sales Order') }}</a>
                        </div>
                    </div>
                    <div class="card">
                        <img class="card-img-top" width="276" height="180" src="{{ asset('images/delivery_order.svg') }}" alt="Delivery order" oncontextmenu="return false">
                        <div class="card-body">
                            <h5 class="card-title">{{ __('Delivery Order') }} <img src="{{ asset('images/question_mark.svg') }}" alt="description" width="16px;" height="16px;" data-toggle="tooltip" data-placement="top" title="Update TCHUB pending orders status to processing" oncontextmenu="return false"></h5>
                            <p class="card-text">{{ __('Update order status to processing') }}</p>
                        </div>
                        <div class="card-footer">
                            <a href="{{ route('tchub.delivery.order') }}" class="btn btn-dark btn-block btn-lg">{{ __('Update Status') }}</a>
                        </div>
                    </div>
                    <div class="card">
                        <img class="card-img-top" width="276" height="180" src="{{ asset('images/invoice.svg') }}" alt="Invoice" oncontextmenu="return false">
                        <div class="card-body">
                            <h5 class="card-title">{{ __('Invoice') }} <img src="{{ asset('images/question_mark.svg') }}" alt="description" width="16px;" height="16px;" data-toggle="tooltip" data-placement="top" title="Update TCHUB processing orders status to completed" oncontextmenu="return false"></h5>
                            <p class="card-text">{{ __('Update order status to completed') }}</p>
                        </div>
                        <div class="card-footer">
                            <a href="{{ route('tchub.ar.invoice') }}" class="btn btn-dark btn-block btn-lg">{{ __('Update Status') }}</a>
                        </div>
                    </div>
                    <div class="card">
                        <img class="card-img-top" width="276px" height="180px" src="{{ asset('images/cancel.svg') }}" alt="Cancel order" oncontextmenu="return false">
                        <div class="card-body">
                            <h5 class="card-title">{{ __('Cancel Order') }} <img src="{{ asset('images/question_mark.svg') }}" alt="description" width="16px;" height="16px;" data-toggle="tooltip" data-placement="top" title="Update sales order to closed in SAP B1 if orders status in TCHUB is canceled" oncontextmenu="return false"></h5>
                            <p class="card-text">{{ __('Cancel pending order') }}</p>
                        </div>
                        <div class="card-footer">
                            <a href="{{ route('tchub.canceled.order') }}" class="btn btn-dark btn-block btn-lg">{{ __('Cancel Order') }}</a>

                        </div>
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

            $('.toast').toast({
                autohide: false
            })

            $('.btn').on('click', () => {
                $('.toast').toast('show')
                $('.btn').addClass('disabled')
            })
            
            $('[data-toggle="tooltip"]').tooltip()

            //item sync
            // $('#item-sync-btn').on('click', () => {
            //     $.ajax({
            //         url: "{{ route('tchub.update.item.status') }}",
            //         type: 'POST',
            //         success: (data) => {
            //             console.log(data.message)
            //         },
            //         error: (error) => {
            //             console.log(error.responseText)
            //         },
            //     })
            // })

            //update stock
            // $('#update-stock-btn').on('click', () => {
            //     $.ajax({
            //         url: "#",
            //         type: 'POST',
            //         success: (data) => {
            //             console.log(data.message)
            //         },
            //         error: (error) => {
            //             console.log(error.responseText)
            //         },
            //     })
            // })

            //sales order   
            // $('#generate-sales-order-btn').on('click', () => {
            //     $.ajax({
            //         url: "#",
            //         type: 'POST',
            //         success: (data) => {
            //             console.log(data.message)
            //         },
            //         error: (error) => {
            //             console.log(error.responseText)
            //         },
            //     })
            // })
        })
    </script>
@endpush