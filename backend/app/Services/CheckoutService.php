<?php

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Exceptions\InvalidPaymentException;
use App\Models\IncomeEntry;
use App\Models\Order;
use App\Models\Product;
use App\Models\Recipe;
use App\Models\StockMovement;
use App\Models\Transaction;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CheckoutService
{
    public function validateStock(Order $order): void
    {
        $insufficientItems = [];

        foreach ($order->orderItems as $item) {
            $recipes = Recipe::where('menu_product_id', $item->product_id)->get();

            // If no recipes, skip stock check for this item
            if ($recipes->isEmpty()) {
                continue;
            }

            foreach ($recipes as $recipe) {
                $required = $recipe->quantity_required * $item->quantity;
                $rawMaterial = Product::find($recipe->raw_material_id);

                if ($rawMaterial && $rawMaterial->stock < $required) {
                    $insufficientItems[] = [
                        'raw_material_id'   => $rawMaterial->id,
                        'raw_material_name' => $rawMaterial->name,
                        'required'          => $required,
                        'available'         => $rawMaterial->stock,
                    ];
                }
            }
        }

        if (!empty($insufficientItems)) {
            throw new InsufficientStockException($insufficientItems);
        }
    }

    public function processCheckout(Order $order, array $paymentData): Transaction
    {
        $totalAmount = $order->orderItems->sum('subtotal');

        if ($paymentData['paid_amount'] < $totalAmount) {
            throw new InvalidPaymentException($paymentData['paid_amount'], $totalAmount);
        }

        $this->validateStock($order);

        return DB::transaction(function () use ($order, $paymentData, $totalAmount) {
            $transaction = Transaction::create([
                'transaction_number' => 'TRX-' . now()->format('YmdHis') . '-' . $order->id,
                'order_id'           => $order->id,
                'payment_method'     => $paymentData['payment_method'] ?? 'cash',
                'total_amount'       => $totalAmount,
                'paid_amount'        => $paymentData['paid_amount'],
                'change_amount'      => $paymentData['paid_amount'] - $totalAmount,
                'status'             => 'success',
                'processed_by'       => Auth::id() ?? null,
            ]);

            $this->deductRawMaterialStock($order, $transaction);

            $order->update(['status' => 'completed']);

            if ($order->table_id && in_array($order->order_type, ['dine_in', 'self_order'])) {
                $order->table->update(['status' => 'available']);
            }

            IncomeEntry::create([
                'date'           => now()->toDateString(),
                'amount'         => $totalAmount,
                'category'       => 'Penjualan',
                'source'         => 'pos',
                'status'         => 'pending',
                'created_by'     => Auth::id() ?? null,
                'transaction_id' => $transaction->id,
            ]);

            return $transaction;
        });
    }

    private function deductRawMaterialStock(Order $order, Transaction $transaction): void
    {
        foreach ($order->orderItems as $item) {
            $recipes = Recipe::where('menu_product_id', $item->product_id)->get();

            foreach ($recipes as $recipe) {
                $rawMaterial = Product::lockForUpdate()->find($recipe->raw_material_id);

                if (!$rawMaterial) {
                    continue;
                }

                $deductQty   = $recipe->quantity_required * $item->quantity;
                $stockBefore = $rawMaterial->stock;

                $rawMaterial->decrement('stock', $deductQty);

                StockMovement::create([
                    'product_id'     => $rawMaterial->id,
                    'type'           => 'out',
                    'quantity'       => $deductQty,
                    'stock_before'   => $stockBefore,
                    'stock_after'    => $stockBefore - $deductQty,
                    'reference_type' => 'transaction',
                    'reference_id'   => $transaction->id,
                    'notes'          => "Checkout order #{$order->order_number}",
                    'created_by'     => Auth::id() ?? null,
                ]);
            }
        }
    }
}
