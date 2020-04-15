<?php


namespace Tests\Unit\Admin\Order;


use App\Http\Controllers\Order\ExtendedOrderController;
use App\Model\Order\Order;
use App\Model\Product\Product;
use App\Model\Product\Subscription;
use Tests\DBTestCase;

class ExtendedOrderControllerTest extends DBTestCase
{
    private $classObject;

    public function setUp(): void
    {
        parent::setUp();
        $this->classObject = new ExtendedOrderController();
    }

    public function test_getBaseQueryForOrders_givesRequiredColumnsWhenCalled()
    {
        $this->getLoggedInUser("admin");
        $product = Product::create(["name"=>"Helpdesk"]);
        $order = Order::create(['client'=> $this->user->id, 'order_status' => 'executed', 'product'=> $product->id]);
        $subscription = Subscription::create(["order_id"=>$order->id, 'product_id'=> $product->id, 'version'=>"v3.0.0"]);
        $query = $this->getPrivateMethod($this->classObject, "getBaseQueryForOrders");
        $record = $query->first();
        $this->assertEquals($order->id, $record->id);
        $this->assertEquals($order->order_status, $record->order_status);
        $this->assertEquals($product->name, $record->product_name);
        $this->assertEquals($this->user->first_name." ".$this->user->last_name, $record->client_name);
        $this->assertEquals($subscription->version, $record->product_version);
        $this->assertEquals($this->user->currency, $record->currency);
    }

    public function test_getSelectedVersionOrders_whenVersionLessThanAndMoreThanAreNull_shouldNotChangeTheQuery()
    {
        $this->getLoggedInUser("admin");
        $this->createOrder("v3.0.0");
        $this->createOrder("v3.1.0");
        $this->createOrder("v3.2.0");
        $baseQuery = $this->getPrivateMethod($this->classObject, "getBaseQueryForOrders");
        $query = $this->getPrivateMethod($this->classObject, "getSelectedVersionOrders", [$baseQuery, null, null]);
        $this->assertEquals(3, $query->count());
    }

    public function test_getSelectedVersionOrders_whenVersionLessThanNotNullAndMoreThanAreNull_shouldGiveResultWhichAreLessThanEqualPassedVersion()
    {
        $this->getLoggedInUser("admin");
        $this->createOrder("v3.0.0");
        $this->createOrder("v3.1.0");
        $this->createOrder("v3.2.0");
        $baseQuery = $this->getPrivateMethod($this->classObject, "getBaseQueryForOrders");
        $query = $this->getPrivateMethod($this->classObject, "getSelectedVersionOrders", [$baseQuery, "v3.1.0", null]);
        $records = $query->get();
        $this->assertEquals(2, $records->count());
        $this->assertEquals("v3.0.0", $records[0]->product_version);
        $this->assertEquals("v3.1.0", $records[1]->product_version);
    }

    public function test_getSelectedVersionOrders_whenVersionLessThaIsNullAndMoreThanIsNotNull_shouldGiveResultWhichAreGreaterThanEqualToPassedVersion()
    {
        $this->getLoggedInUser("admin");
        $this->createOrder("v3.0.0");
        $this->createOrder("v3.1.0");
        $this->createOrder("v3.2.0");
        $baseQuery = $this->getPrivateMethod($this->classObject, "getBaseQueryForOrders");
        $query = $this->getPrivateMethod($this->classObject, "getSelectedVersionOrders", [$baseQuery, null, "v3.1.0"]);
        $records = $query->get();
        $this->assertEquals(2, $records->count());
        $this->assertEquals("v3.1.0", $records[0]->product_version);
        $this->assertEquals("v3.2.0", $records[1]->product_version);
    }

    public function test_getSelectedVersionOrders_whenVersionLessThaIsNotNullAndMoreThanIsNotNull_shouldGiveIntersectionOfBoth()
    {
        $this->getLoggedInUser("admin");
        $this->createOrder("v3.0.0");
        $this->createOrder("v3.1.0");
        $this->createOrder("v3.2.0");
        $baseQuery = $this->getPrivateMethod($this->classObject, "getBaseQueryForOrders");
        $query = $this->getPrivateMethod($this->classObject, "getSelectedVersionOrders", [$baseQuery, "v3.1.0", "v3.1.0"]);
        $records = $query->get();
        $this->assertEquals(1, $records->count());
        $this->assertEquals("v3.1.0", $records[0]->product_version);
    }

    private function createOrder($version = "v3.0.0")
    {
        $product = Product::create(["name"=>"Helpdesk".$version]);
        $order = Order::create(['client'=> $this->user->id, 'order_status' => 'executed',
            'product'=> $product->id, "number"=> mt_rand(100000, 999999)]);
        Subscription::create(["order_id"=>$order->id, 'product_id'=> $product->id, 'version'=>$version]);
    }
}