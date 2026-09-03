CONF = "/data/data/com.termux/files/usr/etc/nginx/nginx.conf"
with open(CONF) as f:
    lines = f.readlines()

new_lines = []
skip = False
for i, line in enumerate(lines):
    if "Static assets" in line:
        skip = True
        new_lines.append("        # -- Static assets (direct from Nginx disk, 30d cache) --\n")
        new_lines.append("        location ~* \\.(jpg|jpeg|png|gif|ico|css|js|webp|svg|woff|woff2|ttf|eot)$ {\n")
        new_lines.append("            root /data/data/com.termux/files/home/uni-activity/public;\n")
        new_lines.append("            try_files $uri =404;\n")
        new_lines.append("            expires 30d;\n")
        new_lines.append('            add_header Cache-Control "public, immutable";\n')
        new_lines.append("            access_log off;\n")
        new_lines.append("        }\n")
        continue
    if skip:
        if line.strip() == "}":
            skip = False
        continue
    new_lines.append(line)

with open(CONF, "w") as f:
    f.writelines(new_lines)
print("Nginx static assets fixed")
