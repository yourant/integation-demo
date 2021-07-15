@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">

        <div aria-live="polite" aria-atomic="true" class="d-flex justify-content-center align-items-center" style="height: 200px;">
            <div class="toast" role="alert" aria-live="assertive" aria-atomic="true" style="position: absolute; bottom: 2%; right: 1%;">
                <div class="toast-header">
                    <strong class="mr-auto">Placeholder Title</strong>
                    <button type="button" class="ml-2 mb-1 close" data-dismiss="toast" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="toast-body">
                    Placeholder message.
                </div>
            </div>
        </div>

        <div class="col-md-12">           
            <div class="card">
                <div class="card-header font-weight-bold">Shopee Dashboard</div>

                <div class="card-body">
                    <div class="row">
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
                                        <a href="#" class="btn btn-primary" id="syncItemBtn">
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
                                        <a href="#" class="btn btn-primary">
                                            UPDATE PRICES
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
                                        <a href="#" class="btn btn-primary">
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
                                    <center>Generate Sales Orders</center>
                                </div>
                                <div class="card-body">
                                    <p class="card-text">Generate Sales Order for every order in Shopee with "To Ship" status</p>
                                </div>
                                <div class="card-footer">
                                    <center>
                                        <a href="#" class="btn btn-primary">
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
                                    <p class="card-text">Generate A/R Invoice for every order in Shopee with "To Receive" status</p>
                                </div>
                                <div class="card-footer">
                                    <center>
                                        <a href="#" class="btn btn-primary">
                                            PROCESS INVOICE
                                        </a>
                                    </center>
                                </div>
                            </div>
                        </div>
                        {{-- <div class="col-md-4">
                            <div class="card">
                                
                                <div class="card-header">
                                    <center>Update Item Stock</center>
                                </div>
                                <div class="card-body">
                                    <p class="card-text">Update the shopee products based on the stock in the Item Master</p>
                                </div>
                                <div class="card-footer">
                                    <center>
                                        <a href="#" class="btn btn-primary">
                                            UPDATE STOCKS
                                        </a>
                                    </center>
                                </div>
                            </div>
                        </div> --}}
                    </div>

                    
                    {{-- <form method="POST" action="{{ route('test.index') }}">
                        @csrf
                        <input type="submit" value="Get Data">
                    </form> --}}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
    <script type="text/javascript">
    
        $(document).ready(function() {
            // console.log($);
            $('.toast').toast();
            // $('.toast').toast('show');
            // console.log($('#myAlert').text());
            // $('#myAlert').on('closed.bs.alert', function () {
            //     console.log('success');
// })

            $('#syncItemBtn').click(function() {
                console.log('dasdasd');
                // alert( "Handler for .click() called." );
            });

            // $('#syncItemBtn').submit(function (e){
            //     e.preventDefault();
            //     var url = $(this).attr('action');
            //     var method = $(this).attr('method');
            //     var data = $(this).serialize();

            //     $.ajax({
            //         url: url,
            //         data: data,
            //         method: method,
            //         beforeSend: function() { 
            //             $(".help-block").remove();
            //             $( ".form-control" ).removeClass("is-invalid");
            //             $(".btn-submit").attr("disabled", true);
            //             $(".btn-submit").html(`<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Sending...`);
            //         },
            //         success: function(data){
            //           if(data.success == false)
            //           {
            //             $('.form-check-label').after('<div class="invalid-feedback d-block">Please confirm if all data are correct</div>');
            //           }
            //           else
            //           {
            //             window.location.reload();
            //           }
            //         },
            //         error: function(response){
            //             var errors = response.responseJSON;
            //             $.each(errors.errors, function (index, value) {
            //                 var id = $("#"+index);
            //                 id.closest('.form-control')
            //                 .addClass('is-invalid');
                            
            //                 if(id.next('.select2-container').length > 0){
            //                     id.next('.select2-container').after('<div class="help-block text-danger">'+value+'</div>');
            //                 }else{
            //                     id.after('<div class="help-block text-danger">'+value+'</div>');
            //                 }
            //             });
                        
            //             if($(".is-invalid").length) {
            //                 $('html, body').animate({
            //                         scrollTop: ($(".is-invalid").first().offset().top - 95)
            //                 },500);
            //             }
                        
            //         },
            //         complete: function() {
            //             $(".btn-submit").attr("disabled", false);
            //             $(".btn-submit").html('Submit');
            //         }
            //     })
            // })
        });
    </script>
@endpush

