<?php
declare(strict_types=1);

/**
 * Import old data from MySQL backup into PostgreSQL using Laravel Eloquent.
 * Usage: php sql/import_old_data.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Activity;
use App\Models\ActivityCategory;
use App\Models\Registration;
use App\Models\Attendance;
use App\Models\ActivityFeedback;
use App\Models\Announcement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

$backupFile = __DIR__ . '/backup_utf8.sql';
$contents = file_get_contents($backupFile);

// Remove MySQL directives
$contents = preg_replace('/\/\*!.*?\*\//s', '', $contents);
$contents = preg_replace('/--.*$/m', '', $contents);

echo "=== Importing old data from backup ===\n\n";

// ─── 1. Import Users ───
echo "1. Importing users...\n";
preg_match_all("/INSERT INTO `users` VALUES\s*(.+?);/s", $contents, $m);
$count = 0;
foreach ($m[1] as $row) {
    $rows = preg_split('/\)\s*,\s*\(/', $row);
    foreach ($rows as $r) {
        $r = trim($r, '()');
        // Parse MySQL VALUES: id, student_id, email, ..., password, full_name, faculty, department, year, role, is_active, ...
        $vals = pg_split_values($r);
        if (!$vals || count($vals) < 15) continue;

        $id           = (int) $vals[0];
        $studentId    = unquote($vals[1]);
        $email        = unquote($vals[2]);
        $password     = unquote($vals[7]);  // MySQL column 7 = password
        $fullName     = unquote($vals[8]);
        $faculty      = unquote($vals[9]);
        $department   = unquote($vals[10]);
        $year         = (int) $vals[11];
        $role         = unquote($vals[13]);
        $isActive     = unquote($vals[14]);

        if (!$fullName && !$email) continue;

        $data = [
            'id'           => $id,
            'student_id'   => $studentId,
            'email'        => $email,
            'full_name'    => $fullName,
            'faculty'      => $faculty,
            'department'   => $department,
            'year'         => $year ?: null,
            'role'         => $role ?: 'student',
            'is_active'    => in_array($isActive, ['1', 'true', true]) ? true : false,
        ];

        // Only set password if it looks like a bcrypt hash
        if ($password && str_starts_with($password, '$2y$')) {
            $data['password'] = $password;
        }

        try {
            User::updateOrCreate(['id' => $id], $data);
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            // If email already exists, update by email instead
            User::where('email', $email)->update($data);
        }
        $count++;
    }
}
echo "   Users imported: {$count}\n";

// ─── 2. Import Activity Categories ───
echo "2. Importing activity_categories...\n";
preg_match_all("/INSERT INTO `activity_categories` VALUES\s*(.+?);/s", $contents, $m);
$count = 0;
foreach ($m[1] as $row) {
    $rows = preg_split('/\)\s*,\s*\(/', $row);
    foreach ($rows as $r) {
        $r = trim($r, '()');
        $vals = pg_split_values($r);
        if (!$vals || count($vals) < 2) continue;

        ActivityCategory::updateOrCreate(
            ['id' => (int) $vals[0]],
            [
                'name'           => unquote($vals[1]),
                'description'    => unquote($vals[2]),
                'required_hours' => (int) ($vals[3] ?? 0),
            ]
        );
        $count++;
    }
}
echo "   Categories imported: {$count}\n";

// ─── 3. Import Activities ───
echo "3. Importing activities...\n";
preg_match_all("/INSERT INTO `activities` VALUES\s*(.+?);/s", $contents, $m);
$count = 0;
foreach ($m[1] as $row) {
    $rows = preg_split('/\)\s*,\s*\(/', $row);
    foreach ($rows as $r) {
        $r = trim($r, '()');
        $vals = pg_split_values($r);
        if (!$vals || count($vals) < 10) continue;

        // MySQL column order (from CREATE TABLE):
        // 0:id 1:title 2:description 3:location 4:lat 5:lng 6:checkin_radius
        // 7:activity_date 8:start_time 9:end_time 10:activity_hours 11:max_participants
        // 12:register_open_at 13:register_close_at 14:checkin_open_at 15:checkin_close_at
        // 16:is_mandatory 17:allow_early_checkin 18:category_id 19:created_by
        // 20:qr_token 21:image_path 22:status 23:scope 24:faculty 25:department
        // 26:created_at 27:updated_at
        $data = [
            'id'                 => (int) $vals[0],
            'title'              => unquote($vals[1]),
            'description'        => unquote($vals[2]),
            'location'           => unquote($vals[3]),
            'latitude'           => isset($vals[4]) && $vals[4] !== 'NULL' ? (float) $vals[4] : null,
            'longitude'          => isset($vals[5]) && $vals[5] !== 'NULL' ? (float) $vals[5] : null,
            'checkin_radius'     => (int) ($vals[6] ?? 200),
            'activity_date'      => unquote($vals[7]),
            'start_time'         => unquote($vals[8]),
            'end_time'           => unquote($vals[9]),
            'activity_hours'     => (float) ($vals[10] ?? 1),
            'max_participants'   => (int) ($vals[11] ?? 50),
            'register_open_at'   => unquote($vals[12]),
            'register_close_at'  => unquote($vals[13]),
            'checkin_open_at'    => unquote($vals[14]),
            'checkin_close_at'   => unquote($vals[15]),
            'is_mandatory'       => ($vals[16] ?? '0') === '1',
            'allow_early_checkin'=> ($vals[17] ?? '0') === '1',
            'category_id'        => (int) ($vals[18] ?? 1),
            'created_by'         => (int) ($vals[19] ?? 1),
            'qr_token'           => unquote($vals[20]) ?: \Illuminate\Support\Str::random(64),
            'image_path'         => unquote($vals[21]),
            'status'             => unquote($vals[22]) ?: 'upcoming',
            'scope'              => unquote($vals[23]) ?: 'university',
            'faculty'            => unquote($vals[24]),
            'department'         => unquote($vals[25]),
        ];

        $data['qr_checkout_token'] = \Illuminate\Support\Str::random(64);
        Activity::updateOrCreate(['id' => $data['id']], $data);
        $count++;
    }
}
echo "   Activities imported: {$count}\n";

// ─── 4. Import Registrations ───
echo "4. Importing registrations...\n";
preg_match_all("/INSERT INTO `registrations` VALUES\s*(.+?);/s", $contents, $m);
$count = 0;
foreach ($m[1] as $row) {
    $rows = preg_split('/\)\s*,\s*\(/', $row);
    foreach ($rows as $r) {
        $r = trim($r, '()');
        $vals = pg_split_values($r);
        if (!$vals || count($vals) < 4) continue;

        $userId = (int) $vals[1];
        $activityId = (int) $vals[2];

        // Skip if foreign key doesn't exist
        if (!User::find($userId) || !Activity::find($activityId)) continue;

        Registration::updateOrCreate(
            ['id' => (int) $vals[0]],
            [
                'user_id'      => $userId,
                'activity_id'  => $activityId,
                'status'       => unquote($vals[3]),
                'approved_at'  => unquote($vals[4]) !== 'NULL' ? unquote($vals[4]) : null,
                'created_at'   => unquote($vals[7]),
                'updated_at'   => unquote($vals[8]),
            ]
        );
        $count++;
    }
}
echo "   Registrations imported: {$count}\n";

// ─── 5. Import Attendances ───
echo "5. Importing attendances...\n";
preg_match_all("/INSERT INTO `attendances` VALUES\s*(.+?);/s", $contents, $m);
$count = 0;
foreach ($m[1] as $row) {
    $rows = preg_split('/\)\s*,\s*\(/', $row);
    foreach ($rows as $r) {
        $r = trim($r, '()');
        $vals = pg_split_values($r);
        if (!$vals || count($vals) < 5) continue;

        $userId = (int) $vals[1];
        $activityId = (int) $vals[2];

        if (!User::find($userId) || !Activity::find($activityId)) continue;

        Attendance::updateOrCreate(
            ['id' => (int) $vals[0]],
            [
                'user_id'        => $userId,
                'activity_id'    => $activityId,
                'method'         => unquote($vals[3]),
                'checked_in_at'  => unquote($vals[4]),
                'status'         => unquote($vals[5]),
                'is_verified'    => $vals[6] === '1',
                'verified_by'    => isset($vals[7]) && $vals[7] !== 'NULL' && (int) $vals[7] > 0 ? (int) $vals[7] : null,
                'created_at'     => unquote($vals[12]),
                'updated_at'     => unquote($vals[13]),
            ]
        );
        $count++;
    }
}
echo "   Attendances imported: {$count}\n";

// ─── Verify ───
echo "\n=== Verification ===\n";
$tables = ['users', 'activities', 'activity_categories', 'registrations', 'attendances'];
foreach ($tables as $t) {
    $c = DB::table($t)->count();
    echo "  {$t}: {$c}\n";
}

echo "\nDone!\n";

// ─── Helper functions ───

/**
 * Split a MySQL VALUES row string into individual values, handling quoted strings.
 */
function pg_split_values(string $row): ?array
{
    $vals = [];
    $i = 0;
    $len = strlen($row);

    while ($i < $len) {
        // Skip whitespace
        while ($i < $len && $row[$i] === ' ') $i++;
        if ($i >= $len) break;

        if ($row[$i] === "'") {
            // Quoted string
            $i++; // skip opening quote
            $val = '';
            while ($i < $len) {
                if ($row[$i] === '\\' && $i + 1 < $len) {
                    $val .= $row[$i + 1];
                    $i += 2;
                } elseif ($row[$i] === "'") {
                    // Check for escaped quote ''
                    if ($i + 1 < $len && $row[$i + 1] === "'") {
                        $val .= "'";
                        $i += 2;
                    } else {
                        $i++; // skip closing quote
                        break;
                    }
                } else {
                    $val .= $row[$i];
                    $i++;
                }
            }
            $vals[] = $val;
        } elseif (substr($row, $i, 4) === 'NULL') {
            $vals[] = 'NULL';
            $i += 4;
        } else {
            // Unquoted value (number, etc.)
            $start = $i;
            while ($i < $len && $row[$i] !== ',' && $row[$i] !== ')') $i++;
            $vals[] = trim(substr($row, $start, $i - $start));
        }

        // Skip comma
        while ($i < $len && ($row[$i] === ',' || $row[$i] === ' ')) $i++;
    }

    return $vals ?: null;
}

/**
 * Remove surrounding quotes from a value.
 */
function unquote(?string $val): ?string
{
    if ($val === null || $val === 'NULL') return null;
    $val = trim($val, "'");
    $val = str_replace("\\'", "'", $val);
    $val = str_replace('\\r\\n', "\n", $val);
    $val = str_replace('\\n', "\n", $val);
    return $val === '' ? null : $val;
}
