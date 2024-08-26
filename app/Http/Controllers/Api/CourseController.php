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
    public function getAllCourses(){
        $courses=Course::paginate(20);

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
            ->paginate(60);
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
        $student->StudentID=$request->StudentID;
        $student->save();
        $response['message']='Successfully Enrolled the student';
        return response()->json($response, 200);
    }

    public function getCoursesForDropdown(){
        $courses = Course::get()->map(function($course) {
            return [
                'name' => $course->CourseCode,
            ];
        });
        return response()->json($courses, 200);
    }
}
