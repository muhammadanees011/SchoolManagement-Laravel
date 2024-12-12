<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable,HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'gender',
        'email',
        // 'role',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function schoolShop()
    {
        return $this->belongsTo(SchoolShop::class);
    }

    public function OrganizationAdmin()
    {
        return $this->hasOne(OrganizationAdmin::class);
    }

    public function Balance()
    {
        return $this->hasOne(Wallet::class,'user_id','id');
    }

    public function UserRole()
    {
        return $this->hasOne(UserRole::class,'model_id','id');
    }

    public function student()
    {
        return $this->hasOne(Student::class,'user_id','id');
    }

    public function staff()
    {
        return $this->hasOne(Staff::class,'user_id','id');
    }

}
