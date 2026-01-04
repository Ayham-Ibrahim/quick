<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\Cart\CartService;
use App\Http\Resources\CartResource;

class CartController extends Controller
{
    protected CartService $cartService;

    public function __construct(CartService $cartService)
    {
        $this->cartService = $cartService;
    }

    /**
     * Get current user's cart
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $cart = $this->cartService->getOrCreateCart();

        return $this->success(
            new CartResource($cart->load([
                'items.product.store',
                'items.product.images',
                'items.variant.attributes.attribute',
                'items.variant.attributes.value',
            ])),
            'تم جلب السلة بنجاح'
        );
    }

    /**
     * Get cart summary (for header/quick view)
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function summary()
    {
        $summary = $this->cartService->getCartSummary();

        return $this->success($summary, 'ملخص السلة');
    }

    /**
     * Add item to cart
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function addItem(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'product_variant_id' => 'nullable|exists:product_variants,id',
            'quantity' => 'nullable|integer|min:1|max:100',
            'notes' => 'nullable|string|max:500',
        ], [
            'product_id.required' => 'يجب تحديد المنتج',
            'product_id.exists' => 'المنتج غير موجود',
            'product_variant_id.exists' => 'المتغير غير موجود',
            'quantity.min' => 'الكمية يجب أن تكون 1 على الأقل',
            'quantity.max' => 'الكمية لا يمكن أن تتجاوز 100',
        ]);

        $cart = $this->cartService->addItem($validated);

        return $this->success(
            new CartResource($cart->load([
                'items.product.store',
                'items.product.images',
                'items.variant.attributes.attribute',
                'items.variant.attributes.value',
            ])),
            'تمت إضافة المنتج إلى السلة',
            201
        );
    }

    /**
     * Update cart item quantity
     *
     * @param Request $request
     * @param int $itemId
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateItem(Request $request, int $itemId)
    {
        $validated = $request->validate([
            'quantity' => 'required|integer|min:0|max:100',
        ], [
            'quantity.required' => 'يجب تحديد الكمية',
            'quantity.min' => 'الكمية لا يمكن أن تكون سالبة',
            'quantity.max' => 'الكمية لا يمكن أن تتجاوز 100',
        ]);

        $cart = $this->cartService->updateItemQuantity($itemId, $validated['quantity']);

        $message = $validated['quantity'] === 0
            ? 'تم حذف المنتج من السلة'
            : 'تم تحديث الكمية بنجاح';

        return $this->success(
            new CartResource($cart->load([
                'items.product.store',
                'items.product.images',
                'items.variant.attributes.attribute',
                'items.variant.attributes.value',
            ])),
            $message
        );
    }

    /**
     * Remove item from cart
     *
     * @param int $itemId
     * @return \Illuminate\Http\JsonResponse
     */
    public function removeItem(int $itemId)
    {
        $cart = $this->cartService->removeItem($itemId);

        return $this->success(
            new CartResource($cart->load([
                'items.product.store',
                'items.product.images',
                'items.variant.attributes.attribute',
                'items.variant.attributes.value',
            ])),
            'تم حذف المنتج من السلة'
        );
    }

    /**
     * Clear all items from cart
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function clear()
    {
        $cart = $this->cartService->clearCart();

        return $this->success(
            new CartResource($cart),
            'تم تفريغ السلة بنجاح'
        );
    }

    /**
     * Validate cart before checkout
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function validate()
    {
        $issues = $this->cartService->validateCart();

        if (empty($issues)) {
            return $this->success([
                'is_valid' => true,
                'issues' => [],
            ], 'السلة جاهزة للدفع');
        }

        return $this->success([
            'is_valid' => false,
            'issues' => $issues,
        ], 'يوجد مشاكل في السلة تحتاج إلى معالجة');
    }

    /**
     * Sync all cart prices to current prices
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function syncPrices()
    {
        $cart = $this->cartService->syncPrices();

        return $this->success(
            new CartResource($cart->load([
                'items.product.store',
                'items.product.images',
                'items.variant.attributes.attribute',
                'items.variant.attributes.value',
            ])),
            'تم تحديث الأسعار بنجاح'
        );
    }
}
