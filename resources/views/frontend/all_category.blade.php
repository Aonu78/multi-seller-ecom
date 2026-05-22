@extends('frontend.layouts.app')

@section('content')
    <!-- Breadcrumb -->
    <section class="pt-4 mb-4">
        <div class="container text-center">
            <div class="row">
                <div class="col-lg-6 text-center text-lg-left">
                    <h1 class="fw-700 fs-20 fs-md-24 text-dark">
                        {{ translate('All Categories') }}
                    </h1>
                </div>
                <div class="col-lg-6">
                    <ul class="breadcrumb bg-transparent p-0 justify-content-center justify-content-lg-end">
                        <li class="breadcrumb-item has-transition opacity-60 hov-opacity-100">
                            <a class="text-reset" href="{{ route('home') }}">{{ translate('Home') }}</a>
                        </li>
                        <li class="text-dark fw-600 breadcrumb-item">
                            "{{ translate('All Categories') }}"
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </section>
    <!-- All Categories -->
    <section class="mb-5 pb-3">
        <div class="container">

            <div class="row">
                @foreach ($categories as $key => $category)

                    <div class="col-xl-2 col-lg-2 col-md-3 col-6 mb-4">
                        <div class="bg-white h-100">

                            <!-- Category Image & Title -->
                            <!-- Category Image -->
                            <a href="{{ route('products.category', $category->slug) }}"
                                class="text-decoration-none d-block overflow-hidden">

                                <div style="height:250px;">
                                    <img src="{{ uploaded_asset($category->banner) }}"
                                        alt="{{ $category->getTranslation('name') }}"
                                        class="w-100 h-100 img-fit" style="border-radius: 15px;">
                                </div>
                            </a>

                            <!-- Category Title Below Image -->
                            <div class="text-center py-3">
                                <a href="{{ route('products.category', $category->slug) }}"
                                    class="text-dark fw-600 fs-16 text-decoration-none">
                                    {{ $category->getTranslation('name') }}
                                </a>
                            </div>

                            <div class="px-4 py-2">
                                <div class="row row-cols-1">

                                    @foreach ($category->childrenCategories as $key => $child_category)

                                        <div class="col text-left mb-3">

                                            <!-- Sub Category Name -->
                                            <h6 class="text-dark mb-3">
                                                <a class="text-reset fw-700 fs-14 hov-text-primary"
                                                    href="{{ route('products.category', $child_category->slug) }}">
                                                    {{ $child_category->getTranslation('name') }}
                                                </a>
                                            </h6>

                                            <!-- Sub-sub Categories -->
                                            <ul class="mb-2 list-unstyled has-transition mh-100 @if ($child_category->childrenCategories->count() > 5) less @endif">

                                                @foreach ($child_category->childrenCategories as $key => $second_level_category)

                                                    <li class="text-dark mb-2">
                                                        <a class="text-reset fw-400 fs-14 hov-text-primary animate-underline-primary"
                                                            href="{{ route('products.category', $second_level_category->slug) }}">
                                                            {{ $second_level_category->getTranslation('name') }}
                                                        </a>
                                                    </li>

                                                @endforeach

                                            </ul>

                                            @if ($child_category->childrenCategories->count() > 5)

                                                <a href="javascript:void(1)"
                                                    class="show-hide-cetegoty text-primary hov-text-primary fs-12 fw-700">
                                                    {{ translate('More') }}
                                                    <i class="las la-angle-down"></i>
                                                </a>

                                            @endif

                                        </div>

                                    @endforeach

                                </div>
                            </div>

                        </div>
                    </div>

                @endforeach
            </div>

        </div>
    </section>
@endsection

@section('script')
    <script>
        $('.show-hide-cetegoty').on('click', function() {
            var el = $(this).siblings('ul');
            if (el.hasClass('less')) {
                el.removeClass('less');
                $(this).html('{{ translate('Less') }} <i class="las la-angle-up"></i>');
            } else {
                el.addClass('less');
                $(this).html('{{ translate('More') }} <i class="las la-angle-down"></i>');
            }
        });
    </script>
@endsection
