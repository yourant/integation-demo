@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">

        <div aria-live="polite" aria-atomic="true" class="d-flex justify-content-center align-items-center" style="height: 200px;">
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

            <div id="success-alert" class="alert alert-success alert-dismissible fade show" role="alert">
                <strong>Success!</strong>
                <span id="success-msg"></span>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <div id="error-alert" class="alert alert-danger alert-dismissible fade show" role="alert">
                <strong>Error!</strong>
                <span id="error-msg"></span>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <div class="card">
                <div class="card-header">
                    <div class="float-left font-weight-bold">
                       Lazada Account 1 Dashboard
                   </div>
                   <div class="float-right">
                        <a href="#" class="btn btn-primary">Switch to Lazada Account 2</a>
                   </div>
               </div>

                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-header">
                                    <center>Synchronize Item</center>
                                </div>
                                <div class="card-body">
                                    <p class="card-text">Synchronize the item master to Lazada products</p>    
                                </div>
                                <div class="card-footer">
                                    <center>
                                        <button class="btn btn-primary" id="sync-item-btn">
                                            PROCESS ITEMS
                                        </button>
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
                                    <p class="card-text">Update the Lazada products based on the price in the Item Master</p>
                                </div>
                                <div class="card-footer">
                                    <center>
                                        <button href="#" class="btn btn-primary" id="update-price-btn">
                                            UPDATE PRICES
                                        </button>
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
                                    <p class="card-text">Update the Lazada products based on the stock in the Item Master</p>
                                </div>
                                <div class="card-footer">
                                    <center>
                                        <button class="btn btn-primary" id="update-stock-btn">
                                            UPDATE STOCKS
                                        </button>
                                    </center>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row mt-4">
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-header">
                                    <center>Generate Sales Orders</center>
                                </div>
                                <div class="card-body">
                                    <p class="card-text">Generate Sales Order for every order in Lazada with "Pending" status</p>
                                </div>
                                <div class="card-footer">
                                    <center>
                                        <a href="#" class="btn btn-primary" id="generate-so-btn">
                                            PROCESS SALES ORDERS
                                        </a>
                                    </center>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-header">
                                    <center>Generate A/R Invoice</center>
                                </div>
                                <div class="card-body">
                                    <p class="card-text">Generate A/R Invoice for every order in Lazada with "Ready to Ship" status</p>
                                </div>
                                <div class="card-footer">
                                    <center>
                                        <a href="#" class="btn btn-primary" id="generate-inv-btn">
                                            PROCESS INVOICE
                                        </a>
                                    </center>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card">                         
                                <div class="card-header">
                                    <center>Generate Credit Memo</center>
                                </div>
                                <div class="card-body">
                                    <p class="card-text">Generate A/R Invoice for every order in Lazada with "Returned" status</p>
                                </div>
                                <div class="card-footer">
                                    <center>
                                        <a href="#" class="btn btn-primary" id="generate-cm-btn">
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

            var isLoading = false; 

            $('#success-alert').hide();
            $('#error-alert').hide();

            $('.toast').toast({
                autohide: false
            });

            $('#sync-item-btn').click(function() {
                $('#success-alert').hide();
                $('#error-alert').hide();
                
                $.ajax({
                    url: "{{ route('lazada.sync-item') }}",
                    method: "POST",
                    beforeSend: function() { 
                        $("#sync-item-btn").attr("disabled", true);
                        $("#sync-item-btn").html(`<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing...`);
                    },
                    success: function(data, status) {
                        $("#success-msg").text('Item Id UDFs updated.');
                        $('#success-alert').show();
                    },
                    error: function(xhr, ajaxOptions, thrownError) {
                        $("#error-msg").text(xhr.responseText);
                        $('#error-alert').show();
                    },
                    complete: function(response, status) {
                        $("#sync-item-btn").attr("disabled", false);
                        $("#sync-item-btn").html('PROCESS ITEMS');
                    }
                })
                
                    
            });

            $('#update-price-btn').click(function() {
                $('#success-alert').hide();
                $('#error-alert').hide();

                $.ajax({
                    url: "{{ route('lazada.update-price') }}",
                    method: "POST",
                    beforeSend: function() { 
                        $("#update-price-btn").attr("disabled", true);
                        $("#update-price-btn").html(`<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing...`);
                    },
                    success: function(data, status) {
                        $("#success-msg").text('Item Price Updated');
                        $('#success-alert').show();
                    },
                    error: function(xhr, ajaxOptions, thrownError) {
                        $("#error-msg").text(xhr.responseText);
                        $('#error-alert').show();
                    },
                    complete: function(response, status) {
                        $("#update-price-btn").attr("disabled", false);
                        $("#update-price-btn").html('UPDATE PRICES');
                    }
                })                 
                
            });

            $('#update-stock-btn').click(function() {
                $('#success-alert').hide();
                $('#error-alert').hide();

                $.ajax({
                    url: "{{ route('lazada.update-stock') }}",
                    method: "POST",
                    beforeSend: function() { 
                        $("#update-stock-btn").attr("disabled", true);
                        $("#update-stock-btn").html(`<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing...`);
                    },
                    success: function(data, status) {
                        $("#success-msg").text('Item Stock Updated');
                        $('#success-alert').show();
                    },
                    error: function(xhr, ajaxOptions, thrownError) {
                        $("#error-msg").text(xhr.responseText);
                        $('#error-alert').show();
                    },
                    complete: function(response, status) {
                        $("#update-stock-btn").attr("disabled", false);
                        $("#update-stock-btn").html('UPDATE STOCKS');
                    }
                })                 
                
            });

            $('#generate-so-btn').click(function() {
                if (!isLoading) {
                    $('#success-alert').hide();
                    $('#error-alert').hide();

                    $('.toast').toast('show');
                    $('#toast-title').text('GENERATE SALES ORDERS');
                    $('#toast-msg').text('Generating . . .');

                    isLoading = true;

                    $.ajax({
                        url: "{{ route('lazada.sales-order-generate') }}",
                        method: "POST",
                        success: function(data, status) {
                            $("#success-msg").text('Sales Orders Generated');
                            $('#success-alert').show();
                        },
                        error: function(xhr, ajaxOptions, thrownError) {
                            $("#error-msg").text(xhr.responseText);
                            $('#error-alert').show();
                        },
                        complete: function(response, status) {
                            $('.toast').toast('hide');
                            isLoading = false;
                        }
                    })                 
                }
            });

            $('#generate-inv-btn').click(function() {
                if (!isLoading) {
                    $('#success-alert').hide();
                    $('#error-alert').hide();

                    $('.toast').toast('show');
                    $('#toast-title').text('GENERATE A/R INVOICES');
                    $('#toast-msg').text('Generating . . .');

                    isLoading = true;

                    $.ajax({
                        url: "{{ route('lazada.invoice-generate') }}",
                        method: "POST",
                        success: function(data, status) {
                            $("#success-msg").text('A/R Invoices Generated');
                            $('#success-alert').show();
                        },
                        error: function(xhr, ajaxOptions, thrownError) {
                            $("#error-msg").text(xhr.responseText);
                            $('#error-alert').show();
                        },
                        complete: function(response, status) {
                            $('.toast').toast('hide');
                            isLoading = false;
                        }
                    })                 
                }
            });

            $('#generate-cm-btn').click(function() {
                if (!isLoading) {
                    $('#success-alert').hide();
                    $('#error-alert').hide();

                    $('.toast').toast('show');
                    $('#toast-title').text('GENERATE CREDIT MEMO');
                    $('#toast-msg').text('Generating . . .');

                    isLoading = true;

                    $.ajax({
                        url: "{{ route('lazada.credit-memo-generate') }}",
                        method: "POST",
                        success: function(data, status) {
                            $("#success-msg").text('A/R Credit Memos Generated');
                            $('#success-alert').show();
                        },
                        error: function(xhr, ajaxOptions, thrownError) {
                            $("#error-msg").text(xhr.responseText);
                            $('#error-alert').show();
                        },
                        complete: function(response, status) {
                            $('.toast').toast('hide');
                            isLoading = false;
                        }
                    })                 
                }
            });

            
        });
    </script>
@endpush