<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ProductType;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ProductTypeController extends Controller
{
    //----------------GET PRODUCT TYPES---------------
    public function index(Request $request){
        $productType=ProductType::paginate($request->entries_per_page);
        $pagination = [
            'current_page' => $productType->currentPage(),
            'last_page' => $productType->lastPage(),
            'per_page' => $productType->perPage(),
            'total' => $productType->total(),
        ];
        $response['data']=$productType;
        $response['pagination']=$pagination;
        return response()->json($response, 200);
    }

    //----------------CREATE PRODUCT TYPES-----------------
    public function create(Request $request){
        $validator = Validator::make($request->all(), [
            'name' =>'required',
        ]);
        if ($validator->fails())
        {
            return response()->json(['errors'=>$validator->errors()->all()], 422);
        }
        try{ 
            $productType=new ProductType();
            $productType->name=$request->name;
            $productType->save();

            $response['message']="Successfully Created The Product Type";
            return response()->json($response, 200);

            } catch (\Exception $exception) {
            if (('APP_ENV') == 'local') {
                dd($exception);
            } else {
                $response['message'] = ['Something went wrong'];
                return response()->json($response, 404);

            }
        }
    }

    //----------------EDIT PRODUCT TYPES-----------------
    public function edit($id){
        $productType =ProductType::find($id);
        return response()->json($productType, 200);
    }

    //----------------UPDATE PRODUCT TYPES-----------------
    public function update(Request $request,$id){
        $validator = Validator::make($request->all(), [
            'name' =>'required',
        ]);
        if ($validator->fails())
        {
            return response()->json(['errors'=>$validator->errors()->all()], 422);
        }
        try{ 
            $productType=ProductType::find($id);
            $productType->name=$request->name;
            $productType->save();
            $response['message']="Successfully Updated The Product Type";
            return response()->json($response, 200);
            } catch (\Exception $exception) {
            if (('APP_ENV') == 'local') {
                dd($exception);
            } else {
                $response['message'] = ['Something went wrong'];
                return response()->json($response, 404);

            }
        }
    }

    //----------------DELETE PRODUCT TYPES-----------------
    public function delete($id){
        $productType =ProductType::find($id);
        $productType->delete();
        $response['message']="Successfully Deleted The Product Type";
        return response()->json($response, 200);
    }

    //----------------FILTER PRODUCT TYPES-----------------
    public function filterProductTypes(Request $request){
        $validator = Validator::make($request->all(), [
            'type' => 'required',
            'value' => 'required',
        ]);
        if ($validator->fails())
        {
            return response()->json(['errors'=>$validator->errors()->all()], 422);
        }
        if($request->type=='Name'){
            $types=ProductType::where('name', 'like', '%' . $request->value . '%')->get();
        }
        return response()->json($types, 200);
    }

    public function getProductTypesForDropdown(){
        $types = ProductType::get()->map(function($item) {
            return $item->name;
        });
        return response()->json($types, 200);
    }
}
