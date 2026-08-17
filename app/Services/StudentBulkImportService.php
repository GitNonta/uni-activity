<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PhpOffice\PhpSpreadsheet\IOFactory;

class StudentBulkImportService
{
    /**
     * ประมวลผลไฟล์ CSV หรือ Excel เพื่อนำเข้านักศึกษาแบบ Bulk Upsert
     *
     * @return array{total_rows: int, created_count: int, updated_count: int, skipped_count: int, errors: array<string>}
     */
    public function importFile(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());

        $rows = in_array($extension, ['csv', 'txt'], true)
            ? $this->parseCsv((string) $file->getRealPath())
            : $this->parseSpreadsheet((string) $file->getRealPath());

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];

        if (empty($rows)) {
            return [
                'total_rows'    => 0,
                'created_count' => 0,
                'updated_count' => 0,
                'skipped_count' => 0,
                'errors'        => ['ไฟล์ไม่มีข้อมูล หรือโครงสร้างไม่ถูกต้อง'],
            ];
        }

        // Header mapping (case-insensitive & whitespace trimmed)
        $header = array_map(
            fn ($h): string => strtolower(trim((string) preg_replace('/^\xEF\xBB\xBF/', '', (string) $h))),
            array_shift($rows) ?? []
        );

        $idxStudentId  = $this->findColumnIndex($header, ['student_id', 'student id', 'รหัสนักศึกษา', 'รหัสประจำตัว', 'id']);
        $idxFullName   = $this->findColumnIndex($header, ['full_name', 'name', 'ชื่อ-นามสกุล', 'ชื่อ นามสกุล', 'ชื่อ']);
        $idxEmail      = $this->findColumnIndex($header, ['email', 'e-mail', 'อีเมล']);
        $idxFaculty    = $this->findColumnIndex($header, ['faculty', 'คณะ']);
        $idxDepartment = $this->findColumnIndex($header, ['department', 'สาขาวิชา', 'ภาควิชา', 'สาขา']);
        $idxYear       = $this->findColumnIndex($header, ['year', 'ชั้นปี', 'ปี']);
        $idxProgram    = $this->findColumnIndex($header, ['program', 'หลักสูตร', 'ประเภท']);

        if ($idxStudentId === null || $idxFullName === null) {
            return [
                'total_rows'    => count($rows),
                'created_count' => 0,
                'updated_count' => 0,
                'skipped_count' => count($rows),
                'errors'        => ['ไม่พบคอลัมน์ "student_id" (รหัสนักศึกษา) หรือ "full_name" (ชื่อ-นามสกุล) ในแถวหัวตาราง'],
            ];
        }

        try {
            DB::transaction(function () use ($rows, $idxStudentId, $idxFullName, $idxEmail, $idxFaculty, $idxDepartment, $idxYear, $idxProgram, &$created, &$updated, &$skipped, &$errors): void {
                foreach ($rows as $lineNum => $row) {
                $rowNum = $lineNum + 2; // +1 for 0-index, +1 for header
                if (empty($row) || (count($row) === 1 && trim((string)$row[0]) === '')) {
                    continue;
                }

                $studentId = trim((string) ($row[$idxStudentId] ?? ''));
                $fullName  = trim((string) ($row[$idxFullName] ?? ''));

                if ($studentId === '' || $fullName === '') {
                    $skipped++;
                    $errors[] = "แถวที่ {$rowNum}: รหัสนักศึกษาหรือชื่อว่างเปล่า";
                    continue;
                }

                // Clean student ID (keep alphanumeric)
                $studentId = preg_replace('/[^a-zA-Z0-9]/', '', $studentId);

                $email = $idxEmail !== null ? trim((string) ($row[$idxEmail] ?? '')) : '';
                if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $email = 's' . $studentId . '@pkru.ac.th';
                }

                $faculty    = $idxFaculty !== null ? trim((string) ($row[$idxFaculty] ?? '')) : null;
                $department = $idxDepartment !== null ? trim((string) ($row[$idxDepartment] ?? '')) : null;
                $year       = $idxYear !== null ? (int) trim((string) ($row[$idxYear] ?? '1')) : 1;
                if ($year < 1 || $year > 8) $year = 1;

                $program    = $idxProgram !== null ? trim((string) ($row[$idxProgram] ?? 'ภาคปกติ')) : 'ภาคปกติ';

                $existingUser = User::where('student_id', $studentId)->first();

                if ($existingUser) {
                    $existingUser->update([
                        'student_id' => $studentId,
                        'full_name'  => $fullName,
                        'faculty'    => $faculty ?: $existingUser->faculty,
                        'department' => $department ?: $existingUser->department,
                        'year'       => $year ?: $existingUser->year,
                        'program'    => $program ?: $existingUser->program,
                    ]);
                    $updated++;
                } else {
                    User::create([
                        'student_id' => $studentId,
                        'full_name'  => $fullName,
                        'email'      => $email,
                        'password'   => Hash::make($studentId), // default password as student_id
                        'role'       => 'student',
                        'faculty'    => $faculty,
                        'department' => $department,
                        'year'       => $year,
                        'program'    => $program,
                        'is_active'  => true,
                    ]);
                    $created++;
                }
                }
            });
        } catch (\Throwable $e) {
            return [
                'total_rows'    => count($rows),
                'created_count' => 0,
                'updated_count' => 0,
                'skipped_count' => count($rows),
                'errors'        => ['เกิดข้อผิดพลาดในการบันทึกฐานข้อมูล: ' . $e->getMessage()],
            ];
        }

        return [
            'total_rows'    => count($rows),
            'created_count' => $created,
            'updated_count' => $updated,
            'skipped_count' => $skipped,
            'errors'        => array_slice($errors, 0, 20), // return top 20 error details
        ];
    }

    /**
     * สร้างไฟล์ CSV Template ตัวอย่างสำหรับดาวน์โหลด
     */
    public function generateTemplateCsv(): string
    {
        $headers = ['student_id', 'full_name', 'email', 'faculty', 'department', 'year', 'program'];
        $sampleData = [
            ['6710880001', 'นายสมชาย ใจดี', 's6710880001@pkru.ac.th', 'คณะวิทยาศาสตร์และเทคโนโลยี', 'สาขาวิชาเทคโนโลยีสารสนเทศ', '1', 'ภาคปกติ'],
            ['6710880002', 'นางสาวสมหญิง รักเรียน', 's6710880002@pkru.ac.th', 'คณะครุศาสตร์', 'สาขาวิชาภาษาอังกฤษ', '1', 'ภาคปกติ'],
            ['6610880003', 'นายวีระ มุ่งมั่น', 's6610880003@pkru.ac.th', 'คณะวิทยาการจัดการ', 'สาขาวิชาการตลาด', '2', 'ภาคพิเศษ'],
        ];

        // UTF-8 BOM for Thai language Excel support
        $output = "\xEF\xBB\xBF";
        $output .= implode(',', $headers) . "\n";
        foreach ($sampleData as $row) {
            $output .= implode(',', array_map(fn($v) => '"' . str_replace('"', '""', $v) . '"', $row)) . "\n";
        }

        return $output;
    }

    private function parseCsv(string $filePath): array
    {
        $rows = [];
        if (($handle = fopen($filePath, 'r')) !== false) {
            while (($data = fgetcsv($handle, 2000, ',')) !== false) {
                // If single column with tab or semicolon
                if (count($data) === 1 && str_contains((string)$data[0], "\t")) {
                    $data = explode("\t", $data[0]);
                } elseif (count($data) === 1 && str_contains((string)$data[0], ";")) {
                    $data = explode(";", $data[0]);
                }
                $rows[] = $data;
            }
            fclose($handle);
        }
        return $rows;
    }

    /** @return array<int, array<int, mixed>> */
    private function parseSpreadsheet(string $filePath): array
    {
        return IOFactory::load($filePath)
            ->getActiveSheet()
            ->toArray(null, true, true, false);
    }

    private function findColumnIndex(array $header, array $candidates): ?int
    {
        foreach ($candidates as $candidate) {
            $index = array_search(strtolower($candidate), $header, true);
            if ($index !== false) {
                return (int) $index;
            }
        }
        return null;
    }
}
