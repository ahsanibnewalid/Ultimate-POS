<?php
namespace Tests\Feature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class TenantIsolationTest extends TestCase {
 use RefreshDatabase;
 public function test_products_are_scoped_to_the_authenticated_tenant(): void {
  $first=$this->postJson('/api/register',['business_name'=>'Store One','name'=>'Owner One','email'=>'one@example.test','password'=>'Correct-Horse-Battery-1!','password_confirmation'=>'Correct-Horse-Battery-1!'])->assertCreated()->json();
  $second=$this->postJson('/api/register',['business_name'=>'Store Two','name'=>'Owner Two','email'=>'two@example.test','password'=>'Correct-Horse-Battery-2!','password_confirmation'=>'Correct-Horse-Battery-2!'])->assertCreated()->json();
  $this->assertNotSame($first['tenant']['id'],$second['tenant']['id']);
  $this->postJson('/api/products',['name'=>'Coffee','sku'=>'COF-1','price'=>3.50,'cost'=>1.20],['Authorization'=>'Bearer '.$first['token']])->assertCreated();
  $this->getJson('/api/me',['Authorization'=>'Bearer '.$second['token']])->assertOk()->assertJsonPath('user.email','two@example.test');
  $this->getJson('/api/products',['Authorization'=>'Bearer '.$second['token']])->assertOk()->assertJsonCount(0,'data');
  $this->patchJson('/api/products/1',['price'=>0.01],['Authorization'=>'Bearer '.$second['token']])->assertNotFound();
 }
 public function test_stock_cannot_be_sold_below_zero(): void {
  $account=$this->postJson('/api/register',['business_name'=>'Stock Store','name'=>'Stock Owner','email'=>'stock@example.test','password'=>'Correct-Horse-Battery-3!','password_confirmation'=>'Correct-Horse-Battery-3!'])->assertCreated()->json();
  $this->postJson('/api/products',['name'=>'Tea','sku'=>'TEA-1','price'=>10,'cost'=>4],['Authorization'=>'Bearer '.$account['token']])->assertCreated();
  $this->postJson('/api/sales',['client_sale_id'=>'2b73a2a1-2c88-4d9f-90f5-a2a3b8a8a001','location_name'=>'Main Store','items'=>[['product_id'=>1,'quantity'=>1]],'payments'=>[['method'=>'cash','amount'=>10]]],['Authorization'=>'Bearer '.$account['token']])->assertUnprocessable();
 }
}
