<div class="shopping-cart-dropdown">
    <ul class="shopping-cart-list">
        @php $total = 0; @endphp

        @if(count($palletItems) > 0)
@foreach($palletItems as $item)
        @php
        $product = $item->productVariation->product;
        $variation = $item->productVariation;
        $color = $item->color;
        
        // Get quantity - handle both session and database data
        $quantity = 1;
        if (isset($pallet[$item->id])) {
            // Database data (logged-in users)
            $quantity = $pallet[$item->id]['quantity'] ?? 1;
        } else {
            // Session data (guest users)
            $quantity = collect($pallet)->where('product_variation_color_id', $item->id)->pluck('quantity')->first() ?? 1;
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
                    <span>Size: {{ $variation->size->name }}</span>,
                    <span>Thickness: {{ $variation->thickness->name }}</span><br>
                    <span>Finish: {{ $variation->finish->name }}</span>,
                    <span>Paint Type: {{ $variation->paint_type->name }}</span><br>
                    <span class="highlight-color">Color: {{ $color->name }}</span>,
                    <span>Color Effect: {{ $variation->color_effect->name }}</span>
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
</div>

@if(count($palletItems) > 0)
<div class="shopping-cart-total" style="text-align: right;padding-top: 20px;">
    <h4>Total: <span>${{ number_format($total, 2) }}</span></h4>
</div>
@endif