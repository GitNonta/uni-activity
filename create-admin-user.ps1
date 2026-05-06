# Create Admin User Script
# Creates an admin user for the University Activity System

Write-Host "`n========================================" -ForegroundColor Cyan
Write-Host "  Create Admin User" -ForegroundColor Cyan
Write-Host "========================================`n" -ForegroundColor Cyan

Write-Host "Creating admin user..." -ForegroundColor Yellow

$createUserCommand = @"
use App\Models\User;
use Illuminate\Support\Facades\Hash;

`$user = User::create([
    'student_id' => 'admin',
    'full_name' => 'System Administrator',
    'email' => 'admin@university.ac.th',
    'password' => Hash::make('admin123'),
    'role' => 'admin',
    'is_active' => true,
    'faculty' => 'Administration',
    'department' => 'IT Department',
]);

echo 'Admin user created successfully!' . PHP_EOL;
echo 'Email: ' . `$user->email . PHP_EOL;
echo 'Password: admin123' . PHP_EOL;
"@

try {
    docker exec laravel-app php artisan tinker --execute=$createUserCommand 2>&1 | Out-Null
    
    Write-Host "`n✓ Admin user created successfully!`n" -ForegroundColor Green
    
    Write-Host "Login Credentials:" -ForegroundColor Cyan
    Write-Host "  URL: http://localhost:8000/admin/login" -ForegroundColor White
    Write-Host "  Email: admin@university.ac.th" -ForegroundColor White
    Write-Host "  Password: admin123`n" -ForegroundColor White
    
    Write-Host "⚠ Remember to change the password after first login!`n" -ForegroundColor Yellow
    
} catch {
    Write-Host "`n✗ Failed to create admin user" -ForegroundColor Red
    Write-Host "Error: $_`n" -ForegroundColor Red
    
    Write-Host "Try manually:" -ForegroundColor Yellow
    Write-Host "  docker exec -it laravel-app php artisan tinker`n" -ForegroundColor White
}

Write-Host "========================================`n" -ForegroundColor Cyan
