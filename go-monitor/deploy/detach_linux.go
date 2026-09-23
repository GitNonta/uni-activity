//go:build linux

package deploy

import "syscall"

// detachSysProcAttr returns process attributes that detach the child from the
// current session (setsid), so daemons respawned by go-monitor (artisan serve,
// php-fpm) survive after go-monitor restarts or the spawning session ends.
func detachSysProcAttr() *syscall.SysProcAttr {
	return &syscall.SysProcAttr{Setsid: true}
}
