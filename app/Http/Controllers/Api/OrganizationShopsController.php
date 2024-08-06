<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\OrganizationShop;
use App\Models\ShopItem;
use App\Models\PaymentPlan;
use App\Models\Student;
use App\Models\Staff;
use App\Models\OrganizationAdmin;
use App\Models\User;
use App\Models\School;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;  

class OrganizationShopsController extends Controller
{
    //----------GET SCHOOLS AND COURSES---------
    public function getAllSchoolsCourses()
    {
        $schools=School::select('id', 'title')->get()->map(function ($item) {
            return [
                'id' => $item->id,
                'name' => $item->title,
            ];
        });
        $data['schools']=$schools;
        return response()->json($data, 200);
    }
    //----------GET SCHOOL SHOP---------
    public function getAllSchoolShop(){
        $user=Auth::user();
        if($user->role=='super_admin'){
            $shops=OrganizationShop::get();
        }else if($user->role=='organization_admin'){
            $admin=OrganizationAdmin::where('user_id',$user->id)->first();
            $shops=OrganizationShop::where('organization_id',$admin->organization_id)->get();
        }
        return response()->json($shops, 200);
    }
    //----------GET SHOP ITEMS---------
    public function getShopItems(Request $request){
        $user=Auth::user();
        if($user->role=='super_admin'){
            $shopItems = OrganizationShop::with(['shopItems' => function($query) {
                $query->where('status', '!=', 'deleted');
            }, 'shopItems.payment'])->paginate(20);
        }else if($user->role=='student'){
            $student=Student::where('user_id',$user->id)->first();
            $school=School::where('id',$student->school_id)->first();
            $schoolName=$school->title;
            $shopItems = OrganizationShop::where('organization_id', $school->organization_id)
            ->with(['shopItems' => function ($query) use ($schoolName){
               $query->where('status', '!=', 'deleted')
               ->whereJsonContains('visibility_options', [['name' => 'Available to Students']])
               ->whereJsonContains('limit_colleges', [['name' => $schoolName]]);
            }, 'shopItems.payment'])
            ->paginate(20);
        }else if($user->role=='staff'){
            $staff=Staff::where('user_id',$user->id)->first();
            $school=School::where('id',$staff->school_id)->first();
            $schoolName=$school->title;
            $shopItems=OrganizationShop::where('organization_id',$school->organization_id)->with(['shopItems' => function($query) use ($schoolName){
                $query->where('status', '!=', 'deleted')
                ->whereJsonContains('visibility_options', [['name' => 'Available to Staff']])
                ->whereJsonContains('limit_colleges', [['name' => $schoolName]]);
            }, 'shopItems.payment'])->paginate(20);
        }else if($user->role=='organization_admin'){
            $admin=OrganizationAdmin::where('user_id',$user->id)->first();
            $shopItems=OrganizationShop::where('organization_id',$admin->organization_id)->with(['shopItems' => function($query) {
                $query->where('status', '!=', 'deleted');
            }, 'shopItems.payment'])->paginate(20);
        }

        $pagination = [
            'current_page' => $shopItems->currentPage(),
            'last_page' => $shopItems->lastPage(),
            'per_page' => $shopItems->perPage(),
            'total' => $shopItems->total(),
        ];
        $response['data']=$shopItems;
        $response['pagination']=$pagination;
        return response()->json($response, 200);
    }

    //-----------GET ARCHIVED ITEMS---------------
    public function getArchivedItems(Request $request)
    {
        $user=Auth::user();
        if($user->role=='super_admin'){
            $shopItems = OrganizationShop::with(['shopItems' => function($query) {
                $query->where('status', 'deleted');
            }, 'shopItems.attribute'])->paginate(20);
        }else if($user->role=='staff'){
            $staff=Staff::where('user_id',$user->id)->first();
            $school=School::where('id',$staff->school_id)->first();
            $shopItems=OrganizationShop::where('organization_id',$school->organization_id)->with(['shopItems' => function($query) {
                $query->where('status', 'deleted');
            }, 'shopItems.attribute'])->paginate(20);
        }else if($user->role=='organization_admin'){
            $admin=OrganizationAdmin::where('user_id',$user->id)->first();
            $shopItems=OrganizationShop::where('organization_id',$admin->organization_id)->with(['shopItems' => function($query) {
                $query->where('status', 'deleted');
            }, 'shopItems.attribute'])->paginate(20);
        }

        $pagination = [
        'current_page' => $shopItems->currentPage(),
        'last_page' => $shopItems->lastPage(),
        'per_page' => $shopItems->perPage(),
        'total' => $shopItems->total(),
        ];
        $response['data']=$shopItems;
        $response['pagination']=$pagination;
        return response()->json($response, 200);
    }

    //-----------ADD ITEM---------------
    public function addItem(Request $request){
        $validator = Validator::make($request->all(), [
            // 'attribute_id' =>['nullable',Rule::exists('attributes', 'id')],
            'shop_id' =>['nullable',Rule::exists('organization_shops', 'id')],
            // 'attributes' => ['nullable', 'array', Rule::exists('attributes', 'id')],
            'name' => 'required|string',
            'product_type' => 'required',
            'image' => 'nullable',
            'detail' => 'required|string',
            'price' => 'required|numeric',
            'quantity'=>'required|numeric',
            'valid_from' => 'required',
            'valid_to' => 'required',
            'payment_plan' => 'required',
            'limitColleges'=>'nullable',
            'limitCourses'=>'nullable',
            'visibility_options'=>'nullable',
            'installmentsAndDeposit'=>'nullable'
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
                $school=School::where('id',$staff->school_id)->first();                
                $shop=OrganizationShop::where('organization_id',$school->organization_id)->first();
                $shop_id=$shop->id;
            }
            $item = new ShopItem();
            $item->shop_id = $shop_id;
            // $item->attribute_id = $request->attribute_id;
            // $item->attributes = $request["attributes"];
            $item->name = $request->name;
            $item->product_type = $request->product_type;
            $item->detail = $request->detail;
            $item->price = $request->price;
            $item->quantity = $request->quantity;
            $item->status = $request->quantity > 0 ? 'available' : 'not_available';
            $item->valid_from = $request->valid_from;
            $item->valid_to = $request->valid_to;
            $item->payment_plan = $request->payment_plan;
            $item->limit_colleges = json_decode($request->input('limitColleges'), true);
            $item->limit_courses = json_decode($request->input('limitCourses'), true);
            $item->visibility_options = json_decode($request->input('visibility_options'), true);

                if($request->file('image')){
                    $path = $request->file('image')->store('uploads', 'public');
                    $item->image=Storage::url($path);
                }
            $item->save();

            if($request->payment_plan=='installments' || $request->payment_plan=='installments_and_deposit'){
                $paymentPlan = new PaymentPlan();
                $paymentPlan->shop_item_id = $item->id;
                $installmentsAndDeposit = json_decode($request->input('installmentsAndDeposit'), true);
                $paymentPlan->total_installments = $installmentsAndDeposit['total_installments'];
                $paymentPlan->amount_per_installment = $installmentsAndDeposit['amount_per_installment'];
                $paymentPlan->initial_deposit_installments = $installmentsAndDeposit['initial_deposit'];
                $paymentPlan->initial_deposit_deadline_installments = $installmentsAndDeposit['initial_deposit_due_date'];
                $paymentPlan->other_installments_deadline_installments = $installmentsAndDeposit['other_installments_due_date'];
                $paymentPlan->save();
            }
            DB::commit();
            $response = ['Successfully Created Item'];
            return response()->json($response, 200);
        } catch (\Exception $exception) {
            DB::rollback();
            if (('APP_ENV') == 'local') {
                return response()->json($exception, 500);
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
        $shopItem=ShopItem::with('payment')->find($id);

        // if (isset($shopItem->limit_colleges) && is_array($shopItem->limit_colleges)) {
        //     // Transform the visibility_options array
        //     $shopItem->limit_colleges = array_map(function ($option) {
        //         return ['name' => $option];
        //     }, $shopItem->limit_colleges);
        // }

        // if (isset($shopItem->visibility_options) && is_array($shopItem->visibility_options)) {
        //     // Transform the visibility_options array
        //     $shopItem->visibility_options = array_map(function ($option) {
        //         return ['name' => $option];
        //     }, $shopItem->visibility_options);
        // }

        return response()->json($shopItem, 200);
    }
    //----------UPDATE SHOP ITEM---------
    public function updateShopItem(Request $request,$id){
        $validator = Validator::make($request->all(), [
            // 'attribute_id' =>['nullable',Rule::exists('attributes', 'id')],
            'shop_id' =>['nullable',Rule::exists('organization_shops', 'id')],
            // 'attributes' => ['nullable', 'array', Rule::exists('attributes', 'id')],
            'name' => 'required|string',
            'image' => 'nullable',
            'product_type' => 'required',
            'detail' => 'required|string',
            'price' => 'required|numeric',
            'quantity'=>'required|numeric',
            'valid_from' => 'required',
            'valid_to' => 'required',
            'payment_plan' => 'required',
            'limitColleges'=>'nullable',
            'limitCourses'=>'nullable',
            'visibility_options'=>'nullable',
            'installmentsAndDeposit'=>'nullable'
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
                $school=School::where('id',$staff->school_id)->first();                
                $shop=OrganizationShop::where('organization_id',$school->organization_id)->first();
                $shop_id=$shop->id;
            }
            $item =ShopItem::find($id);
            // $item->attribute_id=$request->attribute_id;
            $item->shop_id=$shop_id;
            // $item->attributes =$request["attributes"];
            $item->name=$request->name;
            $item->product_type = $request->product_type;
            $item->detail=$request->detail;
            $item->price=$request->price;
            $item->quantity=$request->quantity;
            $item->status = $request->quantity > 0 ? 'available' : 'not_available';
            $item->valid_from=$request->valid_from;
            $item->valid_to=$request->valid_to;
            $item->payment_plan=$request->payment_plan;
            $item->limit_colleges=json_decode($request->input('limitColleges'), true);
            $item->limit_courses=json_decode($request->input('limitCourses'), true);
            $item->visibility_options=json_decode($request->input('visibility_options'), true);
            if($request->file('image')){
                //remove the image if it already exists
                if ($item->image) {
                    $lastSlashPosition = strrpos($item->image, '/');
                    if ($lastSlashPosition !== false) {
                        $fileName = substr($item->image, $lastSlashPosition + 1);
                        unlink(storage_path('app/public/uploads/'. $fileName));
                    }
                }
                $path = $request->file('image')->store('uploads', 'public');
                $item->image=Storage::url($path);
            }
            $item->save();

            if($request->payment_plan=='installments' || $request->payment_plan=='installments_and_deposit'){
                $paymentPlan = new PaymentPlan();
                $paymentPlan->shop_item_id = $item->id;
                $installmentsAndDeposit = json_decode($request->input('installmentsAndDeposit'), true);
                $paymentPlan->total_installments = $installmentsAndDeposit['total_installments'];
                $paymentPlan->amount_per_installment = $installmentsAndDeposit['amount_per_installment'];
                $paymentPlan->initial_deposit_installments = $installmentsAndDeposit['initial_deposit'];
                $paymentPlan->initial_deposit_deadline_installments = $installmentsAndDeposit['initial_deposit_due_date'];
                $paymentPlan->other_installments_deadline_installments = $installmentsAndDeposit['other_installments_due_date'];
                $paymentPlan->save();
            }

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
        // $shopItem->delete();
        $shopItem->status='deleted';
        $shopItem->save();
        $response = ['Successfully Deleted Item'];
        return response()->json($response, 200);
    }

    //----------BULK Delete ARCHIVED ITEM---------
    public function bulkDeleteItems(Request $request){
        $ids = $request->all();
        ShopItem::whereIn('id', $ids)->delete();
        $response = ['Successfully Deleted Items'];
        return response()->json($response, 200);
    }

    //----------BULK RESTORE ARCHIVED ITEM---------
    public function bulkRestoreItems(Request $request){
        $ids = $request->all();
        foreach ($ids as $record) {
            $item=ShopItem::where('id',$record)->first();
            $item->status='available';
            $item->save();
        }
        $response = ['Successfully Restored Items'];
        return response()->json($response, 200);
    }
}
