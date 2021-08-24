@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">

        <div class="col-md-12">
            <div id="alert" class="alert alert-dismissible fade show" style="display: none;" role="alert">
                <strong></strong>
                <span id="alert-msg"></span>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

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
                        Lazada Account 2 Dashboard
                    </div>
                    <div class="float-right">
                        <button class="btn btn-primary" id="refresh-token-btn">Manual Refresh Tokens</button>
                        <a href="{{ route('lazada.dashboard') }}" class="btn btn-primary">Switch to Lazada Account 1</a>
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
                                    <p class="card-text">Update the Lazada products based on the price in the Item
                                        Master</p>
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
                                    <p class="card-text">Update the Lazada products based on the stock in the Item
                                        Master</p>
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
                                    <p class="card-text">Generate Sales Order for every order in Lazada with "Pending"
                                        status</p>
                                </div>
                                <div class="card-footer">
                                    <center>
                                        <button class="btn btn-primary" id="generate-so-btn">
                                            PROCESS SALES ORDERS
                                        </button>
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
                                    <p class="card-text">Generate A/R Invoice for every order in Lazada with "Ready to
                                        Ship" status</p>
                                </div>
                                <div class="card-footer">
                                    <center>
                                        <button class="btn btn-primary" id="generate-inv-btn">
                                            PROCESS INVOICE
                                        </button>
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
                                    <p class="card-text">Generate A/R Invoice for every order in Lazada with "Returned"
                                        status</p>
                                </div>
                                <div class="card-footer">
                                    <center>
                                        <button class="btn btn-primary" id="generate-cm-btn">
                                            PROCESS CREDIT MEMO
                                        </button>
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

            $('#success-alert').hide();
            $('#error-alert').hide();

            $('#refresh-token-btn').click(function() {
                $('#alert').hide();

                $.ajax({
                    url: "{{ route('lazada2.refresh-token') }}",
                    method: "POST",
                    beforeSend: function() { 
                        $("#refresh-token-btn").attr("disabled", true);
                        $("#refresh-token-btn").html(`<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Refreshing...`);
                    },
                    success: function(data) {
                        $('#alert').addClass(data.status);
                        $('#alert strong').text(data.title);
                        $('#alert-msg').text(data.message)
                        $('#alert').show();
                    },
                    error: function(xhr, ajaxOptions, thrownError) {
                        $("#error-msg").text(xhr.responseText);
                        $('#error-alert').show();
                    },
                    complete: function(response, status) {
                        $("#refresh-token-btn").attr("disabled", false);
                        $("#refresh-token-btn").html('Manual Refresh Tokens');
                    }
                })
                    
            });

            $('#sync-item-btn').click(function() {
                $('#alert').hide();
                
                $.ajax({
                    url: "{{ route('lazada2.sync-item') }}",
                    method: "POST",
                    beforeSend: function() { 
                        $("#sync-item-btn").attr("disabled", true);
                        $("#sync-item-btn").html(`<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing...`);
                    },
                    success: function(data) {
                        $('#alert').addClass(data.status);
                        $('#alert strong').text(data.title);
                        $('#alert-msg').text(data.message)
                        $('#alert').show();
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
                $('#alert').hide();

                $.ajax({
                    url: "{{ route('lazada2.update-price') }}",
                    method: "POST",
                    beforeSend: function() { 
                        $("#update-price-btn").attr("disabled", true);
                        $("#update-price-btn").html(`<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Updating...`);
                    },
                    success: function(data) {
                        $('#alert').addClass(data.status);
                        $('#alert strong').text(data.title);
                        $('#alert-msg').text(data.message)
                        $('#alert').show();
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
                $('#alert').hide();

                $.ajax({
                    url: "{{ route('lazada2.update-stock') }}",
                    method: "POST",
                    beforeSend: function() { 
                        $("#update-stock-btn").attr("disabled", true);
                        $("#update-stock-btn").html(`<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Updating...`);
                    },
                    success: function(data) {
                        $('#alert').addClass(data.status);
                        $('#alert strong').text(data.title);
                        $('#alert-msg').text(data.message)
                        $('#alert').show();
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
                $('#success-alert').hide();
                $('#error-alert').hide();

                $.ajax({
                    url: "{{ route('lazada2.sales-order-generate') }}",
                    method: "POST",
                    beforeSend: function() { 
                        $("#generate-so-btn").attr("disabled", true);
                        $("#generate-so-btn").html(`<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Generating...`);
                    },
                    success: function(data, status) {
                        $("#success-msg").text('Sales Orders Generated');
                        $('#success-alert').show();
                    },
                    error: function(xhr, ajaxOptions, thrownError) {
                        $("#error-msg").text(xhr.responseText);
                        $('#error-alert').show();
                    },
                    complete: function(response, status) {
                        $("#generate-so-btn").attr("disabled", false);
                        $("#generate-so-btn").html('PROCESS SALES ORDERS');
                    }
                })                 
                
            });

            $('#generate-inv-btn').click(function() {
                $('#success-alert').hide();
                $('#error-alert').hide();

                $.ajax({
                    url: "{{ route('lazada2.invoice-generate') }}",
                    method: "POST",
                    beforeSend: function() { 
                        $("#generate-inv-btn").attr("disabled", true);
                        $("#generate-inv-btn").html(`<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Generating...`);
                    },
                    success: function(data, status) {
                        $("#success-msg").text('A/R Invoices Generated');
                        $('#success-alert').show();
                    },
                    error: function(xhr, ajaxOptions, thrownError) {
                        $("#error-msg").text(xhr.responseText);
                        $('#error-alert').show();
                    },
                    complete: function(response, status) {
                        $("#generate-inv-btn").attr("disabled", false);
                        $("#generate-inv-btn").html(`PROCESS INVOICE`);
                    }
                })                 
            
            });

            $('#generate-cm-btn').click(function() {
                $('#success-alert').hide();
                $('#error-alert').hide();

                $.ajax({
                    url: "{{ route('lazada2.credit-memo-generate') }}",
                    method: "POST",
                    beforeSend: function() { 
                        $("#generate-cm-btn").attr("disabled", true);
                        $("#generate-cm-btn").html(`<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Generating...`);
                    },
                    success: function(data, status) {
                        $("#success-msg").text('A/R Credit Memos Generated');
                        $('#success-alert').show();
                    },
                    error: function(xhr, ajaxOptions, thrownError) {
                        $("#error-msg").text(xhr.responseText);
                        $('#error-alert').show();
                    },
                    complete: function(response, status) {
                        $("#generate-cm-btn").attr("disabled", false);
                        $("#generate-cm-btn").html(`PROCESS CREDIT MEMO`);
                    }
                })                 
                
            });

        });
    </script>
@endpush
