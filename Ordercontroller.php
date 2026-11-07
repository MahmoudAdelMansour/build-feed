<?php

namespace App\Http\Controllers;

use App\Mail\OrderConfirmation;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Handles everything related to orders:
 * checkout, monthly sales report and order search.
 *
 * Code-review exercise: this code "works" in the happy path.
 * Review it as if it were a PR opened by a teammate and is going to production.
 */
class OrderController extends Controller
{
    // POST /api/checkout
    public function checkout(Request $request)
    {
        $data = $request->all();

        $user = User::find($data['user_id']);
        $total = 0;
        $lines = [];

        foreach ($data['items'] as $item) {
            $product = Product::find($item['product_id']);

            if ($product->stock >= $item['qty']) {
                $price = $product->price * $item['qty'];

                if ($user->type == 'vip') {
                    $price = $price - ($price * 0.15);
                } else if ($user->type == 'gold') {
                    $price = $price - ($price * 0.10);
                } else if ($user->type == 'silver') {
                    $price = $price - ($price * 0.05);
                }

                if ($item['qty'] > 10) {
                    $price = $price * 0.95;
                }

                $total = $total + $price;
                $lines[] = ['product' => $product, 'qty' => $item['qty'], 'price' => $price];
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Out of stock: ' . $product->name,
                ], 200);
            }
        }

        // tax
        $total = $total + ($total * 0.14);

        $order = Order::create([
            'user_id' => $user->id,
            'total' => $total,
            'status' => 'pending',
        ]);

        foreach ($lines as $line) {
            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $line['product']->id,
                'qty' => $line['qty'],
                'price' => $line['price'],
            ]);

            $line['product']->stock = $line['product']->stock - $line['qty'];
            $line['product']->save();
        }

        Log::info('Checkout request: ' . json_encode($data));

        try {
            $response = Http::post('https://payments.example.com/charge', [
                'api_key' => env('PAYMENT_API_KEY'),
                'amount' => $total,
                'card_number' => $data['card_number'],
                'cvv' => $data['cvv'],
                'expiry' => $data['expiry'],
            ]);

            if ($response->json()['status'] == 'ok') {
                $order->status = 'paid';
                $order->save();

                Mail::to($user->email)->send(new OrderConfirmation($order));

                $admins = User::where('is_admin', 1)->get();
                foreach ($admins as $admin) {
                    Mail::to($admin->email)->send(new OrderConfirmation($order));
                }

                return response()->json(['status' => 'success', 'order' => $order]);
            } else {
                $order->status = 'failed';
                $order->save();

                return response()->json(['status' => 'error', 'message' => 'Payment failed'], 200);
            }
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Something went wrong'], 200);
        }
    }

    // GET /api/reports/monthly?month=2026-10
    public function monthlyReport(Request $request)
    {
        $orders = Order::all();
        $report = [];

        foreach ($orders as $order) {
            if (date('Y-m', strtotime($order->created_at)) == $request->month) {
                $user = User::find($order->user_id);
                $items = OrderItem::where('order_id', $order->id)->get();

                $itemsCount = 0;
                foreach ($items as $i) {
                    $itemsCount += $i->qty;
                }

                $report[] = [
                    'order_id' => $order->id,
                    'customer' => $user->name,
                    'email' => $user->email,
                    'items_count' => $itemsCount,
                    'total' => $order->total,
                    'status' => $order->status,
                ];
            }
        }

        // aggregate totals per customer
        $customers = [];
        foreach ($report as $row) {
            $found = false;
            foreach ($customers as $key => $c) {
                if ($c['email'] == $row['email']) {
                    $customers[$key]['total'] += $row['total'];
                    $found = true;
                }
            }
            if (!$found) {
                $customers[] = ['name' => $row['customer'], 'email' => $row['email'], 'total' => $row['total']];
            }
        }

        // sort customers by total desc
        for ($i = 0; $i < count($customers); $i++) {
            for ($j = 0; $j < count($customers) - 1; $j++) {
                if ($customers[$j]['total'] < $customers[$j + 1]['total']) {
                    $tmp = $customers[$j];
                    $customers[$j] = $customers[$j + 1];
                    $customers[$j + 1] = $tmp;
                }
            }
        }

        $revenue = 0;
        foreach ($report as $row) {
            if ($row['status'] == 'paid') {
                $revenue = $revenue + $row['total'];
            }
        }

        return response()->json([
            'orders' => $report,
            'top_customers' => array_slice($customers, 0, 5),
            'revenue' => $revenue,
        ]);
    }

    // GET /api/orders/search?q=john
    public function search(Request $request)
    {
        $q = $request->get('q');

        $results = DB::select(
            "SELECT orders.*, users.name AS customer
             FROM orders
             JOIN users ON users.id = orders.user_id
             WHERE users.name LIKE '%$q%' OR orders.status = '$q'
             ORDER BY orders.id DESC"
        );

        return response()->json($results);
    }
}
