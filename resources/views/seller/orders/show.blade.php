@extends('seller.layouts.app')

@section('panel_content')

    <div class="card">
        <div class="card-header">
            <h1 class="h2 fs-16 mb-0">{{ translate('Order Details') }}</h1>
        </div>

        <div class="card-body">
            <div class="row gutters-5 mb-3">
                <div class="col text-md-left text-center">
                </div>
                @php
                    $delivery_status = $order->delivery_status;

                    $payment_status = $order->orderDetails
                        ->where('seller_id', Auth::user()->id)
                        ->first()?->payment_status;
                @endphp
                @if (get_setting('product_manage_by_admin') == 0)
                    <div class="col-md-3 ml-auto d-flex">
                        @if ($payment_status != 'paid')
                        <button id="payment_for_storehouse" type="button" class="btn btn-info">Payment For Storehouse</button>
                        @endif
                    </div>
                    <div class="col-md-3 ml-auto">
                        <label for="update_payment_status">{{ translate('Payment Status') }}</label>
                        {{-- @if (($order->payment_type == 'cash_on_delivery' || (addon_is_activated('offline_payment') == 1 && $order->manual_payment == 1)) && $payment_status == 'unpaid') --}}
                            {{-- @if ($payment_status != 'paid')
                            
                            <select class="form-control aiz-selectpicker" data-minimum-results-for-search="Infinity"
                                id="update_payment_status">
                                <option value="unpaid" @if ($payment_status == 'unpaid') selected @endif>
                                    {{ translate('Unpaid') }}</option>
                                <option value="paid" @if ($payment_status == 'paid') selected @endif>
                                    {{ translate('Paid') }}</option>
                            </select>
                        @else --}}
                            <input type="text" class="form-control" value="{{ translate($payment_status) }}" disabled>
                        {{-- @endif --}}
                    </div>
                    <div class="col-md-3 ml-auto">
                        <label for="update_delivery_status">{{ translate('Delivery Status') }}</label>
                        {{-- @if ($delivery_status != 'delivered' && $delivery_status != 'cancelled')
                            <select class="form-control aiz-selectpicker" data-minimum-results-for-search="Infinity"
                                id="update_delivery_status">
                                <option value="pending" @if ($delivery_status == 'pending') selected @endif>
                                    {{ translate('Pending') }}</option>
                                <option value="confirmed" @if ($delivery_status == 'confirmed') selected @endif>
                                    {{ translate('Confirmed') }}</option>
                                <option value="picked_up" @if ($delivery_status == 'picked_up') selected @endif>
                                    {{ translate('Picked Up') }}</option>
                                <option value="on_the_way" @if ($delivery_status == 'on_the_way') selected @endif>
                                    {{ translate('On The Way') }}</option>
                                <option value="delivered" @if ($delivery_status == 'delivered') selected @endif>
                                    {{ translate('Delivered') }}</option>
                                <option value="cancelled" @if ($delivery_status == 'cancelled') selected @endif>
                                    {{ translate('Cancel') }}</option>
                            </select>
                        @else --}}
                            <input type="text" class="form-control" value="{{ translate(ucfirst(str_replace('_', ' ', $delivery_status))) }}" disabled>
                        {{-- @endif --}}
                    </div>
                    {{-- <div class="col-md-3 ml-auto">
                        <label for="update_tracking_code">
                            {{ translate('Tracking Code (optional)') }}
                        </label>
                        <input type="text" class="form-control" id="update_tracking_code"
                            value="{{ $order->tracking_code }}">
                    </div> --}}
                @endif
            </div>
            <div class="row gutters-5 mt-2">
                <div class="col text-md-left text-center">
                @php
                     if (!function_exists('maskings')) {
                            function maskings($string) {
                                $len = strlen($string);
                                if ($len <= 4) {
                                    return str_repeat('*', $len); // Fully mask short strings
                                }
                                return substr($string, 0, 2) . str_repeat('*', $len - 4) . substr($string, -2);
                            }
                        }
                    @endphp


                    @if(json_decode($order->shipping_address))
                        @php $addr = json_decode($order->shipping_address); @endphp
                        <address>
                            <strong class="text-main">
                                {{ maskings($addr->name) }}
                            </strong><br>
                            {{ maskings($addr->email) }}<br>
                            {{ maskings($addr->phone) }}<br>
                            {{ maskings($addr->address) }}, {{ maskings($addr->city) }}, 
                            @if(isset($addr->state)) {{ maskings($addr->state) }} - @endif 
                            {{ maskings($addr->postal_code) }}<br>
                            {{ maskings($addr->country) }}
                        </address>
                    @else
                        <address>
                            <strong class="text-main">
                                {{ maskings($order->user->name) }}
                            </strong><br>
                            {{ maskings($order->user->email) }}<br>
                            {{ maskings($order->user->phone) }}<br>
                        </address>
                    @endif

                    @if ($order->manual_payment && is_array(json_decode($order->manual_payment_data, true)))
                        <br>
                        <strong class="text-main">{{ translate('Payment Information') }}</strong><br>
                        {{ translate('Name') }}: {{ json_decode($order->manual_payment_data)->name }},
                        {{ translate('Amount') }}:
                        {{ single_price(json_decode($order->manual_payment_data)->amount) }},
                        {{ translate('TRX ID') }}: {{ json_decode($order->manual_payment_data)->trx_id }}
                        <br>
                        <a href="{{ uploaded_asset(json_decode($order->manual_payment_data)->photo) }}"
                            target="_blank"><img
                                src="{{ uploaded_asset(json_decode($order->manual_payment_data)->photo) }}" alt=""
                                height="100"></a>
                    @endif
                </div>
                <div class="col-md-4">
                    <table class="ml-auto">
                        <tbody>
                            <tr>
                                <td class="text-main text-bold">{{ translate('Order #') }}</td>
                                <td class="text-info text-bold text-right">{{ $order->code }}</td>
                            </tr>
                            <tr>
                                <td class="text-main text-bold">{{ translate('Order Status') }}</td>
                                <td class="text-right">
                                    @if ($delivery_status == 'delivered')
                                        <span
                                            class="badge badge-inline badge-success">{{ translate(ucfirst(str_replace('_', ' ', $delivery_status))) }}</span>
                                    @else
                                        <span
                                            class="badge badge-inline badge-info">{{ translate(ucfirst(str_replace('_', ' ', $delivery_status))) }}</span>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <td class="text-main text-bold">{{ translate('Order Date') }}</td>
                                <td class="text-right">{{ $order->created_at->format('d-m-Y h:i A') }}</td>
                            </tr>
                            <tr>
                                <td class="text-main text-bold">{{ translate('Total amount') }}</td>
                                <td class="text-right">
                                    {{ single_price($order->grand_total) }}
                                </td>
                            </tr>
                            <tr>
                                <td class="text-main text-bold">{{ translate('Payment method') }}</td>
                                <td class="text-right">
                                    {{ translate(ucfirst(str_replace('_', ' ', $order->payment_type))) }}</td>
                            </tr>

                            <tr>
                                <td class="text-main text-bold">{{ translate('Additional Info') }}</td>
                                <td class="text-right">{{ $order->additional_info }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <hr class="new-section-sm bord-no">
            <div class="row">
                <div class="col-lg-12 table-responsive">
                    <table class="table-bordered aiz-table invoice-summary table">
                        <thead>
                            <tr class="bg-trans-dark">
                                <th data-breakpoints="lg" class="min-col">#</th>
                                <th width="10%">{{ translate('Photo') }}</th>
                                <th class="text-uppercase">{{ translate('Description') }}</th>
                                <th data-breakpoints="lg" class="text-uppercase">{{ translate('Delivery Type') }}</th>
                                <th data-breakpoints="lg" class="min-col text-uppercase text-center">
                                    {{ translate('Qty') }}
                                </th>
                                <th data-breakpoints="lg" class="min-col text-uppercase text-center">
                                    {{ translate('Price') }}</th>
                                <th data-breakpoints="lg" class="min-col text-uppercase text-right">
                                    {{ translate('Total') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($order->orderDetails as $key => $orderDetail)
                                <tr>
                                    <td>{{ $key + 1 }}</td>
                                    <td>
                                        @if ($orderDetail->product != null && $orderDetail->product->auction_product == 0)
                                            <a href="{{ route('product', $orderDetail->product->slug) }}"
                                                target="_blank"><img height="50"
                                                    src="{{ uploaded_asset($orderDetail->product->thumbnail_img) }}"></a>
                                        @elseif ($orderDetail->product != null && $orderDetail->product->auction_product == 1)
                                            <a href="{{ route('auction-product', $orderDetail->product->slug) }}"
                                                target="_blank"><img height="50"
                                                    src="{{ uploaded_asset($orderDetail->product->thumbnail_img) }}"></a>
                                        @else
                                            <strong>{{ translate('N/A') }}</strong>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($orderDetail->product != null && $orderDetail->product->auction_product == 0)
                                            <strong><a href="{{ route('product', $orderDetail->product->slug) }}"
                                                    target="_blank"
                                                    class="text-muted">{{ $orderDetail->product->getTranslation('name') }}</a></strong>
                                            <small>{{ $orderDetail->variation }}</small>
                                        @elseif ($orderDetail->product != null && $orderDetail->product->auction_product == 1)
                                            <strong><a href="{{ route('auction-product', $orderDetail->product->slug) }}"
                                                    target="_blank"
                                                    class="text-muted">{{ $orderDetail->product->getTranslation('name') }}</a></strong>
                                        @else
                                            <strong>{{ translate('Product Unavailable') }}</strong>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($order->shipping_type != null && $order->shipping_type == 'home_delivery')
                                            {{ translate('Home Delivery') }}
                                        @elseif ($order->shipping_type == 'pickup_point')
                                            @if ($order->pickup_point != null)
                                                {{ $order->pickup_point->getTranslation('name') }}
                                                ({{ translate('Pickup Point') }})
                                            @else
                                                {{ translate('Pickup Point') }}
                                            @endif
                                        @elseif($order->shipping_type == 'carrier')
                                            @if ($order->carrier != null)
                                                {{ $order->carrier->name }} ({{ translate('Carrier') }})
                                                <br>
                                                {{ translate('Transit Time').' - '.$order->carrier->transit_time }}
                                            @else
                                                {{ translate('Carrier') }}
                                            @endif
                                        @endif
                                    </td>
                                    <td class="text-center">{{ $orderDetail->quantity }}</td>
                                    <td class="text-center">
                                        {{ single_price($orderDetail->price / $orderDetail->quantity) }}</td>
                                    <td class="text-center">{{ single_price($orderDetail->price) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="clearfix float-right">
                <table class="table">
                    <tbody>
                        <tr>
                            <td>
                                <strong class="text-muted">{{ translate('Sub Total') }} :</strong>
                            </td>
                            <td>
                                {{ single_price($order->orderDetails->sum('price')) }}
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <strong class="text-muted">{{ translate('Profit') }} :</strong>
                            </td>
                            <td> 
                                ${{ number_format($order->orderDetails->sum('price') * ($commission_percentage / 100), 2) }}
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <strong class="text-muted">{{ translate('Tax') }} :</strong>
                            </td>
                            <td>
                                {{ single_price($order->orderDetails->sum('tax')) }}
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <strong class="text-muted">{{ translate('Shipping') }} :</strong>
                            </td>
                            <td>
                                {{ single_price($order->orderDetails->sum('shipping_cost')) }}
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <strong class="text-muted">{{ translate('Coupon') }} :</strong>
                            </td>
                            <td>
                                {{ single_price($order->coupon_discount) }}
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <strong class="text-muted">{{ translate('TOTAL') }} :</strong>
                            </td>
                            <td class="text-muted h5">
                                {{ single_price($order->grand_total + ($order->orderDetails->sum('price') * ($commission_percentage / 100))) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
                
            </div>

        </div>
    </div>
    <div class="modal fade" id="transactionPinModal" tabindex="-1" role="dialog" aria-labelledby="transactionPinModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ translate('Enter Transaction PIN') }}</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="{{ translate('Close') }}">
                <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <input type="password" id="transaction_pin_input" class="form-control" placeholder="{{ translate('Transaction PIN') }}">
                <div id="pin_error" class="text-danger mt-2" style="display:none;">{{ translate('Invalid PIN') }}</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ translate('Cancel') }}</button>
                <button type="button" id="submit_transaction_pin" class="btn btn-primary">{{ translate('Submit') }}</button>
            </div>
            </div>
        </div>
    </div>

@endsection

@section('script')
    <script type="text/javascript">
        $('#update_delivery_status').on('change', function() {
            var order_id = {{ $order->id }};
            var status = $('#update_delivery_status').val();
            $.post('{{ route('seller.orders.update_delivery_status') }}', {
                _token: '{{ @csrf_token() }}',
                order_id: order_id,
                status: status
            }, function(data) {
                $('#order_details').modal('hide');
                AIZ.plugins.notify('success', '{{ translate('Order status has been updated') }}');
                location.reload().setTimeOut(500);
            });
        });

        $('#payment_for_storehouse').on('click', function() {
            $('#transactionPinModal').modal('show');
        });

        $('#submit_transaction_pin').on('click', function () {
            var pin = $('#transaction_pin_input').val();
            var order_id = {{ $order->id }};
            var status = 'paid';

            // First: verify transaction pin
            $.ajax({
                type: 'POST',
                url: '{{ route('seller.orders.verify_transaction_pin') }}',
                data: {
                    _token: $('meta[name="csrf-token"]').attr('content'),
                    pin: pin
                },
                success: function (response) {
                    if (response.status === 'success') {
                        // Second: update payment status
                        $.ajax({
                            type: 'POST',
                            url: '{{ route('seller.orders.update_payment_status') }}',
                            data: {
                                _token: $('meta[name="csrf-token"]').attr('content'),
                                order_id: order_id,
                                status: status
                            },
                            success: function (res) {
                                if (res === 1 || res === '1') {
                                    AIZ.plugins.notify('success', 'Payment status has been updated successfully.');
                                    $('#transactionPinModal').modal('hide');
                                    location.reload();
                                } else {
                                    // Treat anything else as failure
                                    $('#pin_error').show().text('Failed to update payment status.');
                                    AIZ.plugins.notify('danger', 'Failed to update payment status.');
                                }
                            },
                            error: function (xhr) {
                                let msg = 'Unexpected error while updating payment.';
                                try {
                                    let json = JSON.parse(xhr.responseText);
                                    msg = json.message || msg;
                                } catch (e) {}
                                $('#pin_error').show().text(msg);
                                AIZ.plugins.notify('danger', msg);
                            }
                        });
                    } else {
                        $('#pin_error').show().text(response.message);
                        AIZ.plugins.notify('danger', response.message);
                    }
                },
                error: function (xhr) {
                    let msg = 'Unexpected error during PIN verification.';
                    try {
                        let json = JSON.parse(xhr.responseText);
                        msg = json.message || msg;
                    } catch (e) {
                        if (xhr.status === 403) msg = 'Forbidden or invalid CSRF token.';
                        else if (xhr.status === 419) msg = 'Session expired. Please refresh.';
                    }

                    $('#pin_error').show().text(msg);
                    AIZ.plugins.notify('danger', msg);
                }
            });
        });

    </script>
@endsection
