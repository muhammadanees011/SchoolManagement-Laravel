<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\UserPermission;
use Symfony\Component\HttpFoundation\Response;

class CheckPermissions
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, $permission): Response
    {
        if($this->checkIfPermission($permission)){
            return $next($request);
        }else{
            return response()->json(['message' => 'Unauthorized.'], 403);
        }
    }

    public function checkIfPermission($permission){
        $user = auth()->user();
        if($user->role=='staff'||$user->role=='student'||$user->role=='parent'){
            $userPermissions=UserPermission::with('permission')->where('user_id',$user->id)->get();
            foreach ($userPermissions as $userPermission) {
                if ($userPermission->permission->name === $permission) {
                    return true;
                }
            }
            return false;
        }else if($user->role=='super_admin'||$user->role=='organization_admin'){
            return true;
        }
        
    }
}
