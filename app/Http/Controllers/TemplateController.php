<?php

namespace App\Http\Controllers;

use App\Services\TemplateService;
use Illuminate\Http\JsonResponse;

class TemplateController extends Controller
{
    public function __construct(
        private TemplateService $templateService
    ) {}

    public function index(): JsonResponse
    {
        return response()->json($this->templateService->listTemplates());
    }
}
