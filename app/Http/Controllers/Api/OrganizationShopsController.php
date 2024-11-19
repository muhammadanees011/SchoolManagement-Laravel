<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\OrganizationShop;
use App\Models\ShopItem;
use App\Models\PaymentPlan;
use App\Models\Student;
use App\Models\StudentCourse;
use App\Models\Staff;
use App\Models\OrganizationAdmin;
use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use App\Models\RoleHasPermission;
use App\Models\School;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage; 
use Illuminate\Support\Facades\Mail;
use Illuminate\Mail\Mailable; 
use App\Services\MicrosoftGraphService;

class OrganizationShopsController extends Controller
{
    //----------GET SCHOOLS AND COURSES---------
    public function getAllSchoolsCourses()
    {
        $schools=School::where('status','active')->select('id', 'title')->get()->map(function ($item) {
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
        if($user->role!=='student' && $user->role!=='staff' && $user->role!=='parent'){
            $shops=OrganizationShop::get();
        }
        return response()->json($shops, 200);
    }

    //----------GET SHOP ITEMS---------
    public function getShopItems(Request $request){
        $user=Auth::user();
        if($user->role!=='staff' && $user->role!=='student' && $user->role!=='parent'){

            $permission=Permission::where('name','view_products')->first();
            $role=Role::where('name',$user->role)->first();
            return [$permission,$role];
            $role_has_permission=RoleHasPermission::where('permission_id',$permission->id)->where('role_id',$role->id)->first();
            if($role_has_permission){ 
                $shopItems = OrganizationShop::with(['shopItems' => function($query) {
                    $query->where('status', '!=', 'deleted')->orderBy('created_at', 'desc');
                }, 'shopItems.payment'])->paginate($request->entries_per_page);
            }else{
                $shopItems = OrganizationShop::with(['shopItems' => function($query)use($user) {
                    $query->where('status', '!=', 'deleted')
                    ->where('created_by',$user->id)->orderBy('created_at', 'desc');
                }, 'shopItems.payment'])->paginate($request->entries_per_page);
            }

        }else if($user->role=='student'){
            $student=Student::where('user_id',$user->id)->first();
            $school=School::where('id',$student->school_id)->first();
            $courses=StudentCourse::where('StudentID',$student->student_id)->get();
            if ($courses->isEmpty()) {
                return response()->json([]);
            }
            $courseCodes = $courses->map(function ($course) {
                return $course->CourseCode . '-' . $course->CourseDescription.'';
            })->toArray();
            $schoolName=$school->title;
            $shopItems = OrganizationShop::where('organization_id', $school->organization_id)
            ->with(['shopItems' => function ($query) use ($schoolName, $courseCodes){
               $query->where('status', '!=', 'deleted')
               ->whereJsonContains('visibility_options', [['name' => 'Available to Students']])
               ->whereJsonContains('limit_colleges', [['name' => $schoolName]])
                ->where(function ($q) use ($courseCodes) {
                    $q->where(function ($subQuery) use ($courseCodes) {
                        foreach ($courseCodes as $courseCode) {
                            $subQuery->orWhereJsonContains('limit_courses', [['name' => $courseCode]]);
                        }
                    });
                })
               ->orderBy('created_at', 'desc');
            }, 'shopItems.payment'])
            ->paginate($request->entries_per_page);
        }else if($user->role=='staff'){
            $staff=Staff::where('user_id',$user->id)->first();
            $school=School::where('id',$staff->school_id)->first();
            $schoolName=$school->title;
            $shopItems=OrganizationShop::where('organization_id',$school->organization_id)->with(['shopItems' => function($query) use ($schoolName){
                $query->where('status', '!=', 'deleted')
                ->whereJsonContains('visibility_options', [['name' => 'Available to Staff']])
                ->whereJsonContains('limit_colleges', [['name' => $schoolName]])
                ->orderBy('created_at', 'desc');
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

    public function filterShopItems(Request $request){

        if($request->type=='Name'){
            $shopItems = OrganizationShop::with(['shopItems' => function($query) use ($request) {
                $query->where('status', $request->status)
                ->where('name', 'like', '%' . $request->value . '%');
            }, 'shopItems.payment'])->get();
        }else if($request->type=='Price'){
            $shopItems = OrganizationShop::with(['shopItems' => function($query) use ($request) {
                $query->where('status', $request->status)
                ->where('price', 'like', '%' . $request->value . '%');
            }, 'shopItems.payment'])->get();
        }else if($request->type=='Quantity'){
            $shopItems = OrganizationShop::with(['shopItems' => function($query) use ($request) {
                $query->where('status', $request->status)
                ->where('quantity', 'like', '%' . $request->value . '%');
            }, 'shopItems.payment'])->get();
        }
        return response()->json($shopItems, 200);
    }

    //-----------GET ARCHIVED ITEMS---------------
    public function getArchivedItems(Request $request)
    {
        $user=Auth::user();
        if($user->role!=='staff' && $user->role!=='student' && $user->role!=='parent'){

            $permission=Permission::where('name','view_products')->first();
            $role=Role::where('name',$user->role)->first();
            $role_has_permission=RoleHasPermission::where('permission_id',$permission->id)->where('role_id',$role->id)->first();

            if($role_has_permission){ 
                $shopItems = OrganizationShop::with(['shopItems' => function($query) {
                    $query->where('status','deleted')->orderBy('created_at', 'desc');
                }, 'shopItems.payment'])->paginate($request->entries_per_page);
            }else{
                $shopItems = OrganizationShop::with(['shopItems' => function($query)use($user) {
                    $query->where('status','deleted')
                    ->where('created_by',$user->id)->orderBy('created_at', 'desc');
                }, 'shopItems.payment'])->paginate($request->entries_per_page);
            }

        }else if($user->role=='staff'){
            $staff=Staff::where('user_id',$user->id)->first();
            $school=School::where('id',$staff->school_id)->first();
            $shopItems=OrganizationShop::where('organization_id',$school->organization_id)->with(['shopItems' => function($query) {
                $query->where('status', 'deleted');
            }, 'shopItems.attribute'])->paginate($request->entries_per_page);
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
            'shop_id' =>['nullable',Rule::exists('organization_shops', 'id')],
            'name' => 'required|string|max:255',
            'product_type' => 'required',
            'image' => 'nullable',
            'detail' => 'required|string|max:255',
            'price' => 'required|numeric',
            'quantity'=>'nullable|numeric',
            'valid_from' => 'required',
            'valid_to' => 'required',
            'payment_plan' => 'required',
            'limitColleges'=>'nullable',
            'limitCourses'=>'nullable',
            'visibility_options'=>'nullable',
            'installmentsAndDeposit'=>'nullable',
            'product_owner_email'=>'nullable'
        ]);
        if ($validator->fails())
        {
            return response()->json(['errors'=>$validator->errors()->all()], 422);
        }
        // try {
        //     DB::beginTransaction();
            $user=Auth::user();               
            $shop=OrganizationShop::first();
            $shop_id=$shop->id;
            $item = new ShopItem();
            $item->shop_id = $shop_id;
            $item->name = $request->name;
            $item->product_type = $request->product_type;
            $item->detail = $request->detail;
            $item->price = $request->price;
            $item->quantity = $request->quantity;
            $item->product_owner_email = $request->product_owner_email;
            $item->quantity_sold = 0;
            $item->status = $request->quantity > 0 ? 'available' : 'not_available';
            $item->valid_from = $request->valid_from;
            $item->valid_to = $request->valid_to;
            $item->payment_plan = $request->payment_plan;
            $item->limit_colleges = json_decode($request->input('limitColleges'), true);
            $item->limit_courses = json_decode($request->input('limitCourses'), true);
            $item->visibility_options = json_decode($request->input('visibility_options'), true);
            $item->created_by = $user->id;

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
                $paymentPlan->initial_deposit = $installmentsAndDeposit['initial_deposit'];
                $paymentPlan->initial_deposit_due_date = $installmentsAndDeposit['initial_deposit_due_date'];
                $paymentPlan->other_installments_deadline_installments = $installmentsAndDeposit['other_installments_due_date'];
                $paymentPlan->save();
            }
            // DB::commit();
            if($shop->product_owner_name!=null && $shop->product_owner_email!=null){
                $data['owner_name']=$shop->product_owner_name;
                $data['owner_email']=$shop->product_owner_email;
                $data['product_name']= $item->name;
                $data['product_price']= number_format($item->price, 2, '.', '');
                $data['product_quantity']=$item->quantity;
                Mail::send('emails.ProductCreated', ['data' => $data], function($message) use ($data) {
                    $message->from('studentpay@xepos.co.uk');
                    $message->to($data['owner_email']);
                    $message->subject('New Product Created – Notification');
                });
            }
            $response = ['Successfully Created Item'];
            return response()->json($response, 200);
        // } catch (\Exception $exception) {
        //     DB::rollback();
        //     if (('APP_ENV') == 'local') {
        //         return response()->json($exception, 500);
        //     } else {
        //         return response()->json($exception, 500);
        //     }
        // }
    }
    
    //----------FIND SHOP ITEM---------
    public function findShopItem($id){
        $shopItem=ShopItem::find($id);
        return response()->json($shopItem, 200);
    }

    //----------Edit SHOP ITEM---------
    public function editShopItem($id){
        $shopItem=ShopItem::with('payment')->find($id);
        return response()->json($shopItem, 200);
    }

    //----------UPDATE SHOP ITEM---------
    public function updateShopItem(Request $request,$id){
        $validator = Validator::make($request->all(), [
            'shop_id' =>['nullable',Rule::exists('organization_shops', 'id')],
            'name' => 'required|string|max:255',
            'image' => 'nullable',
            'product_type' => 'required',
            'detail' => 'required|string|max:255',
            'price' => 'required|numeric',
            'quantity'=>'nullable|numeric',
            'valid_from' => 'required',
            'valid_to' => 'required',
            'payment_plan' => 'required',
            'limitColleges'=>'nullable',
            'limitCourses'=>'nullable',
            'visibility_options'=>'nullable',
            'installmentsAndDeposit'=>'nullable',
            'product_owner_email'=>'nullable'
        ]);
        if ($validator->fails())
        {
            return response()->json(['errors'=>$validator->errors()->all()], 422);
        }
        try {
            DB::beginTransaction();
            $user=Auth::user();                
            $shop=OrganizationShop::first();
            $shop_id=$shop->id;
            $item =ShopItem::find($id);
            $item->shop_id=$shop_id;
            $item->name=$request->name;
            $item->product_type = $request->product_type;
            $item->detail=$request->detail;
            $item->product_owner_email = $request->product_owner_email;
            $item->price=$request->price;
            $item->quantity=$request->quantity;
            if($request->quantity){
            $item->status = $request->quantity > 0 ? 'available' : 'not_available';
            }
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
                $paymentPlan = PaymentPlan::where('shop_item_id',$item->id)->first();
                $installmentsAndDeposit = json_decode($request->input('installmentsAndDeposit'), true);
                $paymentPlan->total_installments = $installmentsAndDeposit['total_installments'];
                $paymentPlan->amount_per_installment = $installmentsAndDeposit['amount_per_installment'];
                $paymentPlan->initial_deposit = $installmentsAndDeposit['initial_deposit'];
                $paymentPlan->initial_deposit_due_date = $installmentsAndDeposit['initial_deposit_due_date'];
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


    public function productsOwner(Request $request){
        $validator = Validator::make($request->all(), [
            'product_owner_name' => 'required|string|max:255',
            'product_owner_email' => 'required|string|max:255',
        ]);
        if ($validator->fails())
        {
            return response()->json(['errors'=>$validator->errors()->all()], 422);
        }
        $shop=OrganizationShop::first();
        $shop->product_owner_name=$request->product_owner_name;
        $shop->product_owner_email=$request->product_owner_email;
        $shop->save();
        $response = ['Successfully Saved'];
        return response()->json($response, 200);
    }
}
