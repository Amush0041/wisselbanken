@extends('frontend.layouts.app')
@push('seo')
<title> Home | {{env('APP_NAME','Wisselbanken')}}</title>
<meta name="description" content="">
@endpush
@push('css')
<style>
    .text-primary {
        color: white !important;
    }

    .hero-slider-1.style-3 .hero-slider-content-2 {
        position: absolute;
        z-index: 2;
        top: 50%;
        left: 17%;
        text-align: center;
        -webkit-transform: translateY(-50%);
        transform: translateY(-50%);
        color: #fff;
        padding-left: 0px;
    }

    .hero-slider-1 img {
        max-height: 500px;
    }

    .bg-color-tertiary {
        background-color: #e1e2e5 !important;
        text-align: center;
    }

    .myHmpg-products__container .myHmpg-products__list {
        display: block;
        padding: 0 0 0 10px;
    }

    .myHmpg-products__list>ul,
    .myHmpg-products__list>ul li {
        list-style: none;
        margin: 0;
        padding: 0;
    }

    .myHmpg-products__list>ul li {
        display: block;
        padding: 0 10px;
        line-height: 1.1;
    }

    .myHmpg-products__list>ul li>h3 {
        margin: 0;
        padding: 0;
    }

    .myHmpg-products__list>ul li:hover,
    .myHmpg-products__list>ul li.menu__open {
        padding-left: 7px;
        border-left: 3px solid #e3b143;
        background-color: #fff;
        box-shadow: rgba(100, 100, 111, 0.2) 0px 7px 29px 0px;
        color: #e3b143;
    }

    .myHmpg-products__list>ul li:hover>h3 a,
    .myHmpg-products__list>ul li.menu__open>h3 a {
        color: #4a171e;
        text-decoration: none;
    }

    .myHmpg-products__list>ul li>h3 a {
        font-size: 13px;
        font-weight: bold;
        color: #4a171e;
        line-height: auto;
        text-decoration: none;
        padding: 5px 0;
        display: block;
    }

    .dropdown-arrow {
        transform: rotate(0deg);
        transition: transform 0.2s ease;
        margin-left: auto;
        padding-left: 10px;
    }

    .menu__open .dropdown-arrow {
        transform: rotate(90deg);
    }

    .specifications-modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
        z-index: 10000;
        overflow-y: auto;
        align-items: flex-start;
        justify-content: center;
        padding: 24px 16px 40px;
    }

    .specifications-modal.show {
        display: flex;
    }

    .specifications-modal-content {
        position: relative;
        background: #fff;
        margin: 0 auto;
        width: 94%;
        max-width: 1100px;
        min-height: 400px;
        max-height: 90vh;
        overflow-y: auto;
        border-radius: 12px;
        box-shadow: 0 12px 48px rgba(0, 0, 0, 0.2), 0 0 0 1px rgba(74, 23, 30, 0.08);
        padding: 0;
        flex-shrink: 0;
    }

    .specifications-modal-header {
        display: flex;
        justify-content: flex-end;
        align-items: center;
        padding: 12px 20px 12px 24px;
        background: linear-gradient(135deg, #4a171e 0%, #5a1f28 100%);
        border-radius: 12px 12px 0 0;
        border-bottom: 3px solid #e3b143;
    }

    .specifications-modal-header h2 {
        margin: 0;
        color: #fff;
        font-size: 20px;
        font-weight: 700;
        margin-right: auto;
    }

    .specifications-modal-close {
        background: rgba(255, 255, 255, 0.15);
        border: 1px solid rgba(255, 255, 255, 0.3);
        font-size: 22px;
        color: #fff;
        cursor: pointer;
        padding: 0;
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        transition: background 0.2s, color 0.2s;
        line-height: 1;
    }

    .specifications-modal-close:hover {
        background: #e3b143;
        color: #4a171e;
        border-color: #e3b143;
    }

    .specifications-list {
        margin: 0;
        padding: 24px 28px 28px;
    }

    .specification-division {
        margin-bottom: 28px;
    }

    .specification-division:last-child {
        margin-bottom: 0;
    }

    .division-heading {
        margin: 0 0 12px 0;
        padding: 8px 14px;
        font-size: 16px;
        font-weight: 700;
        color: #fff;
        line-height: 1.3;
        background: linear-gradient(135deg, #4a171e 0%, #5a1f28 100%);
        border-radius: 6px;
        border-left: 3px solid #e3b143;
    }

    .division-name {
        margin-right: 6px;
    }

    .division-code {
        font-weight: 600;
        font-size: 14px;
        color: #e3b143;
        opacity: 0.95;
    }

    .specifications-categories-wrapper {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 18px 24px;
        margin-left: 0;
    }

    .specification-category {
        margin-bottom: 0;
        margin-left: 0;
        break-inside: avoid;
        background: #faf8f5;
        border: 1px solid #efe2d7;
        border-radius: 8px;
        padding: 14px 16px;
        transition: box-shadow 0.2s, border-color 0.2s;
    }

    .specification-category:hover {
        border-color: rgba(74, 23, 30, 0.25);
        box-shadow: 0 4px 12px rgba(74, 23, 30, 0.08);
    }

    .category-heading {
        margin: 0 0 8px 0;
        padding: 4px 0 6px 0;
        font-size: 12px;
        font-weight: 600;
        color: #4a171e;
        line-height: 1.35;
        border-bottom: 1px solid rgba(74, 23, 30, 0.1);
    }

    .category-code {
        margin-right: 4px;
        color: #5a1f28;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.02em;
    }

    .category-name {
        font-weight: 600;
        color: #333;
        font-size: 12px;
    }

    .specification-items {
        list-style: none;
        margin: 0;
        padding: 0;
    }

    .specification-item {
        margin: 0;
        padding: 6px 0;
        line-height: 1.4;
        border-bottom: 1px solid rgba(0, 0, 0, 0.05);
    }

    .specification-item:last-child {
        border-bottom: none;
    }

    .specification-item a {
        color: #333;
        text-decoration: none;
        display: block;
        font-size: 13px;
        transition: color 0.2s, padding-left 0.2s;
        padding: 2px 0;
    }

    .specification-item a:hover {
        color: #4a171e;
        padding-left: 4px;
    }

    .specification-number {
        font-weight: 600;
        color: #4a171e;
        margin-right: 6px;
    }

    .specification-type {
        color: #555;
    }

    @media (min-width: 1200px) {
        .specifications-categories-wrapper {
            grid-template-columns: repeat(4, 1fr);
        }
    }

    @media (max-width: 991px) {
        .specifications-categories-wrapper {
            grid-template-columns: repeat(3, 1fr);
        }
    }

    @media (max-width: 768px) {
        .specifications-modal {
            padding: 12px 8px 24px;
        }

        .specifications-modal-content {
            width: 100%;
            margin: 0;
            border-radius: 8px;
            max-height: 95vh;
            padding: 0;
        }

        .specifications-modal-header {
            padding: 10px 16px;
            border-radius: 8px 8px 0 0;
        }

        .specifications-list {
            padding: 16px;
        }

        .division-heading {
            font-size: 15px;
            padding: 8px 12px;
            margin-bottom: 10px;
        }

        .specifications-categories-wrapper {
            grid-template-columns: 1fr;
            gap: 12px;
        }

        .specification-category {
            padding: 12px 14px;
        }

        .category-heading {
            font-size: 11px;
        }
        .category-code, .category-name {
            font-size: 11px;
        }

        .specification-item a {
            font-size: 13px;
        }
    }

    /* Position adjustments */
    .position-relative {
        position: relative;
    }

    .hero-slider-1.style-3 .slider-1-height-3 {
        height: 390px;
    }

    .custom .banner-text {
        height: 194px;
        padding: 15px;
        background: #4a171e;
    }

    .custom .banner-text h4,
    .custom .banner-text p {
        color: white;
    }

    .custom .banner-text a,
    .custom .banner-text span {
        color: #e3b143;
    }

    @media only screen and (max-width: 480px) {
        .hero-slider-1.style-3 .slider-1-height-3 {
            height: 260px;
        }
    }
</style>
@endpush
@section('content')
<section class="home-slider position-relative pt-25 pb-20">
    <div class="container-fluid px-4">
        <div class="row">
            <div class="col-lg-3">
                <div class="card">
                    <div class="card-body">
                        <div class="myHmpg-products__list">
                            <ul class="list-unstyled">
                                @foreach (\App\Helper\Helper::getDivisions() as $division)
                                <li class="nav-item dropdown position-relative">
                                    <h3>
                                        <a href="{{ route('product-division', $division->slug) }}"
                                            class="nav-link text-dark d-flex align-items-center division-link parent-link"
                                            data-has-dropdown="{{ count($division->specifications) > 0 ? 'true' : 'false' }}">
                                            {{$division->code}} - {{$division->name}}
                                            @if(count($division->specifications) > 0)
                                            <span class="dropdown-arrow">›</span>
                                            @endif
                                        </a>
                                    </h3>
                                </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-9">
                <div class="position-relative">
                    <div class="hero-slider-1 style-3 dot-style-1 dot-style-1-position-1">
                        <div class="single-hero-slider single-animation-wrap">
                            <div class="container-fluid px-4">
                                <div class="slider-1-height-3 slider-animated-1">
                                    <div class="hero-slider-content-2">
                                        <h2 class="animated fw-900  text-primary">INCREDIBLE DESIGNS</h2>
                                        <h4 class="animated  text-primary">Wisselbanken is a huge success in the one of largest world's MarketPlace</h4>
                                        <a class="btn btn-primary btn-modern font-weight-bold text-2 py-3 btn-px-5 mt-2 appear-animation" href="javascript:void(0)"> Shop Now </a>
                                    </div>
                                    <div class="slider-img" style="width: 100%;">
                                        <img src="{{asset('frontend/slide_2.jpg')}}" style="width: 100%;" alt="">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="single-hero-slider single-animation-wrap">
                            <div class="container-fluid px-4">
                                <div class="slider-1-height-3 slider-animated-1">
                                    <div class="hero-slider-content-2">
                                        <h2 class="animated fw-900 text-primary">Inovate your vision</h2>
                                        <h4 class="animated  text-primary">Wisselbanken is a huge success in the one of largest world's MarketPlace</h4>
                                        <a class="btn btn-primary btn-modern font-weight-bold text-2 py-3 btn-px-5 mt-2 appear-animation" href="javascript:void(0)"> Explore Now </a>
                                    </div>
                                    <div class="slider-img" style="width: 100%;">
                                        <img src="{{asset('frontend/slide_3.png')}}" style="width: 100%;" alt="">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="slider-arrow hero-slider-1-arrow style-3"></div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Rest of your content remains the same -->
<section class="banners custom mb-20">
    <div class="container-fluid px-4">
        <div class="row">
            <div class="col-lg-4 col-md-4  col-sm-12 mb-2 mt-2">
                <div class="banner-text">
                    <span>Expert Planning</span>
                    <h4 class="mb-2 mt-2">Save Time and <br>Costs on Construction</h4>
                    <p>Partner with Wisselbanken for precise project planning and resource management.</p>
                    <a href="#">Learn More <i class="fi-rs-arrow-right"></i></a>
                </div>
            </div>
            <div class="col-lg-4 col-md-4 col-sm-12 mb-2 mt-2">
                <div class="banner-text">
                    <span>Innovative Solutions</span>
                    <h4 class="mb-2 mt-2">Optimized Design and <br>Material Planning</h4>
                    <p>Our team ensures quality materials and efficient design for every build.</p>
                    <a href="#">Discover Services <i class="fi-rs-arrow-right"></i></a>
                </div>
            </div>
            <div class="col-lg-4 col-md-4  col-sm-12 mb-2 mt-2">
                <div class="banner-text">
                    <span>Proven Success</span>
                    <h4 class="mb-2 mt-2">Trusted by Top <br>Construction Firms</h4>
                    <p>Delivering successful projects with expertise and precision.</p>
                    <a href="#">View Projects <i class="fi-rs-arrow-right"></i></a>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="py-3 py-md-5">
    <div class="container">
        <div class="row gy-3 gy-md-4 gy-lg-0 align-items-lg-center">
            <div class="col-12 col-lg-6 col-xl-5">
                <img class="img-fluid rounded" loading="lazy" src="{{asset('frontend/slide_3.png')}}" alt="About Wisselbanken - Expert Commercial Construction Services">
            </div>
            <div class="col-12 col-lg-6 col-xl-7">
                <div class="row justify-content-xl-center">
                    <div class="col-12 col-xl-11">
                        <h2 class="mb-3 text-brand text-uppercase">About Us</h2>
                        <p class="mb-2"><strong>Wisselbanken</strong> is a premier partner in the early phases of commercial construction, delivering innovative preconstruction planning, precise cost estimation, and value-driven engineering solutions for complex building projects.</p>
                        <p class="mb-4">Our dedicated team works closely with clients to assess design feasibility, optimize budgets, and source high-quality materials, ensuring each project is set up for success from day one.</p>
                        <div class="row gy-4 gy-md-0 gx-xxl-5">
                            <div class="col-12 col-md-6">
                                <div class="d-flex">
                                    <div class="me-4"><i class="fi-rs-settings fs-2"></i></div>
                                    <div>
                                        <h2 class="h5 mb-2">Innovative Solutions</h2>
                                        <p class="text-secondary mb-0">We provide cutting-edge tools and strategies to streamline construction planning and execution.</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="d-flex">
                                    <div class="me-4"><i class="fi-rs-building fs-2"></i></div>
                                    <div>
                                        <h2 class="h5 mb-2">Trusted Expertise</h2>
                                        <p class="text-secondary mb-0">Our experience in commercial construction ensures quality, timeliness, and cost-efficiency for every project.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <p class="mt-4">Partner with <strong>Wisselbanken</strong> for a seamless, efficient construction journey that meets your unique needs and exceeds expectations.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

@php
    $groupedSpecifications = [];
    
    foreach (\App\Helper\Helper::getDivisions() as $division) {
        $categories = [];
        
        foreach ($division->specifications as $specification) {
            $specNumber = $specification->specification_number;
            $parts = explode(' ', $specNumber);
            
            if (count($parts) >= 2) {
                $majorCategory = $parts[0] . ' ' . $parts[1];
                
                if (!isset($categories[$majorCategory])) {
                    $categories[$majorCategory] = [
                        'code' => $majorCategory . ' 00',
                        'specifications' => []
                    ];
                }
                
                $categories[$majorCategory]['specifications'][] = [
                    'specification' => $specification,
                    'division' => $division
                ];
            }
        }
        
        if (!empty($categories)) {
            foreach ($categories as $key => $category) {
                usort($category['specifications'], function($a, $b) {
                    $aParts = explode(' ', $a['specification']->specification_number);
                    $bParts = explode(' ', $b['specification']->specification_number);
                    
                    $aFirst = isset($aParts[0]) ? (int)$aParts[0] : 0;
                    $bFirst = isset($bParts[0]) ? (int)$bParts[0] : 0;
                    if ($aFirst !== $bFirst) {
                        return $aFirst <=> $bFirst;
                    }
                    
                    $aSecond = isset($aParts[1]) ? (int)$aParts[1] : 0;
                    $bSecond = isset($bParts[1]) ? (int)$bParts[1] : 0;
                    if ($aSecond !== $bSecond) {
                        return $aSecond <=> $bSecond;
                    }
                    
                    $aThird = isset($aParts[2]) ? (int)$aParts[2] : 0;
                    $bThird = isset($bParts[2]) ? (int)$bParts[2] : 0;
                    return $aThird <=> $bThird;
                });
                $categories[$key] = $category;
            }
            
            uksort($categories, function($a, $b) {
                $aParts = explode(' ', $a);
                $bParts = explode(' ', $b);
                $aFirst = isset($aParts[0]) ? (int)$aParts[0] : 0;
                $bFirst = isset($bParts[0]) ? (int)$bParts[0] : 0;
                if ($aFirst !== $bFirst) {
                    return $aFirst <=> $bFirst;
                }
                $aSecond = isset($aParts[1]) ? (int)$aParts[1] : 0;
                $bSecond = isset($bParts[1]) ? (int)$bParts[1] : 0;
                return $aSecond <=> $bSecond;
            });
            
            $groupedSpecifications[] = [
                'division' => $division,
                'categories' => $categories
            ];
        }
    }
    
    usort($groupedSpecifications, function($a, $b) {
        $aCode = (int)$a['division']->code;
        $bCode = (int)$b['division']->code;
        return $aCode <=> $bCode;
    });
@endphp

<div class="specifications-modal" id="specificationsModal">
    <div class="specifications-modal-content">
        <div class="specifications-modal-header">
            <button class="specifications-modal-close" id="closeSpecificationsModal" aria-label="Close">&times;</button>
        </div>
        <div class="specifications-list">
            @foreach($groupedSpecifications as $group)
                <div class="specification-division">
                    <h3 class="division-heading">
                        <span class="division-name">{{ $group['division']->name }}</span>
                        <span class="division-code">{{ $group['division']->code }}</span>
                    </h3>
                    
                    <div class="specifications-categories-wrapper">
                    @foreach($group['categories'] as $category)
                        <div class="specification-category">
                            <h4 class="category-heading">
                                <span class="category-code">{{ $category['code'] }}</span>
                                @php
                                    $categoryName = '';
                                    $materialTypes = array_map(function($item) {
                                        return $item['specification']->material_type;
                                    }, $category['specifications']);
                                    
                                    $words = [];
                                    foreach ($materialTypes as $type) {
                                        $typeWords = explode(' ', strtolower($type));
                                        foreach ($typeWords as $word) {
                                            if (strlen($word) > 3) {
                                                $words[] = $word;
                                            }
                                        }
                                    }
                                    
                                    $wordCounts = array_count_values($words);
                                    arsort($wordCounts);
                                    $commonWords = array_slice(array_keys($wordCounts), 0, 2);
                                    
                                    if (!empty($commonWords)) {
                                        $categoryName = ucwords(implode(' ', $commonWords));
                                        if (stripos($categoryName, 'panel') !== false) {
                                            $categoryName = str_replace('panel', 'Panels', $categoryName);
                                        }
                                    } else {
                                        $firstSpec = $category['specifications'][0]['specification'];
                                        $specParts = explode(' ', $firstSpec->specification_number);
                                        if (count($specParts) >= 2) {
                                            $baseCode = $specParts[0] . ' ' . $specParts[1];
                                            if ($baseCode == '07 41') {
                                                $categoryName = 'Roof Panels';
                                            } elseif ($baseCode == '07 42') {
                                                $categoryName = 'Wall Panels';
                                            } elseif ($baseCode == '07 44') {
                                                $categoryName = 'Faced Panels';
                                            } elseif ($baseCode == '07 46') {
                                                $categoryName = 'Siding';
                                            } else {
                                                $categoryName = $firstSpec->material_type;
                                            }
                                        }
                                    }
                                @endphp
                                <span class="category-name">{{ $categoryName }}</span>
                            </h4>
                            <ul class="specification-items">
                                @foreach($category['specifications'] as $item)
                                <li class="specification-item">
                                    <a href="{{ url('product-manufacturer?filter='.$item['division']->code. '-' . $item['specification']->specification_number) }}">
                                        <span class="specification-number">{{ $item['specification']->specification_number }}</span>
                                        <span class="specification-type">- {{ $item['specification']->material_type }}</span>
                                    </a>
                                </li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    const modal = $('#specificationsModal');
    const closeBtn = $('#closeSpecificationsModal');
    
    const openModal = () => {
        modal.addClass('show');
        $('body').css('overflow', 'hidden');
    };
    
    const closeModal = () => {
        modal.removeClass('show');
        $('body').css('overflow', '');
    };
    
    $('.parent-link[data-has-dropdown="true"]').on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        openModal();
    });
    
    closeBtn.on('click', closeModal);
    
    modal.on('click', function(e) {
        if ($(e.target).is(modal)) {
            closeModal();
        }
    });
    
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape' && modal.hasClass('show')) {
            closeModal();
        }
    });
    
    $('.parent-link[data-has-dropdown="false"]').on('click', function(e) {
    });
});
</script>
@endpush 