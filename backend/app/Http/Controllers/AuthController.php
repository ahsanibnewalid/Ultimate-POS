<?php
namespace App\Http\Controllers;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
class AuthController {
    public function register(Request $request) {
        $data = $request->validate([
            'business_name'=>['required','string','max:160'],
            'name'=>['required','string','max:120'],
            'email'=>['required','email','max:255','unique:users,email'],
            'password'=>['required','string','min:12','confirmed'],
        ]);
        $user = DB::transaction(function () use ($data) {
            $base = Str::slug($data['business_name']) ?: 'store'; $slug = $base; $i = 1;
            while (Tenant::where('slug',$slug)->exists()) $slug = $base.'-'.($i++);
            $tenant = Tenant::create(['name'=>$data['business_name'],'slug'=>$slug,'currency'=>'BDT','timezone'=>'Asia/Dhaka']);
            return User::create(['tenant_id'=>$tenant->id,'name'=>$data['name'],'email'=>$data['email'],'password'=>Hash::make($data['password']),'role'=>'admin']);
        });
        return response()->json(['user'=>$user,'tenant'=>$user->tenant,'token'=>$user->createToken('pos-web', ['*'], now()->addDays(30))->plainTextToken],201);
    }
    public function login(Request $request) {
        $data = $request->validate(['email'=>['required','email'],'password'=>['required','string']]);
        $user = User::where('email',$data['email'])->first();
        if (!$user || !Hash::check($data['password'],$user->password)) throw ValidationException::withMessages(['email'=>['The provided credentials are incorrect.']]);
        return response()->json(['user'=>$user,'tenant'=>$user->tenant,'token'=>$user->createToken('pos-web', ['*'], now()->addDays(30))->plainTextToken]);
    }
    public function logout(Request $request) { $request->user()->currentAccessToken()?->delete(); return response()->json(['message'=>'Logged out.']); }
    public function me(Request $request) { return response()->json(['user'=>$request->user(),'tenant'=>$request->user()->tenant]); }
}
