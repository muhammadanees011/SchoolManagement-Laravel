<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Models\Menu;
use App\Models\MenuItem;
use Illuminate\Validation\Rule;

class MenuItemsController extends Controller
{
    public function getItemsByMenuId($id)
    {
        $items = MenuItem::where('menu_id',$id)->get();
        return response()->json($items, 200);
    }
    
    public function addMenuItem(Request $request){
        $validator = Validator::make($request->all(), [
            'menu_id' => ['required',Rule::exists('menus', 'id')],
            'item_name' => 'required|string',
            'item_description' => 'required|string',
            'price'=>'required|numeric',
            'status'=>'required|string',
        ]);
        if ($validator->fails())
        {
            return response()->json(['errors'=>$validator->errors()->all()], 422);
        }
        try {
            DB::beginTransaction();
            $item =new MenuItem();
            $item->menu_id=$request->menu_id;
            $item->item_name=$request->item_name;
            $item->item_description=$request->item_description;
            $item->price=$request->price;
            $item->status=$request->status;
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

    public function editMenuItem($id){
        $item =MenuItem::find($id);
        return response()->json($item, 200);

    }


    public function updateMenuItem(Request $request,$id){
        $validator = Validator::make($request->all(), [
            'menu_id' => ['required',Rule::exists('menus', 'id')],
            'item_name' => 'required|string',
            'item_description' => 'required|string',
            'price'=>'required|numeric',
            'status'=>'required|string',
        ]);
        if ($validator->fails())
        {
            return response()->json(['errors'=>$validator->errors()->all()], 422);
        }
        try {
            DB::beginTransaction();
            $item =MenuItem::find($id);
            $item->menu_id=$request->menu_id;
            $item->item_name=$request->item_name;
            $item->item_description=$request->item_description;
            $item->price=$request->price;
            $item->status=$request->status;
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

    public function deleteMenuItem($id){
        $item =MenuItem::find($id);
        $item->delete();
        $response = ['Successfully Deleted Item'];
        return response()->json($response, 200);
    }
}
