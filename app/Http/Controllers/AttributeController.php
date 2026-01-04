<?php

namespace App\Http\Controllers;

use App\Http\Requests\Attribute\StoreAttributeRequest;
use App\Http\Requests\Attribute\UpdateAttributeRequest;
use App\Models\Attribute\Attribute;
use App\Models\Attribute\AttributeValue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AttributeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $data = Attribute::select('id', 'name')->get();
        return $this->success($data, 'تم جلب قائمة الخصائص بنجاح', 201);
    }

    public function getValue(Attribute $attribute){
        $data = AttributeValue::select('id', 'value')->where('attribute_id',$attribute->id)->get();
        return $this->success($data, 'تم جلب قيم الخاصية بنجاح',201);
    }

    /**
     * Store a newly created resource in storage.
     */
   public function store(StoreAttributeRequest $request)
    {
        DB::beginTransaction();
        
        try {
            $data = $request->validated();
            
            $attribute = Attribute::create([
                'name' => trim($data['name']),
                'slug' => \Illuminate\Support\Str::slug($data['name']),
            ]);

            $attribute->values()->createMany(array_map(function($value) {
                return [
                    'value' => trim($value),
                    'slug' => \Illuminate\Support\Str::slug($value),
                ];
            }, $data['value']));
            
            DB::commit();
            
            
            return $this->success([
                'attribute' => $attribute->load('values')
            ], 'تم إنشاء الخاصية بنجاح', 201);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('فشل إنشاء الخاصية: ' . $e->getMessage(), [
                'data' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->error('حدث خطأ أثناء إنشاء الخاصية: ' . $e->getMessage(), 500);
        }
    }
    /**
     * Display the specified resource.
     */
    public function show(Attribute $attribute)
    {
        $attribute->load('values:id,attribute_id,value');
        return $this->success([
            'id' => $attribute->id,
            'name' => $attribute->name,
            'values' => $attribute->values
        ], 'تم جلب الخاصية بنجاح', 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateAttributeRequest $request, Attribute $attribute)
    {
    DB::beginTransaction();
    
    try {
        $data = $request->validated();
        
        $attribute->update([
            'name' => trim($data['name']) ?? $attribute->name,
        ]);
        
        if (isset($data['value'])) {
            $values = array_map(function($value) use ($attribute) {
                return [
                    'attribute_id' => $attribute->id,
                    'value' => trim($value),
                    'slug' => \Illuminate\Support\Str::slug($value),
                ];
            }, $data['value']);
            $attribute->values()->upsert(
                $values,
                ['attribute_id', 'value'],
                ['slug']
            );
        }
        
        DB::commit();
        
        return $this->success(
            $attribute->load('values'), 
            'تم تحديث الخاصية بنجاح', 
            200
        );
        
    } catch (\Exception $e) {
        DB::rollBack();
        
        Log::error('فشل تحديث الخاصية: ' . $e->getMessage(), [
            'attribute_id' => $attribute->id,
            'data' => $request->all()
        ]);
        
        return $this->error('حدث خطأ أثناء تحديث الخاصية'. $e->getMessage(), 500);
    }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Attribute $attribute)
    {
        $attribute->delete();
        return $this->success(
            null,
            'تم حذف الخاصية بنجاح',
            204
        );
    }

    public function destroyValue(AttributeValue $attributevalue)
    {
        $attributevalue->delete();
        return $this->success(
            null,
            'تم حذف القيمة بنجاح',
            204
        );
    }

    public function updateValue(AttributeValue $attributevalue, Request $request){

        try{
        $attributevalue->update([
            'value' => trim($request->value) ?? $attributevalue->value,
        ]);

        return $this->success(
            $attributevalue, 
            'تم تحديث القيمة بنجاح', 
            200
        );
        } catch (\Exception $e) {
        DB::rollBack();
        
        Log::error('فشل تحديث الخاصية: ' . $e->getMessage(), [
            'attributevalue_id' => $attributevalue->id,
            'data' => $request->all()
        ]);
        
        return $this->error('حدث خطأ أثناء تحديث القيمة'. $e->getMessage(), 500);
    }
    }
}
