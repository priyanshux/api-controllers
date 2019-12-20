<?php namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Lang;
use Validator;
use Queue;

use App\User;
use App\Models\OrderDelivery;
use App\Models\OrderDeliveryBarcode;
use App\Models\OrderDeliverySummary;
use App\Models\OrderDeliveryIssue;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\Setting;
use App\Models\OrderProductPacking;

class DeliveryController extends Controller {

        protected $user;

        public function __construct() {

        }
	
        	
	/**
	* @SWG\Get(
	*   path="/delivery/tower/list",
	*   summary="Get list of society tower for delivery",
	*   tags={"Delivery"},
	*   description="Get list of society tower.",
	*   operationId="deliveryTowerList",
	*   produces={"application/json"},
	*   @SWG\Parameter(
	*       name="Key",
	*       in="header",
	*       description="API Key",
	*       required=true,
	*       type="string",
	*	default="FiUOBt1ptC2ZHyVu9Ruz",
	*       @SWG\Items(type="string")
	*   ),
	*   @SWG\Parameter(
	*       name="version",
	*       in="header",
	*       description="App Version",
	*       required=true,
	*       type="string",
	*	default="1.0.1",
	*       @SWG\Items(type="string")
	*   ),
	*   @SWG\Parameter(
	*       name="Authorization",
	*       in="header",
	*       description="Auth Token",
	*       required=true,
	*       type="string",
	*	default="Bearer ",
	*       @SWG\Items(type="string")
	*   ),	
	*   @SWG\Parameter(
	*	name="hub",
	*       in="query",
	*       description="Hub ID",
	*       required=true,
	*       type="integer",
	*       @SWG\Items(type="string")
	*	),
	*   @SWG\Parameter(
	*	name="society",
	*       in="query",
	*       description="Society ID",
	*       required=true,
	*       type="integer",
	*       @SWG\Items(type="string")
	*	),
	*   @SWG\Parameter(
	*	name="delivery_type",
	*       in="query",
	*       description="Delivery Type",
	*       required=true,
	*       type="integer",
	*       @SWG\Items(type="string")
	*	),
	*   @SWG\Response(
	*       response=200,
	*       description="List of tower",
        *       @SWG\Schema(ref="#/definitions/DeliveryTowerListResponse")
	*   ),
	*   @SWG\Response(
	*       response="400",
	*       description="Data field errors",   
        *       @SWG\Schema(ref="#/definitions/MessageModel")
	*   )
	* )
        * @SWG\Definition(
        * 	definition="DeliveryTowerListResponse",
        *       type="array",
        *       @SWG\Items( 
        *                   @SWG\Property(property="address_tower", type="string", example="Tower 1"),
        *                   @SWG\Property(property="total_order", type="integer", example=10),
        *                   @SWG\Property(property="pending_order", type="string", example="5"),
        *                   @SWG\Property(property="bags", type="array", @SWG\Items(
        *                           @SWG\Property(property="sheet", type="integer", example=1), 
        *                           @SWG\Property(property="total", type="string", example="10") 
        *                           )
        *                   )
        *      )
        *  )
	*/	
		
	public function tower(Request $request) {
		
		$auth = User::auth($request, 'view_deliveries');
		if (!$auth['status']) {
			$message = Lang::get($auth['error']);
                        return response()->json(compact('message'), 401);
		}
		$this->user = $auth['user'];
		
		$input = $request->all();
                $input['hub'] = isset($input['hub']) ? $input['hub'] : '';
		$validator = Validator::make($input, [
			'hub'          =>	'required|exists:hub,id,flag,1',
                        'society'       =>	'required|exists:societies,id,hub_id,'.$input['hub'].',flag,1',
                        'delivery_type' =>	'required'
		]);

		if ($validator->fails()) {
			$message = $validator->messages()->first();
			return response()->json(compact('message'), 400);
		}
                
		$filters = array();
                $filters['date'] = date('Y-m-d');
                if (isset($input['hub'])) $filters['hub_id'] = $input['hub'];
                if (isset($input['society'])) $filters['society_id'] = $input['society'];
                if (isset($input['delivery_type'])) $filters['delivery_type'] = $input['delivery_type'];
                $filters['completeNaOrderId'] = OrderProductPacking::getCompleteNaOrders($filters);
                
                $data = OrderDelivery::getTowerList($filters);
                
                foreach($data as &$tower){
                    $filters['address_tower'] = $tower->address_tower;
                    $tower->bags = OrderDelivery::getBagCount($filters);
                }
                
                return response()->json($data, 200);
	}

        
        
    /**
     * @SWG\Get(
     *   path="/delivery/order/list",
     *   summary="Get order list for delivery",
     *   tags={"Delivery"},
     *   description="Get order lists along with order detail.",
     *   operationId="deliveryOrderList",
     *   consumes={"application/json"},
     *   produces={"application/json"},
     *   @SWG\Parameter(
     *       name="Key",
     *       in="header",
     *       description="API Key",
     *       required=true,
     *       type="string",
     *	default="FiUOBt1ptC2ZHyVu9Ruz",
     *       @SWG\Items(type="string")
     *       ),
     *   @SWG\Parameter(
     *       name="version",
     *       in="header",
     *       description="App Version",
     *       required=true,
     *       type="string",
     *	default="1.0.1",
     *       @SWG\Items(type="string")
     *   ),
     *   @SWG\Parameter(
     *       name="Authorization",
     *       in="header",
     *       description="Auth Token",
     *       required=true,
     *       type="string",
     *	default="Bearer ",
     *       @SWG\Items(type="string")
     *       ),
     *   @SWG\Parameter(
     *	name="hub",
     *       in="query",
     *       description="Hub ID",
     *       required=true,
     *       type="integer",
     *       @SWG\Items(type="string")
     *	),
     *   @SWG\Parameter(
     *	name="society",
     *       in="query",
     *       description="Society ID",
     *       required=true,
     *       type="integer",
     *       @SWG\Items(type="string")
     *	),
     *   @SWG\Parameter(
     *	name="tower",
     *       in="query",
     *       description="Tower Name",
     *       required=true,
     *       type="string",
     *       @SWG\Items(type="string")
     *	),
     *   @SWG\Parameter(
     *	name="delivery_type",
     *       in="query",
     *       description="Delivery Type",
     *       required=true,
     *       type="string",
     *       @SWG\Items(type="string")
     *	),
     *   @SWG\Response(
     *       response=200,
     *       description="List of order",
     *       @SWG\Schema(ref="#/definitions/DeliveryOrderListResponse")
     *   ),
     *   @SWG\Response(
     *       response="400",
     *       description="Data field errors",
     *       @SWG\Schema(ref="#/definitions/MessageModel")
     *   )
     * )
     * @SWG\Definition(
     * 	definition="DeliveryOrderListResponse",
     *       type="array",
     *       @SWG\Items(
     *                   @SWG\Property(property="order_id", type="integer", example=10021),
     *                   @SWG\Property(property="firstname", type="string", example="Rajnesh"),
     *                   @SWG\Property(property="lastname", type="string", example="Rajput"),
     *                   @SWG\Property(property="status", type="string", example="Pending"),
     *                   @SWG\Property(property="address_unit", type="string", example="301"),
     *                   @SWG\Property(property="barcode_flag", type="integer", example=0),
     *                   @SWG\Property(property="barcode", type="string", example="301"),
     *                   @SWG\Property(property="bags", type="array", @SWG\Items(
     *                           @SWG\Property(property="sheet", type="integer", example=1),
     *                           @SWG\Property(property="total", type="string", example="10")
     *                           )
     *                   ),
     *                   @SWG\Property(
     *                           property="instruction",
     *                           @SWG\Property(property="text", type="string", example="Ring Bell"),
     *                           @SWG\Property(property="label", type="string", example="ring"),
     *                   ),
     *                   @SWG\Property(property="products", type="array", @SWG\Items(
     *                           @SWG\Property(property="id", type="integer", example=121),
     *                           @SWG\Property(property="product_id", type="integer", example=12),
     *                           @SWG\Property(property="product_name", type="string", example="Amul Double Toned Milk"),
     *                           @SWG\Property(property="quantity", type="integer", example=2),
     *                           @SWG\Property(property="hub_id", type="integer", example=2),
     *                           @SWG\Property(property="image", type="string", example="amul-milk-double-toned.jpg")
     *                           )
     *                   )
     *      )
     *  )
     */
	
	public function order(Request $request) {
		
                $auth = User::auth($request, 'view_deliveries');
                        if (!$auth['status']) {
                    $message = Lang::get($auth['error']);
                    return response()->json(compact('message'), 401);
                }
                $this->user = $auth['user'];

                $input = $request->all();
                $input['hub'] = isset($input['hub']) ? $input['hub'] : '';
                $validator = Validator::make($input, [
                                'hub'          =>	'required|exists:hub,id,flag,1',
                                'society'       =>	'required|exists:societies,id,hub_id,'.$input['hub'].',flag,1',
                                'tower'         =>	'required',
                                'delivery_type' =>	'required'
                ]);

                if ($validator->fails()) {
                    $message = $validator->messages()->first();
                    return response()->json(compact('message'), 400);
                }

                $societies = Setting::where('slug', 'society_ids_for_barcode')->first();
                $society_ids = array();

                if(!is_null($societies)) {
                    $society_ids = explode(',', $societies->value);
                }

                $societies_gate = Setting::where('slug', 'society_ids_for_gate_locked')->first();
                $society_gates = array();

                if(!is_null($societies_gate)) {
                    $society_gates = explode(',', $societies_gate->value);
                }

                $filters = array();
                $filters['date'] = date('Y-m-d');
                if (isset($input['hub'])) $filters['hub_id'] = $input['hub'];
                if (isset($input['society'])) $filters['society_id'] = $input['society'];
                if (isset($input['tower'])) $filters['address_tower'] = trim($input['tower']);
                if (isset($input['delivery_type'])) $filters['delivery_type'] = $input['delivery_type'];
                $filters['completeNaOrderId'] = OrderProductPacking::getCompleteNaOrders($filters);
                
                $orders = OrderDelivery::getOrderList($filters);
                foreach($orders as &$order){
                        if(!$order->status){
                                $instructions = OrderDelivery::getUserDeliveryInstruction($order->user_id);
                                $instructions_array = [];
                                if(!empty($instructions)){
                                    $instructions_array = $instructions[0];
                                }
                                $order->instruction = $instructions_array;
                                $order->products = OrderProduct::getDeliveryProducts(array('order_id' => $order->order_id));
                                $order->bags = OrderDelivery::getOrderBagCount(array('order_id' => $order->order_id));

                                if (!empty($society_ids) && in_array($input['society'], $society_ids)) {
                                    $barcoded_user = OrderDeliveryBarcode::getUserBarcodeMapping($order->user_id);

                                    // barcode flag is false when we need to send new barcode to user (status=0) 
                                    if (is_null($barcoded_user) || $barcoded_user->status == 0) {
                                        $order->barcode_flag = 0;
                                    } else {
                                        $order->barcode_flag = 1;
                                        if ($barcoded_user->status == 3 || $barcoded_user->status == 2) {
                                            $order->first_scan_flag = 1;
                                        }
                                        $order->barcode = $barcoded_user->barcode_number;
                                    }
                                }
                                if (!empty($society_gates) && in_array($input['society'], $society_gates)) {
                                    // society flag is true when gate is locked (status=1) 
                                        $order->maingate_flag = 1;
                                }
                                else {
                                        $order->maingate_flag = 0;
                                }
                        }
                        $order->status = OrderDelivery::getStatus($order->status);
                        $last_order_issue = OrderDeliveryIssue::getLastOrderIssue(array(
                                                                                    'user_id' => $order->user_id
                                                                                 ));
                        
                        $order->address_not_found_option = 0;
                        if(!empty($last_order_issue)) {
                            if($last_order_issue->type == 'address_not_available') {
                                $order->address_not_found_option = 1;
                            }
                        } else {
                            //this is to handle when customer ordered today for first time
                            $order->address_not_found_option = 1;
                        }
                        unset($order->user_id);
                }
                return response()->json($orders, 200);
                
	}
        
        /**
	* @SWG\Post(
	*   path="/delivery/order/{order_id}/deliver",
	*   summary="Deliver an order",
	*   tags={"Delivery"},
	*   description="Deliver an order.",
	*	operationId="deliveryOrderDeliver",
	*       consumes={"application/json"},
	*       produces={"application/json"},
	*	@SWG\Parameter(
	*       name="Key",
	*       in="header",
	*       description="API Key",
	*       required=true,
	*       type="string",
	*	default="FiUOBt1ptC2ZHyVu9Ruz",
	*       @SWG\Items(type="string")
	*   ),
	*   @SWG\Parameter(
	*       name="version",
	*       in="header",
	*       description="App Version",
	*       required=true,
	*       type="string",
	*	default="1.0.1",
	*       @SWG\Items(type="string")
	*   ),
	*	@SWG\Parameter(
	*       name="Authorization",
	*       in="header",
	*       description="Auth Token",
	*       required=true,
	*       type="string",
	*	default="Bearer ",
	*       @SWG\Items(type="string")
	*   ),	
	*   @SWG\Parameter(
	*	name="order_id",
	*       in="path",
	*       description="Order ID",
	*       required=true,
	*       type="integer",
	*       @SWG\Items(type="string")
	*	),
	*   @SWG\Parameter(
	*	name="body",
	*       in="body",
	*       description="Delivery issue data",
	*       required=true,
	*       @SWG\Schema(ref="#/definitions/DeliveryIssueRequest"),
	*   ),
	*   @SWG\Response(
	*       response=200,
	*       description="Order Delivery Response",
        *       @SWG\Schema(ref="#/definitions/DeliveryOrderDeliverResponse")
	*   ),
	*   @SWG\Response(
	*       response="400",
	*       description="Data field errors",   
        *       @SWG\Schema(ref="#/definitions/MessageModel")
	*   )
	* )
        * @SWG\Definition(
        * 	definition="DeliveryIssueRequest",
        * 	@SWG\Property(property="issues", type="array", @SWG\Items(
        *            @SWG\Property(property="type", type="string", example= "bag_missing"), 
        *            @SWG\Property(property="bags", type="array", @SWG\Items(
        *                   @SWG\Property(property="sheet", type="integer", example=1), 
        *                   @SWG\Property(property="qty", type="integer", example=2), 
        *               )
        *            ) 
        *         ) 
        *       )
        *   )
        * @SWG\Definition(
        * 	definition="DeliveryOrderDeliverResponse",
        * 	@SWG\Property(property="message", type="string", example="Order Delivered"),
        *           @SWG\Property(
        *              property="data", 
        *              @SWG\Property(property="status", type="string", example="Partially Delivered")
        *           ) 
        *   )
	*/		
	
	public function deliver($order_id, Request $request) {
		
		$auth = User::auth($request, 'deliver_order');
		if (!$auth['status']) {
			$message = Lang::get($auth['error']);
                        return response()->json(compact('message'), 401);
		}
		$this->user = $auth['user'];
		
		$order = Order::getData(array('order_id' => $order_id, 'date' => date('Y-m-d')));
		if (is_null($order)) {
			$message = Lang::get('messages.order_not_found');
			return response()->json(compact('message'), 400);
		}
		
                $order_delivery = OrderDelivery::where('order_id', $order_id)->where('flag', 1)->first();
		if (!empty($order_delivery)) {
			$message = Lang::get('messages.order_already_delivered');
			return response()->json(compact('message'), 400);
		}
                
                $input = $request->all();
                $return = OrderDeliveryIssue::validation($order_id, $input);
		if($return['error']){
                    $message = $return['error'];
                    return response()->json(compact('message'), 400);
                }
                
		$orderDelivery = New OrderDelivery;
                $orderDelivery->order_id = $order_id;
                $orderDelivery->status = $return['delivery_status'];
		$orderDelivery->user_id = $this->user->id;		
		$orderDelivery->save();
                
                $delivery_data = array('orderId' => $order_id, 'status' => $return['delivery_status'], 'customerId' => $order->user_id);
                
                if (!empty($return['issues'])) {
                        
                            foreach($return['issues'] as $row){
                                
                                    $issue_queue_array = array('order_id' => $order_id, 'issue_type' => $row['type']);
                                    if($row['type'] == 'bag_missing' && !empty($row['data'])){
                                            $bags = array();
                                            foreach($row['data'] as $sheet=>$qty){
                                                $bags[] = array('sheet' => $sheet, 'quantity' => $qty);
                                            }
                                            $issue_queue_array['bags'] = $bags;
                                            
                                    }elseif($row['type'] == 'product_damaged' && !empty($row['products'])){
                                            $order_products = array();
                                            foreach($row['products'] as $id=>$product){
                                                $order_products[] = array('id' => $id, 'quantity' => $product['quantity']);
                                            }
                                            $issue_queue_array['order_products'] = $order_products;
                                            $delivery_data['damagedItems'] = $order_products;
                                    }
                                    
                                    //send issues to SQS
                                    $issue_sqs_url = config('queue.connections.sqs.prefix').config('queue.connections.sqs.delivery_issue_queue');
                                    Queue::pushRaw(json_encode($issue_queue_array), $issue_sqs_url);
                            }
                        
                }
                
                //OrderDelivery::notifyCustomer($order, $this->user->id);
                
                //If order not marked undelivered
                if($return['delivery_status'] != 3){
                    $orderProducts = array();
                    $orderProductArray = OrderProduct::getDeliveryProducts(array('order_id' => $order_id));
                    foreach($orderProductArray as $item){
                        $orderProducts[] = array('id' => $item->id, 'quantity' => $item->quantity);
                    }
                    $delivery_data['orderProducts'] = $orderProducts;
                }
		
                OrderDelivery::notifyToCustomer($delivery_data);

                $data['message'] = Lang::get('messages.order_delivered');
                $data['data']['status'] = OrderDelivery::getStatus($return['delivery_status']);
                return response()->json($data, 200);
    }

    /**
     *
     * @SWG\Post(
     *   path="/delivery/order/delivers",
     *   summary="Scan and Deliver an order",
     *   tags={"Delivery"},
     *   description="Scan and Deliver an order.",
     *	operationId="deliveryScanOrderDeliver",
     *       consumes={"application/json"},
     *       produces={"application/json"},
     *	@SWG\Parameter(
     *       name="Key",
     *       in="header",
     *       description="API Key",
     *       required=true,
     *       type="string",
     *	default="FiUOBt1ptC2ZHyVu9Ruz",
     *       @SWG\Items(type="string")
     *   ),
     *   @SWG\Parameter(
     *       name="version",
     *       in="header",
     *       description="App Version",
     *       required=true,
     *       type="string",
     *	default="1.0.1",
     *       @SWG\Items(type="string")
     *   ),
     *	@SWG\Parameter(
     *       name="Authorization",
     *       in="header",
     *       description="Auth Token",
     *       required=true,
     *       type="string",
     *	default="Bearer ",
     *       @SWG\Items(type="string")
     *   ),
     *   @SWG\Parameter(
     *      name="body",
     *       in="body",
     *       description="Scanned Barcode data for multiple orders",
     *       required=true,
     *       @SWG\Schema(ref="#/definitions/ScannedBarcodesRequest"),
     *   ),
    *   @SWG\Response(
    *       response=200,
    *       description="Order Delivery Response",
    *       @SWG\Schema(ref="#/definitions/ScannedBarcodesResponse")
    *   )
     * )
     * @SWG\Definition(
     * 	definition="ScannedBarcodesRequest",
     * 	@SWG\Property(property="orders", type="array", @SWG\Items(
     *            @SWG\Property(property="barcode", type="string", example= "QXYRT"),
     *            @SWG\Property(property="match", type="integer", example= "1"),
     *            @SWG\Property(property="order_id", type="integer", example= "10021")
     *            )
     *         )
     *       )
     *   )
        * @SWG\Definition(
        * 	definition="ScannedBarcodesResponse",
        * 	@SWG\Property(property="message", type="string", example="Order Delivered"),
        *           @SWG\Property(
        *              property="data", 
        *              @SWG\Property(property="status", type="string", example="Partially Delivered")
        *           ) 
        *   )
     */
    public function scanAndDeliver(Request $request){
        
            $auth = User::auth($request, 'deliver_order');
            if (! $auth['status']) {
                $message = Lang::get($auth['error']);
                return response()->json(compact('message'), 401);
            }
            $this->user = $auth['user'];
            $input = $request->all();
        
            if (! empty($input['orders']) && is_array($input['orders'])) {
                
                    foreach ($input['orders'] as $delivered_order) {
                            $order = Order::getData(array(
                                'order_id' => $delivered_order['order_id'],
                                'date' => date('Y-m-d')
                            ));
                            if (empty($order)) {
                                continue;
                            }

                            $order_delivery = OrderDelivery::where('order_id', $delivered_order['order_id'])->where('flag', 1)->first();
                            //order already delivered
                            if (!empty($order_delivery)) {
                                continue;
                            }

                            $orderDelivery = new OrderDelivery();
                            $orderDelivery->order_id = $delivered_order['order_id'];
                            $orderDelivery->status = 1;
                            $orderDelivery->user_id = $this->user->id;
                            $orderDelivery->save();
                            
                            OrderDeliveryBarcode::addRecord(array(
                                                                  'customer_id' => $order->user_id,
                                                                  'order_id' => $delivered_order['order_id'],
                                                                  'barcode' => isset($delivered_order['barcode']) ? $delivered_order['barcode'] : null,
                                                                  'match' => isset($delivered_order['match']) ? $delivered_order['match'] : 0
                                                            ));
                            
                            $delivery_data = array('orderId' => $delivered_order['order_id'], 'status' => 1, 'customerId' => $order->user_id);
                            $orderProducts = array();
                            $orderProductArray = OrderProduct::getDeliveryProducts(array('order_id' => $delivered_order['order_id']));
                            foreach($orderProductArray as $item){
                                $orderProducts[] = array('id' => $item->id, 'quantity' => $item->quantity);
                            }
                            $delivery_data['orderProducts'] = $orderProducts;
                            OrderDelivery::notifyToCustomer($delivery_data);
                    }
            }
            $data = array();
            return response()->json($data, 200);
    }
        
        	
	/**
	* @SWG\Get(
	*   path="/delivery/society/summary",
	*   summary="Get list of cluster society delivery summary",
	*   tags={"Delivery"},
	*   description="Get list of cluster society summary.",
	*   operationId="deliverySocietySummary",
	*   produces={"application/json"},
	*   @SWG\Parameter(
	*       name="Key",
	*       in="header",
	*       description="API Key",
	*       required=true,
	*       type="string",
	*	default="FiUOBt1ptC2ZHyVu9Ruz",
	*       @SWG\Items(type="string")
	*   ),
	*   @SWG\Parameter(
	*       name="version",
	*       in="header",
	*       description="App Version",
	*       required=true,
	*       type="string",
	*	default="1.0.1",
	*       @SWG\Items(type="string")
	*   ),
	*   @SWG\Parameter(
	*       name="Authorization",
	*       in="header",
	*       description="Auth Token",
	*       required=true,
	*       type="string",
	*	default="Bearer ",
	*       @SWG\Items(type="string")
	*   ),	
	*   @SWG\Parameter(
	*	name="hub",
	*       in="query",
	*       description="Hub ID",
	*       required=true,
	*       type="integer",
	*       @SWG\Items(type="string")
	*	),
	*   @SWG\Parameter(
	*	name="cluster",
	*       in="query",
	*       description="Cluster ID",
	*       required=true,
	*       type="integer",
	*       @SWG\Items(type="string")
	*	),
	*   @SWG\Parameter(
	*	name="delivery_type",
	*       in="query",
	*       description="Delivery Type",
	*       required=true,
	*       type="integer",
	*       @SWG\Items(type="string")
	*	),
	*   @SWG\Response(
	*       response=200,
	*       description="List of society summary",
        *       @SWG\Schema(ref="#/definitions/DeliverySocietySummaryResponse")
	*   ),
	*   @SWG\Response(
	*       response="400",
	*       description="Data field errors",   
        *       @SWG\Schema(ref="#/definitions/MessageModel")
	*   )
	* )
        * @SWG\Definition(
        * 	definition="DeliverySocietySummaryResponse",
        *          type="array",
        *          @SWG\Items( 
        *                   @SWG\Property(property="society_id", type="string", example="1"),
        *                   @SWG\Property(property="society_name", type="string", example="Orchid Petals"),
        *                   @SWG\Property(property="total_order", type="integer", example=10),
        *                   @SWG\Property(property="pending_order", type="string", example="5")
        *          )
        *   )
	*/	
		
	public function societySummary(Request $request) {
		
		$auth = User::auth($request, 'view_deliveries');
		if (!$auth['status']) {
			$message = Lang::get($auth['error']);
                        return response()->json(compact('message'), 401);
		}
		$this->user = $auth['user'];
		
		$input = $request->all();
                $input['hub'] = isset($input['hub']) ? $input['hub'] : '';
		$validator = Validator::make($input, [
			'hub'          =>	'required|exists:hub,id,flag,1',
                        'cluster'       =>	'required|exists:clusters,id,hub_id,'.$input['hub'].',flag,1',
                        'delivery_type' =>	'required'
		]);

		if ($validator->fails()) {
			$message = $validator->messages()->first();
			return response()->json(compact('message'), 400);
		}
                
		$filters = array();
                $filters['date'] = date('Y-m-d');
                if (isset($input['hub'])) $filters['hub_id'] = $input['hub'];
                if (isset($input['cluster'])) $filters['cluster_id'] = $input['cluster'];
                if (isset($input['delivery_type'])) $filters['delivery_type'] = $input['delivery_type'];
                //$filters['completeNaOrderId'] = OrderProductPacking::getCompleteNaOrders($filters);
                
                $data = OrderDeliverySummary::getSocietyList($filters);
                return response()->json($data, 200);
	}
        
        	
	/**
	* @SWG\Get(
	*   path="/delivery/tower/summary",
	*   summary="Get list of society tower delivery summary",
	*   tags={"Delivery"},
	*   description="Get list of society tower summary.",
	*   operationId="deliveryTowerSummary",
	*   produces={"application/json"},
	*   @SWG\Parameter(
	*       name="Key",
	*       in="header",
	*       description="API Key",
	*       required=true,
	*       type="string",
	*	default="FiUOBt1ptC2ZHyVu9Ruz",
	*       @SWG\Items(type="string")
	*   ),
	*   @SWG\Parameter(
	*       name="version",
	*       in="header",
	*       description="App Version",
	*       required=true,
	*       type="string",
	*	default="1.0.1",
	*       @SWG\Items(type="string")
	*   ),
	*   @SWG\Parameter(
	*       name="Authorization",
	*       in="header",
	*       description="Auth Token",
	*       required=true,
	*       type="string",
	*	default="Bearer ",
	*       @SWG\Items(type="string")
	*   ),	
	*   @SWG\Parameter(
	*	name="hub",
	*       in="query",
	*       description="Hub ID",
	*       required=true,
	*       type="integer",
	*       @SWG\Items(type="string")
	*	),
	*   @SWG\Parameter(
	*	name="society",
	*       in="query",
	*       description="Society ID",
	*       required=true,
	*       type="integer",
	*       @SWG\Items(type="string")
	*	),
	*   @SWG\Parameter(
	*	name="delivery_type",
	*       in="query",
	*       description="Delivery Type",
	*       required=true,
	*       type="integer",
	*       @SWG\Items(type="string")
	*	),
	*   @SWG\Response(
	*       response=200,
	*       description="List of tower summary",
        *       @SWG\Schema(ref="#/definitions/DeliveryTowerSummaryResponse")
	*   ),
	*   @SWG\Response(
	*       response="400",
	*       description="Data field errors",   
        *       @SWG\Schema(ref="#/definitions/MessageModel")
	*   )
	* )
        * @SWG\Definition(
        * 	definition="DeliveryTowerSummaryResponse",
        *          type="array",
        *          @SWG\Items( 
        *                   @SWG\Property(property="address_tower", type="string", example="Tower 1"),
        *                   @SWG\Property(property="total_order", type="integer", example=10),
        *                   @SWG\Property(property="pending_order", type="string", example="5"),
        *          )
        *   )
	*/	
		
	public function towerSummary(Request $request) {
		
		$auth = User::auth($request, 'view_deliveries');
		if (!$auth['status']) {
			$message = Lang::get($auth['error']);
                        return response()->json(compact('message'), 401);
		}
		$this->user = $auth['user'];
		
		$input = $request->all();
                $input['hub'] = isset($input['hub']) ? $input['hub'] : '';
		$validator = Validator::make($input, [
			'hub'          =>	'required|exists:hub,id,flag,1',
                        'society'       =>	'required|exists:societies,id,hub_id,'.$input['hub'].',flag,1',
                        'delivery_type' =>	'required'
		]);

		if ($validator->fails()) {
			$message = $validator->messages()->first();
			return response()->json(compact('message'), 400);
		}
                
		$filters = array();
                $filters['date'] = date('Y-m-d');
                if (isset($input['hub'])) $filters['hub_id'] = $input['hub'];
                if (isset($input['society'])) $filters['society_id'] = $input['society'];
                if (isset($input['delivery_type'])) $filters['delivery_type'] = $input['delivery_type'];
                //$filters['completeNaOrderId'] = OrderProductPacking::getCompleteNaOrders($filters);
		
                $data = OrderDeliverySummary::getTowerList($filters);
                return response()->json($data, 200);
	}
        
}
