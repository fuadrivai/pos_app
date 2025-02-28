<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\DailyTask;
use App\Models\OnlineShop;
use App\Models\Receipt;
use App\Models\TiktokAccessToken;
use Illuminate\Http\Request;
use NVuln\TiktokShop\Client;

class TiktokApiController extends Controller
{

    public $code = "ROW_GM5-FAAAAABTpmVYZgZzPSYDx8NQ2nU03QmO-I6A3xp-KURBbVLFHXONn52KYPym7imYXsTmbdv6TDL0G0ZVhOpg3_mAHXVe";
    public $apiKey = "67eui64gqqriu";
    public $shopId = "7494670387281169228";
    public $apiSecret = "da24ac38ba6931114cd43e7b49f1bd0a7ae2f2e1";
    public $refreshToken = "ROW_MDViZmRlZDZhZTQyYjQ1MTdiNWZkZmE2NTg3MWUxNWI2MGNkODc4NmRjODRiOA";
    public $access_token = "ROW_7cPEjwAAAADn98JTwrilby-8D5Me_l6_rLJOtwLvSsv_EjEWGSDHDPbGh9D2Cj8PabzYfThARghOQ6Mm_UHJ-fI4ILRAmKaQ4VKMSNchTgWYQWcedmxSWg";
    public $refreshTokenExpired = 1707801821;
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        // return $this->getOrderDetail("576947223578839163");
        return $this->getOrders();
        // return $this->getRefreshToken();
        // return $this->getAccessToken();
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }

    public function getOrderDetail($listOrder, $auth = null)
    {
        if ($auth == null) {
            $auth = $this->getRefreshToken();
        }

        $client = new Client($auth->api_key, $auth->api_secret);
        $client->setAccessToken($auth->access_token);
        $client->setShopId($auth->shopId);
        $orders = $client->Order->getOrderDetail($listOrder);
        $orderList = $orders['order_list'];
        $validOrders = [];

        foreach ($orderList as $order) {
            (int)$order['create_time'];
            (int)$order['update_time'];
            $validItems = [];
            foreach ($order['order_line_list'] as $item) {
                $itemData = $this->mapingOrder($order, $item);
                array_push($validItems, $itemData);
            }
            $order['order_line_list'] = $validItems;

            array_push($validOrders, $this->mapingOrderHeader($order));
        }
        return $validOrders;
    }

    public function getOrders()
    {
        $auth = $this->getRefreshToken();
        $client = new Client($auth->api_key, $auth->api_secret);
        $client->setAccessToken($auth->access_token);
        $client->setShopId($auth->shopId);
        $more = true;
        $nextCursor = null;
        $page_sized = 50;
        $fullOrder = [];
        $listOrder = [];

        $orders = $client->Order->getOrderList([
            'order_status' => 112,
            'page_size' => $page_sized,
            'sort_type' => 2
        ]);
        $more = $orders['more'];
        $nextCursor = $orders['next_cursor'];
        foreach ($orders['order_list'] as $order) {
            array_push($listOrder, $order['order_id']);
        }
        $orderFull = $this->getOrderDetail($listOrder, $auth);
        foreach ($orderFull as $order) {
            array_push($fullOrder, $order);
        }

        while ($more) {
            $listOrder = [];
            $orders = $client->Order->getOrderList([
                'order_status' => 112,
                'page_size' => $page_sized,
                'sort_type' => 2,
                'cursor' => $nextCursor,
            ]);
            $more = $orders['more'];
            $nextCursor = $orders['next_cursor'];
            foreach ($orders['order_list'] as $order) {
                array_push($listOrder, $order['order_id']);
            }
            $orderFull = $this->getOrderDetail($listOrder, $auth);
            foreach ($orderFull as $order) {
                array_push($fullOrder, $order);
            }
        }
        return $fullOrder;
    }

    private function mapingOrder($headerObject, $detail)
    {
        $itemData = null;
        $itemData['image_url'] = $detail['sku_image'];
        $itemData['item_name'] = $detail['product_name'];
        $itemData['item_sku'] = $detail['seller_sku']??"";
        $itemData['variation'] = $detail['sku_name'];
        $itemData['order_item_id'] = null;
        $itemData['sku_id'] = $detail['sku_id'];
        $itemData['qty'] = 1;
        $itemData['original_price'] = $detail['original_price'];
        $itemData['discounted_price'] = $detail['sale_price'];
        $itemData['product_id'] = (int)$detail['product_id'];
        $itemData['order_id'] = (int)$detail['order_line_id'];
        $itemData['order_type'] = null;
        $itemData['order_status'] = $this->getOrderStatus($detail['display_status'])['status'];
        $itemData['tracking_number'] = $detail['tracking_number'];

        return $itemData;
    }

    private function mapingOrderHeader($headerObject)
    {
        $platform = OnlineShop::where('name', 'TikTok')->first();
        $fixData = null;
        $fixData["create_time_online"] = date('Y-m-d H:i:s', (int)$headerObject['create_time'] / 1000);
        $fixData["update_time_online"] = date('Y-m-d H:i:s', (int)$headerObject['update_time']);
        $fixData["message_to_seller"] = $headerObject['buyer_message']??"";
        $fixData["order_no"] = $headerObject['order_id'];
        $fixData["order_status"] = $this->getOrderStatus($headerObject['order_status'])['status'];
        $fixData["tracking_number"] = $headerObject['tracking_number'] ?? "";
        $fixData["delivery_by"] = $headerObject['shipping_provider'] ?? "";
        $fixData["pickup_by"] = $headerObject['shipping_provider'] ?? "";
        $fixData["total_amount"] = 0;
        $fixData["total_qty"] = count($headerObject['order_line_list']);
        $fixData["items"] = $headerObject['order_line_list'];
        $fixData["status"] = 1;
        $fixData["online_shop_id"] = $platform->id;
        $fixData["order_id"] = (string)$headerObject['order_id'];
        $fixData["shipping_provider_type"] = $headerObject['delivery_option'] ?? "";
        $fixData["product_picture"] = null;
        $fixData["package_picture"] = null;
        $receipt = Receipt::where('number', $fixData["tracking_number"])->first();
        $fixData["scanned"] = isset($receipt) ? true : false;
        $fixData["show_request"] = isset($receipt) ? false : true;
        return $fixData;
    }

    private function getOrderStatus($status)
    {
        $orderStatus = array();
        switch ($status) {
            case 100:
                $orderStatus = [
                    "status" => "BELUM BAYAR",
                    "show_request" => false
                ];
                break;
            case 111:
                $orderStatus = [
                    "status" => "MENUNGGU DIPROSES",
                    "show_request" => false
                ];
                break;
            case 112:
                $orderStatus = [
                    "status" => "SIAP KIRIM",
                    "show_request" => false
                ];
                break;
            case 114:
                $orderStatus = [
                    "status" => "PENGIRIMAN PARSIAL",
                    "show_request" => false
                ];
                break;
            case 121:
                $orderStatus = [
                    "status" => "TRANSIT",
                    "show_request" => false
                ];
                break;
            case 122:
                $orderStatus = [
                    "status" => "TERKIRIM",
                    "show_request" => false
                ];
                break;
            case 130:
                $orderStatus = [
                    "status" => "SELESAI",
                    "show_request" => false
                ];
                break;
            case 140:
                $orderStatus = [
                    "status" => "BATAL",
                    "show_request" => false
                ];
                break;

            default:
                $orderStatus = [
                    "status" => (string)$status,
                    "show_request" => false
                ];
                break;
        }
        return $orderStatus;
    }

    public function getAccessToken()
    {
        $host = "https://auth.tiktok-shops.com";
        $path = "/api/v2/token/get";
        $url = $host . $path . '?app_key=' . $this->apiKey . '&app_secret=' . $this->apiSecret . '&auth_code=' . $this->code . '&grant_type=authorized_code';
        return $this->curlRequest($url, "GET");
    }
    public function getRefreshToken()
    {
        $accessAuth = TiktokAccessToken::all()->first();
        $host = "https://auth.tiktok-shops.com";
        $path = "/api/v2/token/refresh";
        $url = $host . $path . '?app_key=' . $accessAuth->api_key . '&app_secret=' . $accessAuth->api_secret . '&refresh_token=' . $accessAuth->refresh_token . '&grant_type=refresh_token';
        $response = $this->curlRequest($url, "GET");
        $auth = json_decode($response)->data;
        TiktokAccessToken::where('id', $accessAuth->id)->update([
            "refresh_token" => $auth->refresh_token,
            "access_token" => $auth->access_token,
            "expired_in" => $auth->access_token_expire_in,
        ]);
        return TiktokAccessToken::all()->first();
    }

    public function curlRequest($url, $method, $fieldRequest = null)
    {
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json'
            ),
            CURLOPT_POSTFIELDS => json_encode($fieldRequest),
        ));

        $response = curl_exec($curl);
        // if (json_decode($response)->error == "error_auth") {
        //     $access =  $this->getRefreshToken();
        // }
        curl_close($curl);
        return $response;
    }
}
