@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-12">

            <div id="myAlert" class="alert alert-primary" role="alert">
                A simple primary alert with <a href="#" class="alert-link">an example link</a>. Give it a click if you like.
              </div>

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
                                        <a href="#" class="btn btn-primary">
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


                    
                    {{-- {{ __('You are logged in!') }}

                    <form method="POST" action="{{ route('test.index') }}">
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
        // $(document).ready(function() {
            console.log($('#myAlert').text());
            $('#myAlert').on('closed.bs.alert', function () {
                console.log('success');
})
        // });
    </script>
@endpush

