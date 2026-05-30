<?php

namespace App\Services\Pricing;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProfitRatios;
use App\Services\Service;

class DynamicPricingService extends Service
{
    private const DEFAULT_ROUNDING_STEP_SYP = 50.0;
    private const USD_SCALE = 6;
    private const PRICE_SCALE = 2;

    public function getExchangeRate(): float
    {
        $exchangeRate = (float) (ProfitRatios::getValueByTag(ProfitRatios::TAG_EXCHANGE_RATE) ?? 0);

        if ($exchangeRate <= 0) {
            $this->throwExceptionJson('سعر الصرف الحالي غير صالح', 422);
        }

        return $exchangeRate;
    }

    public function calculateBasePriceUsd(float $priceSyp, ?float $exchangeRate = null): float
    {
        if ($priceSyp <= 0) {
            $this->throwExceptionJson('سعر المنتج يجب أن يكون أكبر من الصفر', 422);
        }

        $effectiveExchangeRate = $exchangeRate ?? $this->getExchangeRate();

        return round($priceSyp / $effectiveExchangeRate, self::USD_SCALE);
    }

    public function calculateSyncedPriceSyp(float $basePriceUsd, ?float $exchangeRate = null): float
    {
        if ($basePriceUsd <= 0) {
            $this->throwExceptionJson('السعر المرجعي بالدولار يجب أن يكون أكبر من الصفر', 422);
        }

        $effectiveExchangeRate = $exchangeRate ?? $this->getExchangeRate();
        $rawPrice = $basePriceUsd * $effectiveExchangeRate;

        return $this->roundSypPrice($rawPrice);
    }

    public function prepareProductPricingPayload(array $productData, bool $hasDirectPrice, ?Product $product = null): array
    {
        $syncEnabled = array_key_exists('sync_enabled', $productData)
            ? (bool) $productData['sync_enabled']
            : (bool) ($product?->sync_enabled ?? false);

        if (!$hasDirectPrice) {
            return [
                'current_price' => null,
                'base_price_usd' => null,
                'sync_enabled' => $syncEnabled,
            ];
        }

        $hasExplicitPrice = array_key_exists('current_price', $productData);
        $wasSynced = (bool) ($product?->sync_enabled ?? false);

        $price = $hasExplicitPrice
            ? $productData['current_price']
            : $product?->current_price;

        if ($syncEnabled) {
            if (!$hasExplicitPrice && $wasSynced && $product?->base_price_usd) {
                return [
                    'current_price' => round((float) $product->current_price, self::PRICE_SCALE),
                    'base_price_usd' => round((float) $product->base_price_usd, self::USD_SCALE),
                    'sync_enabled' => true,
                ];
            }

            if ($price === null || (float) $price <= 0) {
                $this->throwExceptionJson('سعر المنتج المرتبط بسعر الصرف يجب أن يكون أكبر من الصفر', 422);
            }

            return [
                'current_price' => round((float) $price, self::PRICE_SCALE),
                'base_price_usd' => $this->calculateBasePriceUsd((float) $price),
                'sync_enabled' => true,
            ];
        }

        return [
            'current_price' => $price === null ? null : round((float) $price, self::PRICE_SCALE),
            'base_price_usd' => null,
            'sync_enabled' => false,
        ];
    }

    public function prepareVariantPricingPayload(array $variantData, bool $syncEnabled, bool $hasDirectVariantPrice, ?ProductVariant $variant = null): array
    {
        $hasExplicitPrice = array_key_exists('price', $variantData);
        $price = $hasExplicitPrice
            ? $variantData['price']
            : $variant?->price;

        if (!$hasDirectVariantPrice) {
            return [
                'price' => $price === null ? null : round((float) $price, self::PRICE_SCALE),
                'base_price_usd' => null,
            ];
        }

        if ($syncEnabled) {
            if (!$hasExplicitPrice && $variant?->base_price_usd) {
                return [
                    'price' => round((float) $variant->price, self::PRICE_SCALE),
                    'base_price_usd' => round((float) $variant->base_price_usd, self::USD_SCALE),
                ];
            }

            if ($price === null || (float) $price <= 0) {
                $this->throwExceptionJson('سعر المتغير المرتبط بسعر الصرف يجب أن يكون أكبر من الصفر', 422);
            }

            return [
                'price' => round((float) $price, self::PRICE_SCALE),
                'base_price_usd' => $this->calculateBasePriceUsd((float) $price),
            ];
        }

        return [
            'price' => $price === null ? null : round((float) $price, self::PRICE_SCALE),
            'base_price_usd' => null,
        ];
    }

    public function repriceProduct(Product $product, ?float $exchangeRate = null): bool
    {
        if (!$product->sync_enabled || !$product->base_price_usd) {
            return false;
        }

        $newPrice = $this->calculateSyncedPriceSyp((float) $product->base_price_usd, $exchangeRate);

        if ((float) $product->current_price === $newPrice) {
            return false;
        }

        $product->forceFill([
            'current_price' => $newPrice,
        ])->save();

        return true;
    }

    public function repriceVariant(ProductVariant $variant, ?float $exchangeRate = null): bool
    {
        if (!$variant->base_price_usd) {
            return false;
        }

        $newPrice = $this->calculateSyncedPriceSyp((float) $variant->base_price_usd, $exchangeRate);

        if ((float) $variant->price === $newPrice) {
            return false;
        }

        $variant->forceFill([
            'price' => $newPrice,
        ])->save();

        return true;
    }

    public function isExchangeRateCurrent(float $expectedExchangeRate): bool
    {
        return abs($this->getExchangeRate() - $expectedExchangeRate) < 0.0001;
    }

    private function roundSypPrice(float $price): float
    {
        $rounded = round(round($price / self::DEFAULT_ROUNDING_STEP_SYP) * self::DEFAULT_ROUNDING_STEP_SYP, self::PRICE_SCALE);

        if ($rounded <= 0 && $price > 0) {
            return self::DEFAULT_ROUNDING_STEP_SYP;
        }

        return $rounded;
    }
}