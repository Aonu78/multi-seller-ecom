<?php

namespace App\Http\Controllers;

use App\Http\Requests\SellerRegistrationRequest;
use App\Models\Shop;
use App\Models\User;
use App\Models\BusinessSetting;
use Auth;
use Hash;
use App\Utility\EmailUtility;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Log;
class ShopController extends Controller
{

    public function __construct()
    {
        $this->middleware('user', ['only' => ['index']]);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $shop = Auth::user()->shop;
        return view('seller.shop', compact('shop'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        if (Auth::check()) {
            if ((Auth::user()->user_type == 'admin' || Auth::user()->user_type == 'customer')) {
                flash(translate('Admin or Customer cannot be a seller'))->error();
                return back();
            }
            if (Auth::user()->user_type == 'seller') {
                flash(translate('This user already a seller'))->error();
                return back();
            }
        } else {
            return view('auth.'.get_setting('authentication_layout_select').'.seller_registration');
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(SellerRegistrationRequest $request)
    {
        Log::info('Seller registration started');

        $inviter = User::where('referral_code', $request->invitation_code)->first();

        Log::info('Invitation code checked', [
            'invitation_code' => $request->invitation_code,
            'inviter_found' => $inviter ? true : false
        ]);

        if (!$inviter) {
            Log::error('Invalid invitation code');

            return back()->withErrors([
                'invitation_code' => 'Invalid invitation code.'
            ])->withInput();
        }

        $user = new User;
        $user->name = $request->name;
        $user->email = $request->email;
        $user->user_type = "seller";
        $user->password = Hash::make($request->password);

        Log::info('Saving user');

        if ($user->save()) {

            Log::info('User saved successfully', [
                'user_id' => $user->id
            ]);

            $shop = new Shop;
            $shop->user_id = $user->id;
            $shop->name = $request->shop_name;
            $shop->address = $request->address;
            $shop->slug = preg_replace('/\s+/', '-', str_replace("/", " ", $request->shop_name));

            $shop->transaction_password = $request->transaction_password;

            $shop->certificate_type = $request->certtype;
            $shop->invitation_code = $request->invitation_code;

            Log::info('Shop model prepared');

            if ($request->hasFile('identity_card_front')) {

                Log::info('Uploading identity_card_front');

                $shop->identity_card_front = $request
                    ->file('identity_card_front')
                    ->store('uploads/certificates');

                Log::info('identity_card_front uploaded', [
                    'path' => $shop->identity_card_front
                ]);
            }

            if ($request->hasFile('identity_card_back')) {

                Log::info('Uploading identity_card_back');

                $shop->identity_card_back = $request
                    ->file('identity_card_back')
                    ->store('uploads/certificates');

                Log::info('identity_card_back uploaded', [
                    'path' => $shop->identity_card_back
                ]);
            }

            Log::info('Saving shop');

            try {

                $shop->save();

                Log::info('Shop saved successfully', [
                    'shop_id' => $shop->id
                ]);

            } catch (\Throwable $e) {

                Log::error('Shop save failed', [
                    'message' => $e->getMessage(),
                    'line' => $e->getLine(),
                    'file' => $e->getFile()
                ]);

                throw $e;
            }

            auth()->login($user, false);

            Log::info('User logged in');

            $emailVerification = BusinessSetting::where('type', 'email_verification')->first();

            Log::info('Email verification setting loaded', [
                'value' => optional($emailVerification)->value
            ]);

            if ($emailVerification && $emailVerification->value == 0) {

                Log::info('Auto verifying seller email');

                $user->email_verified_at = date('Y-m-d H:i:s');
                $user->save();

            } else {

                try {

                    Log::info('Sending seller verification email');

                    EmailUtility::email_verification($user, 'seller');

                    Log::info('Seller verification email sent');

                } catch (\Throwable $th) {

                    Log::error('Seller verification email failed', [
                        'message' => $th->getMessage(),
                        'line' => $th->getLine(),
                        'file' => $th->getFile()
                    ]);

                    $shop->delete();
                    $user->delete();

                    flash(translate('Seller registration failed. Please try again later.'))->error();

                    return back();
                }
            }

            Log::info('Checking seller registration email template');

            if ((get_email_template_data('registration_email_to_seller', 'status') == 1)) {

                try {

                    Log::info('Sending registration email to seller');

                    EmailUtility::selelr_registration_email(
                        'registration_email_to_seller',
                        $user,
                        null
                    );

                    Log::info('Registration email sent to seller');

                } catch (\Exception $e) {

                    Log::error('Seller email failed', [
                        'message' => $e->getMessage()
                    ]);
                }
            }

            if ((get_email_template_data('seller_reg_email_to_admin', 'status') == 1)) {

                try {

                    Log::info('Sending seller registration email to admin');

                    EmailUtility::selelr_registration_email(
                        'seller_reg_email_to_admin',
                        $user,
                        null
                    );

                    Log::info('Seller registration email sent to admin');

                } catch (\Exception $e) {

                    Log::error('Admin email failed', [
                        'message' => $e->getMessage()
                    ]);
                }
            }

            Log::info('Seller registration completed successfully');

            flash(translate('Your Shop has been created successfully!'))->success();

            return redirect()->route('seller.shop.index');
        }

        Log::error('User save failed');

        flash(translate('Sorry! Something went wrong.'))->error();

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
        //
    }

    public function destroy($id)
    {
        //
    }
}
