<?php

namespace App\Services;

use App\Models\Complaint;
use App\Models\UserManagement\User;
use App\Services\Service;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ComplaintService extends Service
{
    public function paginate($perPage = 10)
    {
        $complaints = Complaint::with(['user'])->paginate($perPage);
        return $complaints;
    }

    public function find(Complaint $complaint)
    {
        $complaint->load(['user']);
        return $complaint;
    }

    public function store(array $data)
    {
        try {
            $user = Auth::guard('api')->user();

            if (! $user instanceof User) {
                throw new \Exception('غير مصرح لك بالقيام بهذا الإجراء.');
            }

            if ($data['image']) {
                $image = FileStorage::storeFile($data['image'], 'Complaint', 'img');
            }

            $complaint = Complaint::create([
                'content' => $data['content'],
                'image' => $image ?? null,
                'user_id' => $user->id,
            ]);

            return $complaint->load('user');
        } catch (\Throwable $th) {
            Log::error($th);

            if ($th instanceof HttpResponseException) {
                throw $th;
            }

            $this->throwExceptionJson();
        }
    }
}
