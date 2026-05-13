<?php

namespace App\Http\Controllers\Seller;

use AizPackages\CombinationGenerate\Services\CombinationService;
use App\Http\Requests\ProductRequest;
use Illuminate\Http\Request;
use App\Models\AttributeValue;
use App\Models\Cart;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductTranslation;
use App\Models\Wishlist;
use App\Models\User;
use App\Notifications\ShopProductNotification;
use Artisan;
use Auth;

use App\Models\ProductStock;
use App\Services\ProductService;
use App\Services\ProductTaxService;
use App\Services\ProductFlashDealService;
use App\Services\ProductStockService;
use App\Services\FrequentlyBoughtProductService;
use Illuminate\Support\Facades\Notification;

class ProductController extends Controller
{
    protected $productService;
    protected $productCategoryService;
    protected $productTaxService;
    protected $productFlashDealService;
    protected $productStockService;
    protected $frequentlyBoughtProductService;

    public function __construct(
        ProductService $productService,
        ProductTaxService $productTaxService,
        ProductFlashDealService $productFlashDealService,
        ProductStockService $productStockService,
        FrequentlyBoughtProductService $frequentlyBoughtProductService
    ) {
        $this->productService = $productService;
        $this->productTaxService = $productTaxService;
        $this->productFlashDealService = $productFlashDealService;
        $this->productStockService = $productStockService;
        $this->frequentlyBoughtProductService = $frequentlyBoughtProductService;
    }

    public function index(Request $request)
    {
        $search = null;
        // $products = Product::where('added_by', 'admin')->orderBy('created_at', 'desc');

        $products = Product::where('user_id', Auth::user()->id)->where('digital', 0)->where('auction_product', 0)->where('wholesale_product', 0)->orderBy('created_at', 'desc');
        if ($request->has('search')) {
            $search = $request->search;
            $products = $products->where('name', 'like', '%' . $search . '%');
        }
            
        $products = $products->paginate(10);
        $products->getCollection()->transform(function ($product) {
            $product->enlisted = Product::where('user_id', auth()->id())
                                    ->where('parent_id', $product->id)
                                    ->exists();
            return $product;
        });
        $commission_percentage = get_setting('vendor_commission');
            
        $count_listed = Product::where('user_id', Auth::user()->id)->count();
        return view('seller.product.products.index', compact('products', 'search', 'count_listed', 'commission_percentage'));
    }
    public function listedProducts()
    {
        $all_products = Product::where('user_id', Auth::user()->id);
        $products = $all_products->paginate(10);
        $count_listed = $all_products->count();
        return view('seller.product.products.listed', compact('products', 'count_listed'));
    }

    public function product_warehouse()
    {
        $userId = auth()->id();

        // Get all parent_ids of products already enlisted by this user
        $alreadyEnlistedIds = Product::where('user_id', $userId)
                                    ->whereNotNull('parent_id')
                                    ->pluck('parent_id');

        // Filter admin products excluding already enlisted ones
        $all_products = Product::where('added_by', 'admin')
                            ->whereNotIn('id', $alreadyEnlistedIds);

        $commission_percentage = 0;

        if(get_setting('vendor_commission_activation')){
            $commission_percentage = get_setting('vendor_commission');
        }

        $products = $all_products->paginate(100);
        $count_listed = Category::count();

        return view(
            'seller.product.products.product_warehouse',
            compact('products', 'count_listed', 'commission_percentage')
        );
    }

    public function create(Request $request)
    {
        if (addon_is_activated('seller_subscription')) {
            if (!seller_package_validity_check()) {
                flash(translate('Please upgrade your package.'))->warning();
                return back();
            }
        }
        $categories = Category::where('parent_id', 0)
            ->where('digital', 0)
            ->with('childrenCategories')
            ->get();
        return view('seller.product.products.create', compact('categories'));
    }

    public function store(ProductRequest $request)
    {
        if (addon_is_activated('seller_subscription')) {
            if (!seller_package_validity_check()) {
                flash(translate('Please upgrade your package.'))->warning();
                return redirect()->route('seller.products');
            }
        }

        $product = $this->productService->store($request->except([
            '_token', 'sku', 'choice', 'tax_id', 'tax', 'tax_type', 'flash_deal_id', 'flash_discount', 'flash_discount_type'
        ]));
        $request->merge(['product_id' => $product->id]);

        ///Product categories
        $product->categories()->attach($request->category_ids);

        //VAT & Tax
        if ($request->tax_id) {
            $this->productTaxService->store($request->only([
                'tax_id', 'tax', 'tax_type', 'product_id'
            ]));
        }

        //Product Stock
        $this->productStockService->store($request->only([
            'colors_active', 'colors', 'choice_no', 'unit_price', 'sku', 'current_stock', 'product_id'
        ]), $product);

        // Frequently Bought Products
        $this->frequentlyBoughtProductService->store($request->only([
            'product_id', 'frequently_bought_selection_type', 'fq_bought_product_ids', 'fq_bought_product_category_id'
        ]));

        // Product Translations
        $request->merge(['lang' => env('DEFAULT_LANGUAGE')]);
        ProductTranslation::create($request->only([
            'lang', 'name', 'unit', 'description', 'product_id'
        ]));

        if (get_setting('product_approve_by_admin') == 1) {
            $users = User::findMany(User::where('user_type', 'admin')->first()->id);
            
            $data = array();
            $data['product_type']   = 'physical';
            $data['status']         = 'pending';
            $data['product']        = $product;
            $data['notification_type_id'] = get_notification_type('seller_product_upload', 'type')->id;

            Notification::send($users, new ShopProductNotification($data));
        }

        flash(translate('Product has been inserted successfully'))->success();

        Artisan::call('view:clear');
        Artisan::call('cache:clear');

        return redirect()->route('seller.products');
    }

    public function edit(Request $request, $id)
    {
        $product = Product::findOrFail($id);

        if (Auth::user()->id != $product->user_id) {
            flash(translate('This product is not yours.'))->warning();
            return back();
        }

        $lang = $request->lang;
        $tags = json_decode($product->tags);
        $categories = Category::where('parent_id', 0)
            ->where('digital', 0)
            ->with('childrenCategories')
            ->get();
        return view('seller.product.products.edit', compact('product', 'categories', 'tags', 'lang'));
    }

    public function update(ProductRequest $request, Product $product)
    {
        //Product
        $product = $this->productService->update($request->except([
            '_token', 'sku', 'choice', 'tax_id', 'tax', 'tax_type', 'flash_deal_id', 'flash_discount', 'flash_discount_type'
        ]), $product);

        $request->merge(['product_id' => $product->id]);

        //Product categories
        $product->categories()->sync($request->category_ids);

        //Product Stock
        $product->stocks()->delete();
        $this->productStockService->store($request->only([
            'colors_active', 'colors', 'choice_no', 'unit_price', 'sku', 'current_stock', 'product_id'
        ]), $product);

        //VAT & Tax
        if ($request->tax_id) {
            $product->taxes()->delete();
            $request->merge(['product_id' => $product->id]);
            $this->productTaxService->store($request->only([
                'tax_id', 'tax', 'tax_type', 'product_id'
            ]));
        }

        // Frequently Bought Products
        $product->frequently_bought_products()->delete();
        $this->frequentlyBoughtProductService->store($request->only([
            'product_id', 'frequently_bought_selection_type', 'fq_bought_product_ids', 'fq_bought_product_category_id'
        ]));
        
        // Product Translations
        ProductTranslation::updateOrCreate(
            $request->only([
                'lang', 'product_id'
            ]),
            $request->only([
                'name', 'unit', 'description'
            ])
        );


        flash(translate('Product has been updated successfully'))->success();

        Artisan::call('view:clear');
        Artisan::call('cache:clear');

        return back();
    }

    public function sku_combination(Request $request)
    {
        $options = array();
        if ($request->has('colors_active') && $request->has('colors') && count($request->colors) > 0) {
            $colors_active = 1;
            array_push($options, $request->colors);
        } else {
            $colors_active = 0;
        }

        $unit_price = $request->unit_price;
        $product_name = $request->name;

        if ($request->has('choice_no')) {
            foreach ($request->choice_no as $key => $no) {
                $name = 'choice_options_' . $no;
                $data = array();
                foreach ($request[$name] as $key => $item) {
                    array_push($data, $item);
                }
                array_push($options, $data);
            }
        }

        $combinations = (new CombinationService())->generate_combination($options);
        return view('backend.product.products.sku_combinations', compact('combinations', 'unit_price', 'colors_active', 'product_name'));
    }

    public function sku_combination_edit(Request $request)
    {
        $product = Product::findOrFail($request->id);

        $options = array();
        if ($request->has('colors_active') && $request->has('colors') && count($request->colors) > 0) {
            $colors_active = 1;
            array_push($options, $request->colors);
        } else {
            $colors_active = 0;
        }

        $product_name = $request->name;
        $unit_price = $request->unit_price;

        if ($request->has('choice_no')) {
            foreach ($request->choice_no as $key => $no) {
                $name = 'choice_options_' . $no;
                $data = array();
                foreach ($request[$name] as $key => $item) {
                    array_push($data, $item);
                }
                array_push($options, $data);
            }
        }

        $combinations = (new CombinationService())->generate_combination($options);
        return view('backend.product.products.sku_combinations_edit', compact('combinations', 'unit_price', 'colors_active', 'product_name', 'product'));
    }

    public function add_more_choice_option(Request $request)
    {
        $all_attribute_values = AttributeValue::with('attribute')->where('attribute_id', $request->attribute_id)->get();

        $html = '';

        foreach ($all_attribute_values as $row) {
            $html .= '<option value="' . $row->value . '">' . $row->value . '</option>';
        }

        echo json_encode($html);
    }

    public function updatePublished(Request $request)
    {
        $product = Product::findOrFail($request->id);
        $product->published = $request->status;
        if (addon_is_activated('seller_subscription') && $request->status == 1) {
            if (!seller_package_validity_check()) {
                return 2;
            }
        }
        $product->save();
        return 1;
    }

    public function updateFeatured(Request $request)
    {
        $product = Product::findOrFail($request->id);
        $product->seller_featured = $request->status;
        if ($product->save()) {
            Artisan::call('view:clear');
            Artisan::call('cache:clear');
            return 1;
        }
        return 0;
    }

    public function duplicate($id)
    {
        $product = Product::find($id);

        if (Auth::user()->id != $product->user_id) {
            flash(translate('This product is not yours.'))->warning();
            return back();
        }

        if (addon_is_activated('seller_subscription')) {
            if (!seller_package_validity_check()) {
                flash(translate('Please upgrade your package.'))->warning();
                return back();
            }
        }

        //Product
        $product_new = $this->productService->product_duplicate_store($product);

        //Product Stock
        $this->productStockService->product_duplicate_store($product->stocks, $product_new);

        //VAT & Tax
        $this->productTaxService->product_duplicate_store($product->taxes, $product_new);

        // Product Categories
        foreach($product->product_categories as $product_category){
            ProductCategory::insert([
                'product_id' => $product_new->id,
                'category_id' => $product_category->category_id,
            ]);
        }
        
        flash(translate('Product has been duplicated successfully'))->success();
        return redirect()->route('seller.products');
    }

    public function destroy($id)
    {
        $product = Product::findOrFail($id);

        if (Auth::user()->id != $product->user_id) {
            flash(translate('This product is not yours.'))->warning();
            return back();
        }

        $product->product_translations()->delete();
        $product->categories()->detach();
        $product->stocks()->delete();
        $product->taxes()->delete();
        $product->frequently_bought_products()->delete();
        $product->last_viewed_products()->delete();
        $product->flash_deal_products()->delete();
        deleteProductReview($product);
        if (Product::destroy($id)) {
            Cart::where('product_id', $id)->delete();
            Wishlist::where('product_id', $id)->delete();

            flash(translate('Product has been deleted successfully'))->success();

            Artisan::call('view:clear');
            Artisan::call('cache:clear');

            return back();
        } else {
            flash(translate('Something went wrong'))->error();
            return back();
        }
    }

    public function bulk_product_delete(Request $request)
    {
        if ($request->id) {
            foreach ($request->id as $product_id) {
                $this->destroy($product_id);
            }
        }

        return 1;
    }

    public function product_search(Request $request)
    {
        $products = $this->productService->product_search($request->except(['_token']));
        return view('partials.product.product_search', compact('products'));
    }

    public function get_selected_products(Request $request){
        $products = product::whereIn('id', $request->product_ids)->get();
        return  view('partials.product.frequently_bought_selected_product', compact('products'));
    }

    public function categoriesWiseProductDiscount(Request $request){
        $sort_search =null;
        $categories = Category::orderBy('order_level', 'desc');
        if ($request->has('search')){
            $sort_search = $request->search;
            $categories = $categories->where('name', 'like', '%'.$sort_search.'%');
        }
        $categories = $categories->paginate(15);
        return view('seller.product.category_wise_discount.set_discount', compact('categories', 'sort_search'));
    }
    
    public function setProductDiscount(Request $request)
    {   
        $response = $this->productService->setCategoryWiseDiscount($request->except(['_token']));
        return $response;
    }
    public function enlistProducts(Request $request)
    {
        $userId = auth()->id();
        $productIds = $request->input('product_ids'); // Should be an array of product IDs
        
        // Handle "ALL" case: get all admin products NOT already enlisted by the user
        if ($productIds === 'ALL') {
            $productIds = Product::where('added_by', 'admin')
                ->whereNotIn('id', Product::where('user_id', $userId)
                                        ->whereNotNull('parent_id')
                                        ->pluck('parent_id'))
                ->pluck('id')
                ->toArray();
        }

        if (!is_array($productIds) || empty($productIds)) {
            return response()->json([
                'status' => 'error',
                'message' => 'No product IDs provided.'
            ]);
        }

        $enlisted = [];
        $skipped = [];

        foreach ($productIds as $productId) {
            // 1. Ensure product exists and is an admin product
            $originalProduct = Product::where('id', $productId)
                                    ->where('added_by', 'admin')
                                    ->first();

            if (!$originalProduct) {
                $skipped[] = [
                    'product_id' => $productId,
                    'reason' => 'Not found or not added by admin.'
                ];
                continue;
            }

            // 2. Check if already copied by this user
            $alreadyExists = Product::where('parent_id', $productId)
                                    ->where('user_id', $userId)
                                    ->exists();

            if ($alreadyExists) {
                $skipped[] = [
                    'product_id' => $productId,
                    'reason' => 'Already enlisted.'
                ];
                continue;
            }

            // 3. Copy product
            $copiedProduct = $originalProduct->replicate();
            $copiedProduct->user_id = $userId;
            $copiedProduct->added_by = 'seller';
            $copiedProduct->approved = 0; // Set to 0 for seller products
            $copiedProduct->parent_id = $originalProduct->id;
            $copiedProduct->slug = $originalProduct->slug . '-' . $userId;

            $copiedProduct->save();

            // 4. Add stock info
            ProductStock::create([
                'product_id' => $copiedProduct->id,
                'price' => $copiedProduct->unit_price,
                'qty' => $copiedProduct->current_stock,
            ]);

            // 5. Assign category
            ProductCategory::create([
                'product_id' => $copiedProduct->id,
                'category_id' => $copiedProduct->category_id,
            ]);

            $enlisted[] = $copiedProduct->id;
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Products processed.',
            'enlisted' => $enlisted,
            'skipped' => $skipped,
        ]);
    }

    public function unlistProduct($productId)
    {
        $user = auth()->user();

        // Find the product where user owns it and it was enlisted
        $product = Product::where('id', $productId)
                        ->where('user_id', $user->id)
                        ->first();

        if (!$product) {
            return response()->json([
                'status' => 'error',
                'message' => 'Product not found or not owned by you.'
            ]);
        }

        // Delete the user-specific copy
        $product->delete();
        // Delete related ProductStock
        ProductStock::where('product_id', $product->id)->delete();

        // Delete related ProductCategory
        ProductCategory::where('product_id', $product->id)->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Product unlisted successfully.'
        ]);
    }

}
