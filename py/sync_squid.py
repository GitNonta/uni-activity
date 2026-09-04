import sys, os
from pathlib import Path
sys.path.insert(0, os.path.join(os.path.dirname(__file__)))
from monitor.proxy_manager import sync_squid_files, load_blocklist

sync_squid_files()
print("Blocklist sync finished:", load_blocklist().get("blocked_domains", []))
