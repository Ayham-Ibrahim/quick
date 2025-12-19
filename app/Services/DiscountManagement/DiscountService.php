<?php

namespace App\Services\DiscountManagement;

use App\Models\DiscountManagement\Discount;
use App\Services\Service;

class DiscountService extends Service
{
    public function create(array $data)
    {
        try {
            return Discount::create($data);
        } catch (\Throwable $e) {
            $this->throwExceptionJson(
                'فشل إنشاء الخصم',
                500,
                $e->getMessage()
            );
        }
    }

    public function update(Discount $discount, array $data)
    {
        try {
            $discount->update($data);
            return $discount->fresh();
        } catch (\Throwable $e) {
            $this->throwExceptionJson(
                'فشل تحديث الخصم',
                500,
                $e->getMessage()
            );
        }
    }

    public function delete(Discount $discount): void
    {
        try {
            $discount->delete();
        } catch (\Throwable $e) {
            $this->throwExceptionJson(
                'فشل حذف الخصم',
                500,
                $e->getMessage()
            );
        }
    }
}
