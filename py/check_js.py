import re
import subprocess

with open('resources/views/layouts/app.blade.php', 'r', encoding='utf-8') as f:
    content = f.read()

scripts = re.findall(r'<script>(.*?)</script>', content, re.DOTALL)
for i, script in enumerate(scripts):
    with open(f'temp_script_{i}.js', 'w', encoding='utf-8') as f:
        # We need to replace blade directives with valid JS if any exist inside script tags.
        # But let's just see if node can parse it.
        # Replace {{ ... }} with "BLADE"
        script = re.sub(r'\{\{.*?\}\}', '"BLADE"', script)
        script = re.sub(r'@if.*?@endif', '', script, flags=re.DOTALL)
        f.write(script)
    
    res = subprocess.run(['node', '-c', f'temp_script_{i}.js'], capture_output=True, text=True)
    if res.returncode != 0:
        print(f"Error in script {i}:")
        print(res.stderr)
    else:
        print(f"Script {i} syntax OK")
