<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SchoolShop;
use App\Models\ShopItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class SchoolShopsController extends Controller
{
    //----------GET SCHOOL SHOP---------
    public function getSchoolShop(){
        $school_id=Auth::user()->id;
        $shop=SchoolShop::where('school_id',$school_id)->get();
        return response()->json($shop, 200);
    }
    //----------GET SHOP ITEMS---------
    public function getShopItems(){
        $school_id=Auth::user()->id;
        $shopItems=SchoolShop::with('shopItems')->first();
        return response()->json($shopItems, 200);
    }
    //-----------ADD ITEM---------------
    public function addItem(Request $request){
        $school_id=Auth::user()->id;
        $shop=SchoolShop::where('school_id',$school_id)->first();

        $validator = Validator::make($request->all(), [
            'name' => 'required|string',
            'detail' => 'required|string',
            'price' => 'required|numeric',
            'quantity'=>'required|numeric',
        ]);
        if ($validator->fails())
        {
            return response()->json(['errors'=>$validator->errors()->all()], 422);
        }
        try {
            DB::beginTransaction();
            $item = new ShopItem();
            $item->shop_id=$shop->id;
            $item->name=$request->name;
            $item->detail=$request->detail;
            $item->price=$request->price;
            $item->quantity=$request->quantity;
            $item->save();
            DB::commit();
            $response = ['Successfully Created Item'];
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
    //----------Edit SHOP ITEM---------
    public function editShopItem($id){
        $shopItem=ShopItem::find($id);
        return response()->json($shopItem, 200);
    }
    //----------UPDATE SHOP ITEM---------
    public function updateShopItem(Request $request,$id){
        $validator = Validator::make($request->all(), [
            'name' => 'required|string',
            'detail' => 'required|string',
            'price' => 'required|numeric',
            'quantity'=>'required|numeric',
        ]);
        if ($validator->fails())
        {
            return response()->json(['errors'=>$validator->errors()->all()], 422);
        }
        try {
            DB::beginTransaction();
            $item =ShopItem::find($id);
            $item->name=$request->name;
            $item->detail=$request->detail;
            $item->price=$request->price;
            $item->quantity=$request->quantity;
            $item->save();
            DB::commit();
            $response = ['Successfully Updated Item'];
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
    //----------Delete SHOP ITEM---------
    public function deleteShopItem($id){
        $shopItem=ShopItem::find($id);
        $shopItem->delete();
        $response = ['Successfully Deleted Item'];
        return response()->json($response, 200);
    }
}
