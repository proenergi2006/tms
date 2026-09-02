<?php

namespace App\Http\Controllers;

use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use Illuminate\Http\Request;

/**
 * Audit trail — Architecture Document Bagian 7.3, NFR-05 Auditability
 * (PRD Bagian 7). Hanya baca; baris ditulis dari titik-titik aksi terkait
 * approval/biaya (lihat ApprovalWorkflowService::log()).
 */
class AuditLogController extends Controller
{
    public function index(Request $httpRequest)
    {
        $logs = AuditLog::query()
            ->with('actor')
            ->when($httpRequest->filled('entity_type'), fn ($q) => $q->where('entity_type', $httpRequest->query('entity_type')))
            ->when($httpRequest->filled('action'), fn ($q) => $q->where('action', $httpRequest->query('action')))
            ->when($httpRequest->filled('actor_id'), fn ($q) => $q->where('actor_id', $httpRequest->query('actor_id')))
            ->when($httpRequest->filled('date_from'), fn ($q) => $q->whereDate('created_at', '>=', $httpRequest->query('date_from')))
            ->when($httpRequest->filled('date_to'), fn ($q) => $q->whereDate('created_at', '<=', $httpRequest->query('date_to')))
            ->latest()
            ->paginate($httpRequest->integer('per_page', 20));

        return AuditLogResource::collection($logs);
    }
}
