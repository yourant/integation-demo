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

            <div id="alert-success" class="alert alert-success alert-dismissible fade show" style="display: none;" role="alert">
                <strong></strong>
                <span id="alert-msg"></span>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <div id="alert-danger" class="alert alert-danger alert-dismissible fade show" style="display: none;" role="alert">
                <strong></strong>
                <span id="alert-msg"></span>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <div class="card">
                <div class="card-header">
                    <div class="float-left font-weight-bold">
                       Lazada Account 1(TC) Dashboard
                   </div>
                   <div class="float-right">
                        <button class="btn btn-primary" id="refresh-token-btn">Manual Refresh Tokens</button>
                        <a href="{{ route('lazada2.dashboard') }}" class="btn btn-primary">Switch to Lazada Account 2(MSG)</a>
                   </div>
               </div>

                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-4">
                           
                        </div>
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-header">
                                    <center>Item Master Integration</center>
                                </div>
                                <div class="card-body">
                                    <p class="card-text">Create base products from SAP B1 to Lazada</p>
                                </div>
                                <div class="card-footer">
                                    <center>
                                        <button href="#" class="btn btn-primary" id="item-master-btn">
                                            CREATE BASE PRODUCTS
                                        </button>
                                    </center>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            
                        </div>
                    </div>

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
                                    <center>Update Item Price (Disabled)</center>
                                </div>
                                <div class="card-body">
                                    <p class="card-text">Update the Lazada products based on the price in the Item Master</p>
                                </div>
                                <div class="card-footer">
                                    <center>
                                        <button href="#" class="btn btn-primary" id="update-price-btn" disabled>
                                            UPDATE PRICES
                                        </button>
                                    </center>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card">
                                
                                <div class="card-header">
                                    <center>Update Item Stock (Disabled)</center>
                                </div>
                                <div class="card-body">
                                    <p class="card-text">Update the Lazada products based on the stock in the Item Master</p>
                                </div>
                                <div class="card-footer">
                                    <center>
                                        <button class="btn btn-primary" id="update-stock-btn" disabled>
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
                                    <p class="card-text">Generate A/R Invoice for every order in Lazada with "Ready to Ship" status</p>
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
                                    <p class="card-text">Generate A/R Credit Memo for every order in Lazada with "Returned" status</p>
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

            function clearAlerts(){
                $('#alert').removeClass('alert-danger');
                $('#alert').removeClass('alert-info');
                $('#alert').removeClass('alert-success');
                $('#alert').hide();
                $('#alert-success').hide();
                $('#alert-danger').hide();
            }
            
            $('#refresh-token-btn').click(function() {
                clearAlerts();

                $.ajax({
                    url: "{{ route('lazada.refresh-token') }}",
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

            $('#item-master-btn').click(function() {
                clearAlerts();

                $.ajax({
                    url: "{{ route('lazada.item-master-integration') }}",
                    method: "POST",
                    beforeSend: function() { 
                        $("#item-master-btn").attr("disabled", true);
                        $("#item-master-btn").html(`<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Creating...`);
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
                        $("#item-master-btn").attr("disabled", false);
                        $("#item-master-btn").html('CREATE BASE PRODUCTS');
                        $('html, body').animate({scrollTop: '0px'}, 300);
                    }
                })
                
                    
            });

            $('#sync-item-btn').click(function() {
                clearAlerts();
                
                $.ajax({
                    url: "{{ route('lazada.sync-item') }}",
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
                        $('html, body').animate({scrollTop: '0px'}, 300);
                    }
                })
                
                    
            });

            $('#update-price-btn').click(function() {
                clearAlerts();

                $.ajax({
                    url: "{{ route('lazada.update-price') }}",
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
                        $('html, body').animate({scrollTop: '0px'}, 300);
                    }
                })                 
                
            });

            $('#update-stock-btn').click(function() {
                clearAlerts();

                $.ajax({
                    url: "{{ route('lazada.update-stock') }}",
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
                        $('html, body').animate({scrollTop: '0px'}, 300);
                    }
                })                 
                
            });

            $('#generate-so-btn').click(function() {
                clearAlerts();

                $.ajax({
                    url: "{{ route('lazada.sales-order-generate') }}",
                    method: "POST",
                    beforeSend: function() { 
                        $("#generate-so-btn").attr("disabled", true);
                        $("#generate-so-btn").html(`<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Generating...`);
                    },
                    success: function(data) {
                        if(data.success_title != undefined){
                            $('#alert-success strong').text(data.success_title)
                            $('#alert-success #alert-msg').text(data.success_message)
                            $('#alert-success').show();
                        }if(data.danger_title != undefined){
                            $('#alert-danger strong').text(data.danger_title)
                            $('#alert-danger #alert-msg').text(data.danger_message)
                            $('#alert-danger').show();
                        }else{
                            $('#alert').addClass(data.status);
                            $('#alert strong').text(data.title);
                            $('#alert-msg').text(data.message)
                            $('#alert').show();
                        }
                    },
                    error: function(xhr, ajaxOptions, thrownError) {
                        $("#error-msg").text(xhr.responseText);
                        $('#error-alert').show();
                    },
                    complete: function(response, status) {
                        $("#generate-so-btn").attr("disabled", false);
                        $("#generate-so-btn").html('PROCESS SALES ORDERS');
                        $('html, body').animate({scrollTop: '0px'}, 300);
                    }
                })                 
                
            });

            $('#generate-inv-btn').click(function() {
                clearAlerts();

                $.ajax({
                    url: "{{ route('lazada.invoice-generate') }}",
                    method: "POST",
                    beforeSend: function() { 
                        $("#generate-inv-btn").attr("disabled", true);
                        $("#generate-inv-btn").html(`<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Generating...`);
                    },
                    success: function(data) {
                        if(data.success_title != undefined){
                            $('#alert-success strong').text(data.success_title)
                            $('#alert-success #alert-msg').text(data.success_message)
                            $('#alert-success').show();
                        }if(data.danger_title != undefined){
                            $('#alert-danger strong').text(data.danger_title)
                            $('#alert-danger #alert-msg').text(data.danger_message)
                            $('#alert-danger').show();
                        }else{
                            $('#alert').addClass(data.status);
                            $('#alert strong').text(data.title);
                            $('#alert-msg').text(data.message)
                            $('#alert').show();
                        }
                    },
                    error: function(xhr, ajaxOptions, thrownError) {
                        $("#error-msg").text(xhr.responseText);
                        $('#error-alert').show();
                    },
                    complete: function(response, status) {
                        $("#generate-inv-btn").attr("disabled", false);
                        $("#generate-inv-btn").html(`PROCESS INVOICE`);
                        $('html, body').animate({scrollTop: '0px'}, 300);
                    }
                })                 
            
            });

            $('#generate-cm-btn').click(function() {
                clearAlerts();

                $.ajax({
                    url: "{{ route('lazada.credit-memo-generate') }}",
                    method: "POST",
                    beforeSend: function() { 
                        $("#generate-cm-btn").attr("disabled", true);
                        $("#generate-cm-btn").html(`<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Generating...`);
                    },
                    success: function(data, status) {
                        if(data.success_title != undefined){
                            $('#alert-success strong').text(data.success_title)
                            $('#alert-success #alert-msg').text(data.success_message)
                            $('#alert-success').show();
                        }if(data.danger_title != undefined){
                            $('#alert-danger strong').text(data.danger_title)
                            $('#alert-danger #alert-msg').text(data.danger_message)
                            $('#alert-danger').show();
                        }else{
                            $('#alert').addClass(data.status);
                            $('#alert strong').text(data.title);
                            $('#alert-msg').text(data.message)
                            $('#alert').show();
                        }
                    },
                    error: function(xhr, ajaxOptions, thrownError) {
                        $("#error-msg").text(xhr.responseText);
                        $('#error-alert').show();
                    },
                    complete: function(response, status) {
                        $("#generate-cm-btn").attr("disabled", false);
                        $("#generate-cm-btn").html(`PROCESS CREDIT MEMO`);
                        $('html, body').animate({scrollTop: '0px'}, 300);
                    }
                })                 
                
            });

            
        });
    </script>
@endpush