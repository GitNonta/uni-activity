#!/usr/bin/env python3
"""
Setup deployment tools for UNI ACTIVITY
"""

import subprocess
import sys
import os

def run_command(cmd):
    """Run shell command and return success status"""
    try:
        result = subprocess.run(cmd, shell=True, check=True, 
                              capture_output=True, text=True)
        return True, result.stdout
    except subprocess.CalledProcessError as e:
        return False, e.stderr

def check_python_version():
    """Check if Python version is 3.7+"""
    version = sys.version_info
    if version.major < 3 or (version.major == 3 and version.minor < 7):
        print(f"❌ Python 3.7+ required, you have {version.major}.{version.minor}")
        return False
    print(f"✓ Python {version.major}.{version.minor}.{version.micro} OK")
    return True

def install_requirements():
    """Install Python requirements"""
    print("\n📦 Installing Python requirements...")
    
    req_file = "py/requirements_deploy.txt"
    if not os.path.exists(req_file):
        print(f"❌ Requirements file not found: {req_file}")
        return False
    
    success, output = run_command(f"pip install -r {req_file}")
    
    if success:
        print("✓ Requirements installed successfully")
        return True
    else:
        print(f"❌ Failed to install requirements:\n{output}")
        return False

def check_installation():
    """Check if required packages are installed"""
    print("\n🔍 Checking installed packages...")
    
    packages = ["paramiko", "websockets"]
    all_ok = True
    
    for package in packages:
        try:
            __import__(package)
            print(f"✓ {package} is installed")
        except ImportError:
            print(f"✗ {package} is NOT installed")
            all_ok = False
    
    return all_ok

def make_scripts_executable():
    """Make Python scripts executable (Unix-like systems)"""
    if sys.platform == "win32":
        print("\n⚠ Windows detected - scripts don't need +x permission")
        return True
    
    print("\n🔧 Making scripts executable...")
    
    scripts = [
        "py/fix_reverb_and_deploy.py",
        "py/emergency_reverb_fix.py",
        "py/monitor_services.py",
        "py/test_websocket.py"
    ]
    
    for script in scripts:
        if os.path.exists(script):
            success, _ = run_command(f"chmod +x {script}")
            if success:
                print(f"✓ {script}")
            else:
                print(f"✗ {script} (failed)")
        else:
            print(f"⚠ {script} (not found)")
    
    return True

def main():
    """Main setup flow"""
    print("="*60)
    print("🚀 UNI ACTIVITY - Deployment Tools Setup")
    print("="*60)
    
    # Check Python version
    if not check_python_version():
        sys.exit(1)
    
    # Install requirements
    print("\nDo you want to install Python requirements?")
    response = input("(yes/no): ").lower()
    
    if response in ['yes', 'y']:
        if not install_requirements():
            print("\n⚠ Installation had issues, but continuing...")
    
    # Check installation
    if not check_installation():
        print("\n⚠ Some packages are missing")
        print("Run: pip install -r py/requirements_deploy.txt")
    
    # Make scripts executable
    make_scripts_executable()
    
    # Final message
    print("\n" + "="*60)
    print("✅ Setup Complete!")
    print("="*60)
    print("\n📋 Available Commands:")
    print("\n1. Emergency fix (when Reverb is down):")
    print("   python py/emergency_reverb_fix.py")
    print("\n2. Complete diagnostic and fix:")
    print("   python py/fix_reverb_and_deploy.py")
    print("\n3. Monitor services:")
    print("   python py/monitor_services.py")
    print("\n4. Quick service check:")
    print("   python py/monitor_services.py quick")
    print("\n5. Test WebSocket connection:")
    print("   python py/test_websocket.py")
    print("\n" + "="*60)
    print("\n📖 Read py/DEPLOYMENT_README.md for full documentation")
    print()

if __name__ == "__main__":
    try:
        main()
    except KeyboardInterrupt:
        print("\n\n⏹️ Setup cancelled by user")
        sys.exit(0)
