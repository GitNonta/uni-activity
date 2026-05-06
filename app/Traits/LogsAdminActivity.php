<?php

namespace App\Traits;

use App\Models\AdminAuditLog;

/**
 * Trait สำหรับบันทึก Audit Log ของ Admin
 * ใช้ใน Controller ฝั่ง Admin เพื่อบันทึกทุกการกระทำอัตโนมัติ
 */
trait LogsAdminActivity
{
    /**
     * บันทึก audit log
     *
     * @param  string      $action      ประเภท: create, update, delete, approve, reject, toggle
     * @param  string      $description คำอธิบายสิ่งที่ทำ
     * @param  string|null $modelType   ชื่อ Model เช่น App\Models\Activity
     * @param  int|null    $modelId     ID ของ record
     * @param  array|null  $oldValues   ค่าเดิม
     * @param  array|null  $newValues   ค่าใหม่
     */
    protected function auditLog(
        string $action,
        string $description,
        ?string $modelType = null,
        ?int $modelId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
    ): void {
        AdminAuditLog::create([
            'user_id'     => auth()->id(),
            'action'      => $action,
            'model_type'  => $modelType,
            'model_id'    => $modelId,
            'description' => $description,
            'old_values'  => $oldValues,
            'new_values'  => $newValues,
            'ip_address'  => request()->ip(),
            'user_agent'  => request()->userAgent(),
        ]);
    }

    /** บันทึก log การสร้าง */
    protected function auditCreate($model, string $description): void
    {
        $this->auditLog('create', $description, get_class($model), $model->id, null, $model->toArray());
    }

    /** บันทึก log การแก้ไข */
    protected function auditUpdate($model, array $oldValues, string $description): void
    {
        $changed = array_intersect_key($model->toArray(), $oldValues);
        $this->auditLog('update', $description, get_class($model), $model->id, $oldValues, $changed);
    }

    /** บันทึก log การลบ */
    protected function auditDelete($model, string $description): void
    {
        $this->auditLog('delete', $description, get_class($model), $model->id, $model->toArray(), null);
    }

    /** บันทึก log การอนุมัติ */
    protected function auditApprove($model, string $description): void
    {
        $this->auditLog('approve', $description, get_class($model), $model->id);
    }

    /** บันทึก log การปฏิเสธ */
    protected function auditReject($model, string $description): void
    {
        $this->auditLog('reject', $description, get_class($model), $model->id);
    }

    /** บันทึก log การสลับสถานะ */
    protected function auditToggle($model, string $description): void
    {
        $this->auditLog('toggle', $description, get_class($model), $model->id);
    }
}
