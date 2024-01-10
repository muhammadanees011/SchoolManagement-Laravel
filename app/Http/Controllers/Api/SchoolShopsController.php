<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SchoolShop;
use App\Models\ShopItem;
use App\Models\Student;
use App\Models\Staff;
use App\Models\OrganizationAdmin;
use App\Models\User;
use App\Models\School;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;

class SchoolShopsController extends Controller
{
    //----------GET SCHOOL SHOP---------
    public function getAllSchoolShop(){
        $user=Auth::user();
        if($user->role=='super_admin'){
            $shops=SchoolShop::get();
        }else if($user->role=='organization_admin'){
            $admin=OrganizationAdmin::where('user_id',$user->id)->first();
            $schoolIds=School::where('organization_id',$admin->organization_id)->pluck('id')->toArray();
            $shops=SchoolShop::whereIn('school_id',$schoolIds)->get();
        }
        return response()->json($shops, 200);
    }
    //----------GET SHOP ITEMS---------
    public function getShopItems(){
        $user=Auth::user();
        if($user->role=='super_admin'){
            $shopItems=SchoolShop::with('shopItems.attribute')->get();
        }else if($user->role=='student'){
            $student=Student::where('user_id',$user->id)->first();
            $shopItems=SchoolShop::where('school_id',$student->school_id)->with('shopItems.attribute')->get();
        }else if($user->role=='staff'){
            $staff=Staff::where('user_id',$user->id)->first();
            $shopItems=SchoolShop::where('school_id',$staff->school_id)->with('shopItems.attribute')->get();
        }else if($user->role=='organization_admin'){
            $admin=OrganizationAdmin::where('user_id',$user->id)->first();
            $schoolIds=School::where('organization_id',$admin->organization_id)->pluck('id')->toArray();
            $shopItems=SchoolShop::whereIn('school_id',$schoolIds)->with('shopItems.attribute')->get();
        }
        return response()->json($shopItems, 200);
    }
    //-----------ADD ITEM---------------
    public function addItem(Request $request){
        $validator = Validator::make($request->all(), [
            'attribute_id' =>['nullable',Rule::exists('attributes', 'id')],
            'shop_id' =>['nullable',Rule::exists('school_shops', 'id')],
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
            $user=Auth::user();
            if($user->role=="super_admin" || $user->role=="organization_admin"){
                $shop_id=$request->shop_id;
            }else if($user->role=="staff"){
                $staff=Staff::where('user_id',$user->id)->first();
                $shop=SchoolShop::where('school_id',$staff->school_id)->first();
                $shop_id=$shop->id;
            }
            $item = new ShopItem();
            $item->shop_id=$shop_id;
            $item->attribute_id=$request->attribute_id;
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
    //----------FIND SHOP ITEM---------
    public function findShopItem($id){
        $shopItem=ShopItem::find($id);
        return response()->json($shopItem, 200);
    }
    //----------Edit SHOP ITEM---------
    public function editShopItem($id){
        $shopItem=ShopItem::find($id);
        return response()->json($shopItem, 200);
    }
    //----------UPDATE SHOP ITEM---------
    public function updateShopItem(Request $request,$id){
        $validator = Validator::make($request->all(), [
            'attribute_id' =>['nullable',Rule::exists('attributes', 'id')],
            'shop_id' =>['nullable',Rule::exists('school_shops', 'id')],
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
            $user=Auth::user();
            if($user->role=="super_admin" || $user->role=="organization_admin"){
                $shop_id=$request->shop_id;
            }else if($user->role=="staff"){
                $staff=Staff::where('user_id',$user->id)->first();
                $shop=SchoolShop::where('school_id',$staff->school_id)->first();
                $shop_id=$shop->id;
            }
            $item =ShopItem::find($id);
            $item->attribute_id=$request->attribute_id;
            $item->shop_id=$shop_id;
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
