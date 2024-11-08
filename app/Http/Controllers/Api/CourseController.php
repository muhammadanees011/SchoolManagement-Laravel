<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Models\Course;
use App\Models\Student;
use App\Models\StudentCourse;
use App\Http\Resources\StudentCourseResource;

class CourseController extends Controller
{
    //-------------GET ALL Courses-------------
    public function getAllCourses(Request $request){
        $courses=Course::paginate($request->entries_per_page);

        $pagination = [
            'current_page' => $courses->currentPage(),
            'last_page' => $courses->lastPage(),
            'per_page' => $courses->perPage(),
            'total' => $courses->total(),
        ];
        $response['data']=$courses;
        $response['pagination']=$pagination;
        return response()->json($response, 200);
    }
    
    public function createCourse(Request $request){
        $validator = Validator::make($request->all(), [
            'CourseCode' => 'required|string|max:255',
            'CourseLevel' => 'required|string|max:255',
            'CourseDescription' => 'required|string|max:255',
            'status'=>'required|string|max:255'
        ]);
        if ($validator->fails())
        {
            return response()->json(['errors'=>$validator->errors()->all()], 422);
        }
        try {
            DB::beginTransaction();
            $course = new Course();
            $course->CourseCode=$request->CourseCode;
            $course->CourseLevel=$request->CourseLevel;
            $course->CourseDescription=$request->CourseDescription;
            $course->status=$request->status;
            $course->save();
            DB::commit();
            $response['message'] = ['Successfully created the Course'];
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

    public function editCourse($id){
        $course=Course::find($id);
        return response()->json($course, 200);
    }

    public function updateCourse(Request $request,$id){
        $validator = Validator::make($request->all(), [
            'CourseCode' => 'required|string|max:255',
            'CourseLevel' => 'required|string|max:255',
            'CourseDescription' => 'required|string|max:255',
            'status'=>'required|string|max:255'
        ]);
        if ($validator->fails())
        {
            return response()->json(['errors'=>$validator->errors()->all()], 422);
        }
        try {
            DB::beginTransaction();
            $course =Course::find($id);
            $course->CourseCode=$request->CourseCode;
            $course->CourseLevel=$request->CourseLevel;
            $course->CourseDescription=$request->CourseDescription;
            $course->status=$request->status;
            $course->save();
            DB::commit();
            $response['message'] = ['Successfully updated the Course'];
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

    public function deleteCourse($id){
        try {
            DB::beginTransaction();
            $course =Course::find($id);
            $course->delete();
            DB::commit();
            $response = ['Successfully deleted Course'];
            return response()->json($response, 200);
        } catch (\Exception $exception) {
            DB::rollback();
            if (('APP_ENV') == 'local') {
                dd($exception);
            } else {
                $response['message'] = ['parent not found'];
                return response()->json($response, 404);

            }
        }
    }

    public function getCourseStudents(Request $request){
        $studentIDs = StudentCourse::where('CourseCode', $request->CourseCode)
            ->pluck('StudentID')
            ->toArray();
        $students = Student::whereIn('student_id', $studentIDs)
            ->with('user.balance', 'school')
            ->whereHas('user', function($query) {
                $query->where('status', 'active');
            })
            ->orderBy('created_at', 'desc')
            ->paginate($request->entries_per_page);
        $res=StudentCourseResource::collection($students);
        $pagination = [
            'current_page' => $students->currentPage(),
            'last_page' => $students->lastPage(),
            'per_page' => $students->perPage(),
            'total' => $students->total(),
        ];
        $response['data']=$res;
        $response['pagination']=$pagination;
        return response()->json($response, 200);
    }

    public function removeEnrolledStudent(Request $request){
        $validator = Validator::make($request->all(), [
            'CourseCode' => 'required|string|max:255',
            'StudentID' => 'required|string|max:255',
        ]);
        if ($validator->fails())
        {
            return response()->json(['errors'=>$validator->errors()->all()], 422);
        }
        $student = StudentCourse::where('CourseCode', $request->CourseCode)
        ->where('StudentID',$request->StudentID)->first();
        $student->delete();
        $response['message']='Successfully Removed the student';
        return response()->json($response, 200);
    }

    public function enrollStudent(Request $request){
        $validator = Validator::make($request->all(), [
            'CourseCode' => 'required|string|max:255',
            'StudentID' => 'required|string|max:255',
        ]);
        if ($validator->fails())
        {
            return response()->json(['errors'=>$validator->errors()->all()], 422);
        }
        $validStudent=Student::where('student_id',$request->StudentID)->first();
        if(!$validStudent){
            return response()->json(['errors'=>['invalid student']], 422);
        }
        $validCourse=Course::where('CourseCode',$request->CourseCode)->first();
        if(!$validCourse){
            return response()->json(['errors'=>['invalid course code']], 422);
        }

        $student=new StudentCourse();
        $student->CourseCode=$request->CourseCode;
        $student->CourseDescription=$validCourse->CourseDescription;
        $student->StudentID=$request->StudentID;
        $student->save();
        $response['message']='Successfully Enrolled the student';
        return response()->json($response, 200);
    }

    public function filterCourseStudents(Request $request){
        $validator = Validator::make($request->all(), [
            'type' => 'required',
            'value' => 'required',
            'course_code' => 'required',
        ]);
        if ($validator->fails())
        {
            return response()->json(['errors'=>$validator->errors()->all()], 422);
        }
        if($request->type=='Student Id'){

            $studentIDs=StudentCourse::where('CourseCode',$request->course_code)
            ->where('StudentID',$request->value)
            ->pluck('StudentID')
            ->toArray();
            
            $students = Student::whereIn('student_id', $studentIDs)
            ->with('user.balance', 'school')
            ->whereHas('user', function($query) {
                $query->where('status', 'active');
            })
            ->orderBy('created_at', 'desc')->get();
            $res=StudentCourseResource::collection($students);
            return response()->json($res, 200);

        }
    }

    public function filterCourses(Request $request){
        $validator = Validator::make($request->all(), [
            'type' => 'required',
            'value' => 'required',
            'status' => 'required',
        ]);
        if ($validator->fails())
        {
            return response()->json(['errors'=>$validator->errors()->all()], 422);
        }
        if($request->type=='Course Code'){
            $courses=Course::where('CourseCode', 'like', '%' . $request->value . '%')
            ->where('status',$request->status)->get();
        }else if($request->type=='Course Name'){
            $courses=Course::where('CourseDescription', 'like', '%' . $request->value . '%')
            ->where('status',$request->status)->get();
        }else if($request->type=='Course Level'){
            $courses=Course::where('CourseLevel', 'like', '%' . $request->value . '%')
            ->where('status',$request->status)->get();
        }
        return response()->json($courses, 200);
    }

    public function getCoursesForDropdown(){
        $courses = Course::where('status','Active')->get()->map(function($course) {
            return [
                'name' => $course->CourseCode.'-'.$course->CourseDescription.'',
            ];
        });
        return response()->json($courses, 200);
    }
}
