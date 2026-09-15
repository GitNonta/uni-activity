"""Minimal setuptools shim — all metadata lives in pyproject.toml.

Needed only so `python setup.py bdist_wheel --plat-name win_amd64` can
stamp the native-payload wheel with the correct platform tag (the wheel
ships a Windows DLL, so a `py3-none-any` tag would be wrong).
"""
from setuptools import setup

setup()
