<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\UserCart;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;

class UserCartController extends Controller
{
    //-------------ADD ITEM TO CART------------------
    public function addItemToCart(Request $request)
    {
        try {
            DB::beginTransaction();
            $user=Auth::user();
            $cartItem=new UserCart();
            $cartItem->user_id=$user->id;
            $cartItem->shop_item_id=$request->shop_item_id;
            $cartItem->save();
            DB::commit();
            $response = ['Successfully Added Item To The Cart'];
            return response()->json($response, 200);
        } catch (\Exception $exception) {
            DB::rollback();
            if (('APP_ENV') == 'local') {
                dd($exception);
            } else {
                return response()->json($exception, 500);
            }
        }
    }
    //-------------GET CART ITEMS------------------
    public function getUserCartItems(){
        try {
            $user=Auth::user();
            $cartItems=UserCart::with('ShopItem')->where('user_id',$user->id)->orderBy('created_at', 'desc')->get();
            return response()->json($cartItems, 200);
        } catch (\Exception $exception) {
            DB::rollback();
            if (('APP_ENV') == 'local') {
                dd($exception);
            } else {
                return response()->json($exception, 500);
            }
        }
    }
    //------------REMOVE ITEM FROM CART--------------
    public function removeItemFromCart(Request $request)
    {
        try {
            $user=Auth::user();
            $cartItem=UserCart::where('id',$request->item_id)->where('user_id',$user->id)->first();
            $cartItem->delete();
            $response = ['Removed Item Successfully'];
            return response()->json($response, 200);
        } catch (\Exception $exception) {
            DB::rollback();
            if (('APP_ENV') == 'local') {
                dd($exception);
            } else {
                return response()->json($exception, 500);
            }
        }
    }
}
