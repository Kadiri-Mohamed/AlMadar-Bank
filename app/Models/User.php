<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_admin',  
        'date_of_birth', 
        'phone',       
        'address',     
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'date_of_birth' => 'date',
            'is_admin' => 'boolean',
        ];
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */

    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     *
     * @return mixed
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return [];
    }

    public function accounts()
    {
        return $this->belongsToMany(Account::class, 'co_ownerships')
            ->withPivot('accepted_closure')
            ->withTimestamps();
    }

    public function coOwnerships()
    {
        return $this->hasMany(CoOwnership::class);
    }

    public function initiatedTransfers()
    {
        return $this->hasMany(Transfer::class, 'initiated_by');
    }

    public function guardian()
    {
        return $this->hasOne(Guardian::class, 'minor_id');
    }

    public function wards()
    {
        return $this->hasMany(Guardian::class, 'guardian_id');
    }

    public function isMinor(): bool
    {
        if (!$this->date_of_birth) {
            return false;
        }
        return $this->date_of_birth->age < 18;
    }

    public function isAdult(): bool
    {
        return !$this->isMinor();
    }
}
