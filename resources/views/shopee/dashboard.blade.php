@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">

        <div aria-live="polite" aria-atomic="true" class="d-flex justify-content-center align-items-center" style="height: 200px; z-index: 10;">
            <div class="toast" role="alert" aria-live="assertive" aria-atomic="true" style="position: absolute; bottom: 2%; right: 1%;">
                <div class="toast-header">
                    <strong class="mr-auto" id="toast-title"></strong>
                    <button type="button" class="ml-2 mb-1 close" data-dismiss="toast" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="toast-body" id="toast-msg"></div>
            </div>
        </div>

        <div class="col-md-12">
            @if (session('status'))
                <div id="authAlert" class="alert alert-dismissible fade show @if(session('status') == 'success') alert-success @else alert-danger @endif" role="alert">
                    <span id="alert-msg">{{ session('msg') }}</span>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            @endif
            
            <div id="alert" class="alert alert-dismissible fade show" role="alert">
                <strong></strong>
                <span id="alert-msg"></span>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <div class="card">
                <div class="card-header font-weight-bold">Shopee Dashboard</div>

                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-header">
                                    <center>Create Shopee Product</center>
                                </div>
                                <div class="card-body">
                                    <p class="card-text">Create non existent Shopee products based on item master</p>    
                                </div>
                                <div class="card-footer">
                                    <center>
                                        <a href="#" class="btn btn-primary req-btn" id="create-prod-btn">
                                            GENERATE PRODUCT
                                        </a>
                                    </center>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-header">
                                    <center>Synchronize Item</center>
                                </div>
                                <div class="card-body">
                                    <p class="card-text">Synchronize the item master to shopee products</p>    
                                </div>
                                <div class="card-footer">
                                    <center>
                                        <a href="#" class="btn btn-primary req-btn" id="sync-item-btn">
                                            PROCESS ITEMS
                                        </a>
                                    </center>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-header">
                                    <center>Update Item Price</center>
                                </div>
                                <div class="card-body">
                                    <p class="card-text">Update the shopee products based on the price in the Item Master</p>
                                </div>
                                <div class="card-footer">
                                    <center>
                                        <a href="#" class="btn btn-primary req-btn" id="update-price-btn">
                                            UPDATE PRICES
                                        </a>
                                    </center>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row mt-4">
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-header">
                                    <center>Generate A/R Invoice</center>
                                </div>
                                <div class="card-body">
                                    <p class="card-text">Generate A/R Invoice for every order in Shopee with "Shipped" status</p>
                                </div>
                                <div class="card-footer">
                                    <center>
                                        <a href="#" class="btn btn-primary req-btn" id="generate-inv-btn">
                                            PROCESS INVOICE
                                        </a>
                                    </center>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-header">
                                    <center>Generate Sales Orders</center>
                                </div>
                                <div class="card-body">
                                    <p class="card-text">Generate Sales Order for every order in Shopee with "To Process" status</p>
                                </div>
                                <div class="card-footer">
                                    <center>
                                        <a href="#" class="btn btn-primary req-btn" id="generate-so-btn">
                                            PROCESS SALES ORDERS
                                        </a>
                                    </center>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card">
                                
                                <div class="card-header">
                                    <center>Update Item Stock</center>
                                </div>
                                <div class="card-body">
                                    <p class="card-text">Update the shopee products based on the stock in the Item Master</p>
                                </div>
                                <div class="card-footer">
                                    <center>
                                        <a href="#" class="btn btn-primary req-btn" id="update-stock-btn">
                                            UPDATE STOCKS
                                        </a>
                                    </center>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row mt-4">
                        <div class="col-md-4">
                            <div class="card">                         
                                <div class="card-header">
                                    <center>Generate Credit Memo</center>
                                </div>
                                <div class="card-body">
                                    <p class="card-text">Generate Credit Memo for every return in Shopee with "Refund Completed" status</p>
                                </div>
                                <div class="card-footer">
                                    <center>
                                        <a href="#" class="btn btn-primary req-btn" id="generate-cm-btn">
                                            PROCESS CREDIT MEMO
                                        </a>
                                    </center>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
    <script type="text/javascript">
    
        $(document).ready(function() {

            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });

            $('.toast').toast({
                autohide: false
            });

            $('.alert').on('close.bs.alert', function (event) {
                event.preventDefault();
                $(this).hide();
            });

            $('#alert').hide();
            var isLoading = false; 
            var processMsg = 'Processing . . .';
            var alertStatus = '';
            var errorTitle = 'ERROR: ';
            var errorMsg = 'There is a problem with the server.';

            
            // $.ajax({
            //         url: "",
            //         method: "POST",
            //         beforeSend: function() { 
            //             $("#refresh-token-btn").attr("disabled", true);
            //             $("#refresh-token-btn").html(`<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Refreshing...`);
            //         },
            //         success: function(data) {
            //             $('#alert').addClass(data.status);
            //             $('#alert strong').text(data.title);
            //             $('#alert-msg').text(data.message)
            //             $('#alert').show();
            //         },
            //         error: function(xhr, ajaxOptions, thrownError) {
            //             $("#error-msg").text(xhr.responseText);
            //             $('#error-alert').show();
            //         },
            //         complete: function(response, status) {
            //             $("#refresh-token-btn").attr("disabled", false);
            //             $("#refresh-token-btn").html('Manual Refresh Tokens');
            //         }
            //     })


            $('#create-prod-btn').click(function() {
                if (!isLoading) {
                    $('#alert').hide();
                    $('#alert').removeClass(alertStatus);
                    $('.req-btn').addClass('disabled');

                    $('.toast').toast('show');
                    $('#toast-title').text('CREATE SHOPEE PRODUCT');
                    $('#toast-msg').text(processMsg);

                    isLoading = true;

                    $.ajax({
                        url: "{{ route('shopee.create-product') }}",
                        method: "POST",
                        success: function(data, status) {
                            alertStatus = data.alertType;

                            $('#alert').addClass(alertStatus);
                            $('#alert strong').text(data.level);
                            $('#alert-msg').text(data.message);
                        },
                        error: function(response, status) {
                            alertStatus = 'alert-danger';

                            $('#alert').addClass(alertStatus);
                            $('#alert strong').text(errorTitle);
                            $('#alert-msg').text(errorMsg);
                        },
                        complete: function(response, status) {
                            $('#alert').show();
                            $('.toast').toast('hide');
                            $('.req-btn').removeClass('disabled');
                            isLoading = false;
                        }
                    })                 
                }
            });

            $('#sync-item-btn').click(function() {
                if (!isLoading) {
                    $('#alert').hide();
                    $('#alert').removeClass(alertStatus);
                    $('.req-btn').addClass('disabled');

                    $('.toast').toast('show');
                    $('#toast-title').text('SYNCHRONIZE ITEMS');
                    $('#toast-msg').text(processMsg);

                    isLoading = true;

                    $.ajax({
                        url: "{{ route('shopee.sync-item') }}",
                        method: "POST",
                        success: function(data, status) {
                            alertStatus = data.alertType;

                            $('#alert').addClass(alertStatus);
                            $('#alert strong').text(data.level);
                            $('#alert-msg').text(data.message);
                        },
                        error: function(response, status) {
                            alertStatus = 'alert-danger';

                            $('#alert').addClass(alertStatus);
                            $('#alert strong').text(errorTitle);
                            $('#alert-msg').text(errorMsg);
                        },
                        complete: function(response, status) {
                            $('#alert').show();
                            $('.toast').toast('hide');
                            $('.req-btn').removeClass('disabled');
                            isLoading = false;
                        }
                    })                 
                }
            });

            $('#update-price-btn').click(function() {
                if (!isLoading) {
                    $('#alert').hide();
                    $('#alert').removeClass(alertStatus);
                    $('.req-btn').addClass('disabled');

                    $('.toast').toast('show');
                    $('#toast-title').text('UPDATE ITEMS PRICE');
                    $('#toast-msg').text(processMsg);

                    isLoading = true;

                    $.ajax({
                        url: "{{ route('shopee.update-price') }}",
                        method: "POST",
                        success: function(data, status) {
                            alertStatus = data.alertType;

                            $('#alert').addClass(alertStatus);
                            $('#alert strong').text(data.level);
                            $('#alert-msg').text(data.message);
                        },
                        error: function(response, status) {
                            alertStatus = 'alert-danger';

                            $('#alert').addClass(alertStatus);
                            $('#alert strong').text(errorTitle);
                            $('#alert-msg').text(errorMsg);
                        },
                        complete: function(response, status) {
                            $('#alert').show();
                            $('.toast').toast('hide');
                            $('.req-btn').removeClass('disabled');
                            isLoading = false;
                        }
                    })                 
                }
            });

            $('#update-stock-btn').click(function() {
                if (!isLoading) {
                    $('#alert').hide();
                    $('#alert').removeClass(alertStatus);
                    $('.req-btn').addClass('disabled');

                    $('.toast').toast('show');
                    $('#toast-title').text('UPDATE ITEMS STOCK');
                    $('#toast-msg').text(processMsg);

                    isLoading = true;

                    $.ajax({
                        url: "{{ route('shopee.update-stock') }}",
                        method: "POST",
                        success: function(data, status) {
                            alertStatus = data.alertType;

                            $('#alert').addClass(alertStatus);
                            $('#alert strong').text(data.level);
                            $('#alert-msg').text(data.message);
                        },
                        error: function(response, status) {
                            alertStatus = 'alert-danger';

                            $('#alert').addClass(alertStatus);
                            $('#alert strong').text(errorTitle);
                            $('#alert-msg').text(errorMsg);
                        },
                        complete: function(response, status) {
                            $('#alert').show();
                            $('.toast').toast('hide');
                            $('.req-btn').removeClass('disabled');
                            isLoading = false;
                        }
                    })                 
                }
            });     

            $('#generate-so-btn').click(function() {
                if (!isLoading) {
                    $('#alert').hide();
                    $('#alert').removeClass(alertStatus);
                    $('.req-btn').addClass('disabled');

                    $('.toast').toast('show');
                    $('#toast-title').text('GENERATE SALES ORDERS');
                    $('#toast-msg').text(processMsg);

                    isLoading = true;

                    $.ajax({
                        url: "{{ route('shopee.salesorder-generate') }}",
                        method: "POST",
                        success: function(data, status) {
                            alertStatus = data.alertType;

                            $('#alert').addClass(alertStatus);
                            $('#alert strong').text(data.level);
                            $('#alert-msg').text(data.message);
                        },
                        error: function(response, status) {
                            alertStatus = 'alert-danger';

                            $('#alert').addClass(alertStatus);
                            $('#alert strong').text(errorTitle);
                            $('#alert-msg').text(errorMsg);
                        },
                        complete: function(response, status) {
                            $('#alert').show();
                            $('.toast').toast('hide');
                            $('.req-btn').removeClass('disabled');
                            isLoading = false;
                        }
                    })               
                }
            });

            $('#generate-inv-btn').click(function() {
                if (!isLoading) {
                    $('#alert').hide();
                    $('#alert').removeClass(alertStatus);
                    $('.req-btn').addClass('disabled');

                    $('.toast').toast('show');
                    $('#toast-title').text('GENERATE A/R INVOICES');
                    $('#toast-msg').text(processMsg);

                    isLoading = true;

                    $.ajax({
                        url: "{{ route('shopee.invoice-generate') }}",
                        method: "POST",
                        success: function(data, status) {
                            alertStatus = data.alertType;

                            $('#alert').addClass(alertStatus);
                            $('#alert strong').text(data.level);
                            $('#alert-msg').text(data.message);
                        },
                        error: function(response, status) {
                            alertStatus = 'alert-danger';

                            $('#alert').addClass(alertStatus);
                            $('#alert strong').text(errorTitle);
                            $('#alert-msg').text(errorMsg);
                        },
                        complete: function(response, status) {
                            $('#alert').show();
                            $('.toast').toast('hide');
                            $('.req-btn').removeClass('disabled');
                            isLoading = false;
                        }
                    })                
                }
            });

            $('#generate-cm-btn').click(function() {
                if (!isLoading) {
                    $('#alert').hide();
                    $('#alert').removeClass(alertStatus);
                    $('.req-btn').addClass('disabled');

                    $('.toast').toast('show');
                    $('#toast-title').text('GENERATE CREDIT MEMO');
                    $('#toast-msg').text(processMsg);

                    isLoading = true;

                    $.ajax({
                        url: "{{ route('shopee.creditmemo-generate') }}",
                        method: "POST",
                        success: function(data, status) {
                            alertStatus = data.alertType;

                            $('#alert').addClass(alertStatus);
                            $('#alert strong').text(data.level);
                            $('#alert-msg').text(data.message);
                        },
                        error: function(response, status) {
                            alertStatus = 'alert-danger';

                            $('#alert').addClass(alertStatus);
                            $('#alert strong').text(errorTitle);
                            $('#alert-msg').text(errorMsg);
                        },
                        complete: function(response, status) {
                            $('#alert').show();
                            $('.toast').toast('hide');
                            $('.req-btn').removeClass('disabled');
                            isLoading = false;
                        }
                    })               
                }
            });
            
        });

    </script>
@endpush

