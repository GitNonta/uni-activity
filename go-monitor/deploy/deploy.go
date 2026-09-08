package deploy

import (
	"bufio"
	"encoding/json"
	"fmt"
	"io"
	"log"
	"net/http"
	"os"
	"os/exec"
	"path/filepath"
	"strings"
	"time"

	"uni-activity/go-monitor/config"
	"uni-activity/go-monitor/telegram"
)

func runAndLog(cmd *exec.Cmd, logWriter io.Writer) error {
	cmdStr := strings.Join(cmd.Args, " ")
	ts := time.Now().Format("2006-01-02 15:04:05")
	fmt.Fprintf(logWriter, "[%s] > %s\n", ts, cmdStr)

	stdout, err := cmd.StdoutPipe()
	if err != nil {
		return err
	}
	cmd.Stderr = cmd.Stdout

	if err := cmd.Start(); err != nil {
		return err
	}

	scanner := bufio.NewScanner(stdout)
	for scanner.Scan() {
		ts = time.Now().Format("2006-01-02 15:04:05")
		fmt.Fprintf(logWriter, "[%s] %s\n", ts, scanner.Text())
	}

	return cmd.Wait()
}

// TriggerManualDeploy pulls latest git changes and reloads application
func TriggerManualDeploy(clearCache bool) {
	appDir := config.AppConfig.ProjectRoot
	syncLog := filepath.Join(appDir, "storage", "logs", "git-sync.log")
	_ = os.MkdirAll(filepath.Dir(syncLog), 0755)

	f, err := os.OpenFile(syncLog, os.O_CREATE|os.O_WRONLY|os.O_TRUNC, 0644)
	if err != nil {
		log.Printf("⚠️ Failed to open git-sync.log: %v", err)
		return
	}
	defer f.Close()

	ts := time.Now().Format("2006-01-02 15:04:05")
	fmt.Fprintf(f, "[%s] Manual deploy triggered via Go Monitor Web UI.\n", ts)

	// 1. Git fetch & reset
	cmdFetch := exec.Command("git", "fetch", "origin", "main")
	cmdFetch.Dir = appDir
	_ = runAndLog(cmdFetch, f)

	cmdReset := exec.Command("git", "reset", "--hard", "origin/main")
	cmdReset.Dir = appDir
	_ = runAndLog(cmdReset, f)

	// 2. Clear cache if requested
	if clearCache {
		fmt.Fprintf(f, "Clearing cache...\n")
		cmdCache := exec.Command("php", "artisan", "cache:clear")
		cmdCache.Dir = appDir
		_ = runAndLog(cmdCache, f)

		cmdView := exec.Command("php", "artisan", "view:clear")
		cmdView.Dir = appDir
		_ = runAndLog(cmdView, f)

		cmdNpmCache := exec.Command("npm", "cache", "clean", "--force")
		cmdNpmCache.Dir = appDir
		_ = runAndLog(cmdNpmCache, f)
	}

	// 3. Clear routes and config
	cmdConfig := exec.Command("php", "artisan", "config:clear")
	cmdConfig.Dir = appDir
	_ = runAndLog(cmdConfig, f)

	cmdRoute := exec.Command("php", "artisan", "route:clear")
	cmdRoute.Dir = appDir
	_ = runAndLog(cmdRoute, f)

	// 4. Build assets
	cmdBuild := exec.Command("npm", "run", "build")
	cmdBuild.Dir = appDir
	_ = runAndLog(cmdBuild, f)

	// 5. Reload Octane
	fmt.Fprintf(f, "Reloading Laravel Octane...\n")
	cmdOctane := exec.Command("php", "artisan", "octane:reload")
	cmdOctane.Dir = appDir
	_ = runAndLog(cmdOctane, f)

	fmt.Fprintf(f, "Deploy finished successfully.\n")

	// Per-commit log copy
	cmdHash := exec.Command("git", "rev-parse", "--short", "origin/main")
	cmdHash.Dir = appDir
	if hashBytes, err := cmdHash.Output(); err == nil {
		hash := strings.TrimSpace(string(hashBytes))
		if hash != "" {
			perCommitLog := filepath.Join(appDir, "storage", "logs", fmt.Sprintf("git-sync-%s.log", hash))
			_ = copyFile(syncLog, perCommitLog)
			telegram.Send(fmt.Sprintf("🚀 <b>Deployment Succeeded!</b>\n━━━━━━━━━━━━━━━━━━━━\nCommit: <code>%s</code>\nStatus: Octane Reloaded", hash))
		}
	}
}

// TriggerRestart restarts php-fpm or octane
func TriggerRestart() {
	appDir := config.AppConfig.ProjectRoot
	_ = exec.Command("pkill", "-9", "-f", "php-fpm").Run()
	cmd := exec.Command("nohup", "php-fpm")
	cmd.Dir = appDir
	_ = cmd.Start()

	cmdOctane := exec.Command("php", "artisan", "octane:reload")
	cmdOctane.Dir = appDir
	_ = cmdOctane.Run()

	telegram.Send("🔄 <b>PHP-FPM / Octane Server Restarted</b> via Go Monitor")
}

// TriggerRollback rolls back git repository to specific commit hash
func TriggerRollback(commitHash string) {
	appDir := config.AppConfig.ProjectRoot
	syncLog := filepath.Join(appDir, "storage", "logs", "git-sync.log")
	_ = os.MkdirAll(filepath.Dir(syncLog), 0755)

	f, err := os.OpenFile(syncLog, os.O_CREATE|os.O_WRONLY|os.O_APPEND, 0644)
	if err != nil {
		return
	}
	defer f.Close()

	ts := time.Now().Format("2006-01-02 15:04:05")
	fmt.Fprintf(f, "[%s] Rollback executed to commit %s via Go Monitor Web UI.\n", ts, commitHash)

	cmdReset := exec.Command("git", "reset", "--hard", commitHash)
	cmdReset.Dir = appDir
	_ = runAndLog(cmdReset, f)

	cmdConfig := exec.Command("php", "artisan", "config:clear")
	cmdConfig.Dir = appDir
	_ = runAndLog(cmdConfig, f)

	cmdRoute := exec.Command("php", "artisan", "route:clear")
	cmdRoute.Dir = appDir
	_ = runAndLog(cmdRoute, f)

	cmdBuild := exec.Command("npm", "run", "build")
	cmdBuild.Dir = appDir
	_ = runAndLog(cmdBuild, f)

	cmdOctane := exec.Command("php", "artisan", "octane:reload")
	cmdOctane.Dir = appDir
	_ = runAndLog(cmdOctane, f)

	perCommitLog := filepath.Join(appDir, "storage", "logs", fmt.Sprintf("git-sync-%s.log", commitHash))
	_ = copyFile(syncLog, perCommitLog)

	telegram.Send(fmt.Sprintf("⏮️ <b>Rollback Completed</b>\n━━━━━━━━━━━━━━━━━━━━\nCommit: <code>%s</code>", commitHash))
}

func copyFile(src, dst string) error {
	in, err := os.Open(src)
	if err != nil {
		return err
	}
	defer in.Close()

	out, err := os.Create(dst)
	if err != nil {
		return err
	}
	defer out.Close()

	_, err = io.Copy(out, in)
	return err
}

// HTTP Handlers

func HandleManualDeploy(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json; charset=utf-8")
	clearCache := r.URL.Query().Get("clear_cache") == "true"

	go TriggerManualDeploy(clearCache)

	json.NewEncoder(w).Encode(map[string]interface{}{
		"status":  "ok",
		"message": "Manual deployment triggered in background!",
	})
}

func HandleRestart(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json; charset=utf-8")

	go TriggerRestart()

	json.NewEncoder(w).Encode(map[string]interface{}{
		"status":  "ok",
		"message": "Restart triggered in background!",
	})
}

func HandleRollback(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json; charset=utf-8")

	var req struct {
		CommitHash string `json:"commit_hash"`
	}
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil || req.CommitHash == "" {
		w.WriteHeader(http.StatusBadRequest)
		json.NewEncoder(w).Encode(map[string]interface{}{
			"status":  "error",
			"message": "Missing or invalid commit_hash",
		})
		return
	}

	go TriggerRollback(req.CommitHash)

	json.NewEncoder(w).Encode(map[string]interface{}{
		"status":  "ok",
		"message": fmt.Sprintf("Rollback to commit %s initiated!", req.CommitHash),
	})
}
