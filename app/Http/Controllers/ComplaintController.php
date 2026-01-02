<?php

namespace App\Http\Controllers;

use App\Http\Requests\Complaint\StoreComplaintRequest;
use App\Http\Requests\Complaint\UpdateComplaintRequest;
use App\Models\Complaint;
use App\Services\ComplaintService;
use App\Services\FileStorage;
use Illuminate\Http\Request;

class ComplaintController extends Controller
{

    protected $complaintService;

    public function __construct(ComplaintService $complaintService)
    {
        $this->complaintService = $complaintService;
    }
    /**
     * Display a listing of the resource.
     */
    public function index($perPage = 10)
    {
        $complaints = $this->complaintService->paginate();


        return $this->paginate(
            $complaints,
            "تم جلب البيانات بنجاح"
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreComplaintRequest $request)
    {
        // return $request;
        $complaint = $this->complaintService->store($request->validated());

        return $this->success(
            $complaint,
            'تم إرسال الشكوى بنجاح',
            201
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(Complaint $complaint)
    {
        $complaint = $this->complaintService->find($complaint);
        return $this->success(
            $complaint,
            'تم جلب تفاصيل الشكوى بنجاح'
        );
    }
}
