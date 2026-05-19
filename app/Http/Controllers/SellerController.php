<?php

namespace App\Http\Controllers;

use App\Models\Addon;
use App\Models\Cart;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Shop;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Wishlist;
use Illuminate\Support\Facades\Hash;
use App\Notifications\ShopVerificationNotification;
use App\Services\PreorderService;
use App\Utility\EmailUtility;
use Cache;
use Illuminate\Support\Facades\Notification;

class SellerController extends Controller
{
    public function __construct()
    {
        // Staff Permission Check
        // $this->middleware(['permission:view_all_seller|view_all_seller_rating_and_followers'])->only('index');
        // $this->middleware(['permission:add_seller'])->only('create');
        // $this->middleware(['permission:view_seller_profile'])->only('profile_modal');
        // $this->middleware(['permission:login_as_seller'])->only('login');
        // $this->middleware(['permission:pay_to_seller'])->only('payment_modal');
        // $this->middleware(['permission:edit_seller'])->only('edit');
        // $this->middleware(['permission:delete_seller'])->only('destroy');
        // $this->middleware(['permission:ban_seller'])->only('ban');
        // $this->middleware(['permission:edit_seller_custom_followers'])->only('editSellerCustomFollowers');
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function all_index(Request $request)
    {
        $sort_search = $request->search ?? null;
        $approved = $request->approved_status ?? null;
        $verification_status = $request->verification_status ?? null;
    
        $shops = Shop::with(['user'])
            ->withCount(['products'])  // 👈 add this
            ->join('users', 'users.id', '=', 'shops.user_id')
            ->where('users.user_type', 'seller')
            ->select('shops.*')
            ->latest();
    
        if ($sort_search || $verification_status) {
    
            $userQuery = User::where('user_type', 'seller');
    
            if ($sort_search) {
                $userQuery->where(function ($q) use ($sort_search) {
                    $q->where('name', 'like', "%{$sort_search}%")
                      ->orWhere('email', 'like', "%{$sort_search}%");
                });
            }
    
            if ($verification_status) {
                if ($verification_status == 'verified') {
                    $userQuery->whereNotNull('email_verified_at');
                } else {
                    $userQuery->whereNull('email_verified_at');
                }
            }
    
            $shops->whereIn('shops.user_id', $userQuery->select('id'));
        }
    
        if ($approved !== null && $approved !== '') {
            $shops->where('shops.verification_status', $approved);
        }
    
        $shops = $shops->paginate(15);
    
        return view('backend.sellers.index', compact(
            'shops',
            'sort_search',
            'approved',
            'verification_status'
        ));
    }

    public function index(Request $request)
    {
        $sort_search = $request->search ?? null;
        $approved = $request->approved_status ?? null;
        $verification_status = $request->verification_status ?? null;
    
        $shops = Shop::with(['user'])
                ->withExists(['products as has_products'])
                ->join('users', 'users.id', '=', 'shops.user_id')
                ->where('users.user_type', 'seller')
                ->select('shops.*')
                ->latest('shops.created_at');
    
        if ($sort_search != null || $verification_status != null) {
    
            $user_ids = User::where('user_type', 'seller');
    
            if ($sort_search != null) {
                $user_ids->where(function ($user) use ($sort_search) {
                    $user->where('name', 'like', "%$sort_search%")
                         ->orWhere('email', 'like', "%$sort_search%");
                });
            }
    
            if ($verification_status != null) {
                $user_ids = $verification_status == 'verified'
                    ? $user_ids->whereNotNull('email_verified_at')
                    : $user_ids->whereNull('email_verified_at');
            }
    
            $shops->whereIn('user_id', $user_ids->select('id'));
        }
    
        if ($approved != null) {
            $shops->where('verification_status', $approved);
        }
    
        $shops = $shops->paginate(15);
    
        return view('backend.sellers.index', compact(
            'shops',
            'sort_search',
            'approved',
            'verification_status'
        ));
    }
    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('backend.sellers.create');
    }
    public function add_funds($encrypted_id)
    {
        $shopId = decrypt($encrypted_id);

        $shop = Shop::findOrFail($shopId);
        $user = $shop->user;

        $balance = $user->balance;

        return view('backend.sellers.add_funds', compact('shop', 'user', 'balance'));
    }
    public function subtract_funds(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'amount' => 'required|numeric|min:0.01',
        ]);

        $user = User::findOrFail($request->user_id);

        if ($user->balance < $request->amount) {
            return response()->json([
                'status' => 'error',
                'message' => 'Insufficient balance.'
            ]);
        }

        $user->balance -= $request->amount;
        $user->save();

        return response()->json([
            'status' => 'success',
            'new_balance' => number_format($user->balance, 2),
            'message' => 'Funds subtracted successfully.'
        ]);
    }

    public function add_funds_store(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'amount' => 'required|numeric|min:0.01'
        ]);

        $user = User::findOrFail($request->user_id);
        $user->balance += $request->amount; // Use seller_balance for consistency
        $user->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Funds added successfully.',
            'new_balance' => number_format($user->balance, 2)
        ]);
    }


    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|max:255',
            'email' => 'required|email|unique:users',
            'shop_name' => 'max:200',
            'address' => 'max:500',
        ],
        [
            'name.required' => translate('Name is required'),
            'name.max' => translate('Max 255 Character'),
            'email.required' => translate('Email is required'),
            'email.email' => translate('Email must be a valid email address'),
            'email.unique' => translate('An user exists with this email'),
            'shop_name.max' => translate('Max 200 Character'),
            'address.max' => translate('Max 255 Character'),
        ]);


        if (User::where('email', $request->email)->first() != null) {
            flash(translate('Email already exists!'))->error();
            return back();
        }
        $password = substr(hash('sha512', rand()), 0, 8);

        $user           = new User;
        $user->name     = $request->name;
        $user->email    = $request->email;
        $user->user_type= "seller";
        $user->password = Hash::make($password);

        if ($user->save()) {
            $shop           = new Shop;
            $shop->user_id  = $user->id;
            $shop->name     = $request->shop_name;
            $shop->address  = $request->address;
            $shop->slug     = 'demo-shop-' . $user->id;
            $shop->save();

            try {
                EmailUtility::selelr_registration_email('registration_from_system_email_to_seller', $user, $password);
            } catch (\Exception $e) {
                $shop->delete();
                $user->delete();
                flash(translate('Registration failed. Please try again later.'))->error();
                return back();
            }

            // Verification email send
            if (get_setting('email_verification') != 1) {
                $user->email_verified_at = date('Y-m-d H:m:s');
                $user->save();
            } else {
                EmailUtility::email_verification($user, 'seller');
            }

            // Seller Account Opening Email to Admin
            if ((get_email_template_data('seller_reg_email_to_admin', 'status') == 1)) {
                try {
                    EmailUtility::selelr_registration_email('seller_reg_email_to_admin', $user, null);
                } catch (\Exception $e) {}
            }

            flash(translate('Seller has been added successfully'))->success();
            return back();

        }
        flash(translate('Something went wrong'))->error();
        return back();
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $shop = Shop::findOrFail(decrypt($id));
        return view('backend.sellers.edit', compact('shop'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $shop = Shop::findOrFail($id);
        $user = $shop->user;
        $user->name = $request->name;
        $user->email = $request->email;
        if (strlen($request->password) > 0) {
            $user->password = Hash::make($request->password);
        }
        if ($user->save()) {
            if ($shop->save()) {
                flash(translate('Seller has been updated successfully'))->success();
                return redirect()->route('sellers.index');
            }
        }

        flash(translate('Something went wrong'))->error();
        return back();
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $shop = Shop::findOrFail($id);

        // Seller Product and product related data delete
        $products = $shop->user->products;
        foreach($products as $product){
            $product_id = $product->id;
            $product->product_translations()->delete();
            $product->categories()->detach();
            $product->stocks()->delete();
            $product->taxes()->delete();
            $product->frequently_bought_products()->delete();
            $product->last_viewed_products()->delete();
            $product->flash_deal_products()->delete();

            if ($product->delete()) {
                Cart::where('product_id', $product_id)->delete();
                Wishlist::where('product_id', $product_id)->delete();
            }
        }

        $orders = Order::where('user_id', $shop->user_id)->get();

        foreach ($orders as $key => $order) {
            OrderDetail::where('order_id', $order->id)->delete();
        }
        Order::where('user_id', $shop->user_id)->delete();

        // If Preorder addon is installed, delete preorder products and related data.
        // if(Addon::where('unique_identifier', 'preorder')->first()){
        //     $preorderProducts = $shop->user->preorderProducts;
        //     foreach($preorderProducts as $preorderProduct){
        //         (new PreorderService)->productdestroy($preorderProduct->id);
        //     }
        // }

        User::destroy($shop->user->id);

        if (Shop::destroy($id)) {
            flash(translate('Seller has been deleted successfully'))->success();
            return redirect()->route('sellers.index');
        } else {
            flash(translate('Something went wrong'))->error();
            return back();
        }
    }

    public function bulk_seller_delete(Request $request)
    {
        if ($request->id) {
            foreach ($request->id as $shop_id) {
                $this->destroy($shop_id);
            }
        }

        return 1;
    }

    public function show_verification_request($id)
    {
        $shop = Shop::findOrFail($id);
        return view('backend.sellers.verification', compact('shop'));
    }

    public function approve_seller($id)
    {
        $shop = Shop::findOrFail($id);
        $shop->verification_status = 1;
        $shop->save();
        Cache::forget('verified_sellers_id');

        $users = User::findMany([$shop->user->id]);
        $data = array();
        $data['shop'] = $shop;
        $data['status'] = 'approved';
        $data['notification_type_id'] = get_notification_type('shop_verify_request_approved', 'type')->id;
        Notification::send($users, new ShopVerificationNotification($data));

        flash(translate('Seller has been approved successfully'))->success();
        return redirect()->route('sellers.index');
    }

    public function reject_seller($id)
    {
        $shop = Shop::findOrFail($id);
        $shop->verification_status = 0;
        $shop->verification_info = null;
        $shop->save();
        Cache::forget('verified_sellers_id');

        $users = User::findMany([$shop->user->id]);
        $data = array();
        $data['shop'] = $shop;
        $data['status'] = 'rejected';
        $data['notification_type_id'] = get_notification_type('shop_verify_request_rejected', 'type')->id;
        Notification::send($users, new ShopVerificationNotification($data));

        flash(translate('Seller verification request has been rejected successfully'))->success();
        return redirect()->route('sellers.index');
    }


    public function payment_modal(Request $request)
    {
        $shop = shop::findOrFail($request->id);
        return view('backend.sellers.payment_modal', compact('shop'));
    }

    public function profile_modal(Request $request)
    {
        $shop = Shop::findOrFail($request->id);
        return view('backend.sellers.profile_modal', compact('shop'));
    }

    public function updateApproved(Request $request)
    {
        $shop = Shop::findOrFail($request->id);
        $shop->verification_status = $request->status;
        $shop->save();
        Cache::forget('verified_sellers_id');

        $status = $request->status == 1 ? 'approved' : 'rejected';
        $users = User::findMany([$shop->user->id]);
        $data = array();
        $data['shop'] = $shop;
        $data['status'] = $status;
        $data['notification_type_id'] = $status == 'approved' ? 
                                        get_notification_type('shop_verify_request_approved', 'type')->id : 
                                        get_notification_type('shop_verify_request_rejected', 'type')->id;

        Notification::send($users, new ShopVerificationNotification($data));
        return 1;
    }

    public function login($id)
    {
        $shop = Shop::findOrFail(decrypt($id));
        $user  = $shop->user;
        auth()->login($user, true);

        return redirect()->route('seller.dashboard');
    }

    public function ban($id)
    {
        $shop = Shop::findOrFail($id);

        if ($shop->user->banned == 1) {
            $shop->user->banned = 0;
            if ($shop->verification_info) {
                $shop->verification_status = 1;
            }
            flash(translate('Seller has been unbanned successfully'))->success();
        } else {
            $shop->user->banned = 1;
            $shop->verification_status = 0;
            flash(translate('Seller has been banned successfully'))->success();
        }
        $shop->save();
        $shop->user->save();
        return back();
    }

    // Seller Based Commission
    public function setSellerBasedCommission(Request $request){
        if($request->seller_ids != null){
            foreach (explode(",",$request->seller_ids) as $shop) {
                $shop = Shop::where('id', $shop)->first();
                $shop->commission_percentage = $request->commission_percentage;
                $shop->save();
            }
            flash(translate('Seller commission is added successfully.'))->success();
        }
        else{
            flash(translate('Something went wrong!.'))->warning();
        }
        return back();
    }

    // Edit Seller Custom Followers
    public function editSellerCustomFollowers(Request $request) {
        $shop = Shop::where('id', $request->shop_id)->first();
        $shop->custom_followers = $request->custom_followers;
        $shop->save();
        flash(translate('Seller custom follower has been updated successfully.'))->success();
        return back();
    }
    
    public function showAddFundForm($id)
    {
        $shopId = decrypt($id);
        $shop = Shop::findOrFail($shopId);
        return view('backend.sellers.add_fund_form', compact('shop'));
    }

    public function storeFund(Request $request, $id)
    {
        $request->validate([
            'guarantee_money' => 'required|numeric|min:0'
        ]);

        $shopId = decrypt($id);
        $shop = Shop::findOrFail($shopId);
        $shop->guarantee_money = $request->guarantee_money;
        $shop->save();

        return redirect()->back()->with('success', 'Guarantee money updated successfully.');
    }

    public function editViews($id)
    {
        $shop = Shop::findOrFail($id);
        return view('backend.sellers.edit_views', compact('shop'));
    }


    public function updateViews(Request $request)
    {
        $request->validate([
            'shop_id' => 'required|exists:shops,id',
            'no_of_views' => 'required|integer|min:0',
            'created_at' => 'nullable|date',
        ]);

        $shop = Shop::findOrFail($request->shop_id);
        $shop->no_of_views = $request->no_of_views;
        if ($request->filled('created_at')) {
            $shop->created_at = $request->created_at;
        }
        $shop->save();

        return redirect()->back()->with('success', 'View count updated successfully.');
    }


}
