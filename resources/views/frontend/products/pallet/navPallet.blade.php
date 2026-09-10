<div class="shopping-cart-dropdown">
    <ul class="shopping-cart-list">
        @php $total = 0; @endphp

        @if(count($palletItemsForView) > 0)
            @foreach($palletItemsForView as $item)
                @php
                    $product = $item->productVariation->product;
                    $variation = $item->productVariation;
                    $color = $item->color;
                    
                    // Handle both database and session data
                    if (isset($palletData)) {
                        // Database data
                        $palletItem = $palletData[$item->id] ?? null;
                        $quantity = $palletItem ? ($palletItem['quantity'] ?? 1) : 1;
                    } else {
                        // Session data
                        $palletItem = $pallet[$item->id] ?? null;
                        $quantity = $palletItem ? ($palletItem['quantity'] ?? 1) : 1;
                    }
                    
                    $price = $variation->pricing ?? 0;
                    $subtotal = $quantity * $price;
                    $total += $subtotal;
                @endphp 
                <li class="cart-item">
                    <div class="cart-item-img">
                        <a href="{{ url('product-detail', $product->slug) }}">
                            <img alt="{{ $product->name }}"
                                src="{{ $product->feature_image ? asset($product->feature_image) : asset('demo.jpg') }}">
                        </a>
                    </div>
                    <div class="cart-item-content">
                        <h4 class="cart-item-title">
                            <a href="{{ url('product-detail', $product->slug) }}">{{ $product->name }}</a>
                        </h4>
                        <p class="cart-item-details">
                            <span>Size: {{ $variation->size->name ?? 'N/A' }}</span>,
                            <span>Thickness: {{ $variation->thickness->name ?? 'N/A' }}</span><br>
                            <span>Finish: {{ $variation->finish->name ?? 'N/A' }}</span>,
                            <span>Paint Type: {{ $variation->paint_type->name ?? 'N/A' }}</span><br>
                            <span class="highlight-color">Color: {{ $color->name ?? 'N/A' }}</span>,
                            <span>Color Effect: {{ $variation->color_effect->name ?? 'N/A' }}</span>
                        </p>
                        <h4 class="cart-item-price">
                            <span>{{ $quantity }} × </span> ${{ number_format($price, 2) }}
                        </h4>
                    </div>
                    <div class="cart-item-delete">
                        <a href="javascript:void(0)" class="remove-cart-item remove-pallet-item" data-id="{{ encrypt($item->id) }}"><i class="fi-rs-cross-small"></i></a>
                    </div>
                </li>
            @endforeach
        @else
            <li class="cart-empty">
                <strong>No items in pallet.</strong>
            </li>
        @endif
    </ul>

    <div class="shopping-cart-footer">
        <div class="shopping-cart-total">
            <h4>Total: <span>${{ number_format($total, 2) }}</span></h4>
        </div>
        <div class="shopping-cart-buttons">
            <a href="{{ url('pallet') }}" class="btn-outline">View Pallet</a>
            <a href="{{url('checkout')}}" class="btn-cart-primary">Checkout</a>
        </div>
    </div>
</div>