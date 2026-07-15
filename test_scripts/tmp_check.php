
$a = \App\Models\User::where('role','admin')->first();
echo json_encode(array_keys($a->toArray()));
