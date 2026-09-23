//go:build !linux

package deploy

import "syscall"

// detachSysProcAttr is a no-op on non-Linux platforms (local dev builds only;
// production always runs on Linux/Termux).
func detachSysProcAttr() *syscall.SysProcAttr {
	return nil
}
