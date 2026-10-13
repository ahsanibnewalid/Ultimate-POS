<?php
namespace App\\Models;
use Illuminate\\Foundation\\Auth\\User as Authenticatable;
use Laravel\\Sanctum\\HasApiTokens;
use Illuminate\\Notifications\\Notifiable;
class User extends Authenticatable {
    use HasApiTokens, Notifiable;
    protected $fillable = ['tenant_id','name','email','password','role'];
    protected $hidden = ['password','remember_token'];
    protected function casts(): array { return ['email_verified_at'=>'datetime','password'=>'hashed']; }
    public function tenant() { return $this->belongsTo(Tenant::class); }
}
