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

class MenusController extends Controller
{
    public function getMenusBySchoolId($id){
        $menus =Menu::where('school_id',$id)->get();
        return response()->json($menus, 200);
    }

    public function addMenu(Request $request){
        $validator = Validator::make($request->all(), [
            'school_id' => ['required',Rule::exists('schools', 'id')],
            'name' => 'required|string',
            'description' => 'required|string',
            'menu_date'=>'required|date',
            'type'=>'required|string',
            'status'=>'required|string',
        ]);
        if ($validator->fails())
        {
            return response()->json(['errors'=>$validator->errors()->all()], 422);
        }
        try {
            DB::beginTransaction();
            $item =new Menu();
            $item->school_id=$request->school_id;
            $item->name=$request->name;
            $item->description=$request->description;
            $item->menu_date=$request->menu_date;
            $item->type=$request->type;
            $item->status=$request->status;
            $item->save();
            DB::commit();
            $response = ['Successfully Created Menu'];
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

    public function editMenu($id){
        $item =Menu::find($id);
        return response()->json($item, 200);
    }

    public function updateMenu(Request $request,$id){
        $validator = Validator::make($request->all(), [
            'school_id' => ['required',Rule::exists('schools', 'id')],
            'name' => 'required|string',
            'description' => 'required|string',
            'menu_date'=>'required|date',
            'type'=>'required|string',
            'status'=>'required|string',
        ]);
        if ($validator->fails())
        {
            return response()->json(['errors'=>$validator->errors()->all()], 422);
        }
        try {
            DB::beginTransaction();
            $menu =Menu::find($id);
            $menu->school_id=$request->school_id;
            $menu->name=$request->name;
            $menu->description=$request->description;
            $menu->menu_date=$request->menu_date;
            $menu->type=$request->type;
            $menu->status=$request->status;
            $menu->save();
            DB::commit();
            $response = ['Successfully Updated Menu'];
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


    public function deleteMenu($id){
        $item =Menu::find($id);
        $item->delete();
        $response = ['Successfully Deleted Menu'];
        return response()->json($response, 200); 
    }

}
