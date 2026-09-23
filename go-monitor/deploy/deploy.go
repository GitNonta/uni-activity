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
	"strconv"
	"strings"
	"sync"
	"time"

	"uni-activity/go-monitor/config"
	"uni-activity/go-monitor/telegram"
)

type DeployStatusInfo struct {
	IsDeploying bool     `json:"is_deploying"`
	Status      string   `json:"status"` // "idle", "running", "success", "failed"
	CurrentStep string   `json:"current_step"`
	StartedAt   string   `json:"started_at"`
	FinishedAt  string   `json:"finished_at"`
	CommitHash  string   `json:"commit_hash"`
	CommitMsg   string   `json:"commit_msg"`
	Error       string   `json:"error"`
	OutputLines []string `json:"output_lines"`
}

var (
	deployMu     sync.RWMutex
	deployStatus = DeployStatusInfo{
		Status:      "idle",
		CurrentStep: "Ready",
		OutputLines: []string{},
	}
)

func GetDeployStatus() DeployStatusInfo {
	deployMu.RLock()
	defer deployMu.RUnlock()
	return deployStatus
}

func updateDeployStep(step string, logLine string) {
	deployMu.Lock()
	defer deployMu.Unlock()
	deployStatus.CurrentStep = step
	if logLine != "" {
		deployStatus.OutputLines = append(deployStatus.OutputLines, logLine)
		if len(deployStatus.OutputLines) > 500 {
			deployStatus.OutputLines = deployStatus.OutputLines[len(deployStatus.OutputLines)-500:]
		}
	}
}

func runAndLog(cmd *exec.Cmd, logWriter io.Writer) error {
	cmdStr := strings.Join(cmd.Args, " ")
	ts := time.Now().Format("2006-01-02 15:04:05")
	line := fmt.Sprintf("[%s] > %s", ts, cmdStr)
	fmt.Fprintln(logWriter, line)
	updateDeployStep(deployStatus.CurrentStep, line)

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
		txt := fmt.Sprintf("[%s] %s", ts, scanner.Text())
		fmt.Fprintln(logWriter, txt)
		updateDeployStep(deployStatus.CurrentStep, txt)
	}

	return cmd.Wait()
}

// octaneRunning reports whether a Laravel Octane server is currently running
// on this device. Octane is optional here: production serves via `php artisan
// serve` workers + php-fpm, so `octane:reload` only makes sense when Octane
// actually exists (otherwise it fails with "Octane server is not running").
func octaneRunning() bool {
	out, err := exec.Command("pgrep", "-f", "octane:start").Output()
	return err == nil && len(strings.TrimSpace(string(out))) > 0
}

// reloadAppRuntime reloads the PHP application runtime after a deploy.
//   - Octane running  -> `php artisan octane:reload`
//   - Otherwise       -> rolling restart of `artisan serve` listeners
//     (kill the php -S listeners first, then the wrappers,
//     then respawn on the same ports — mirrors deploy.yml)
func reloadAppRuntime() {
	if octaneRunning() {
		if err := exec.Command("php", "artisan", "octane:reload").Run(); err != nil {
			log.Printf("⚠️ octane:reload failed: %v", err)
		}
		return
	}

	ports := []string{"8000", "8002", "8003"}
	for _, port := range ports {
		// Kill the php -S LISTENERS by PID first (killing only the wrapper
		// orphans the child, which keeps holding the port).
		if out, err := exec.Command("sh", "-c",
			"netstat -tlnp 2>/dev/null | grep ':"+port+" ' | grep -oE '[0-9]+/php' | cut -d/ -f1 | sort -u").Output(); err == nil {
			for _, pidStr := range strings.Fields(string(out)) {
				if pid, err := strconv.Atoi(strings.TrimSpace(pidStr)); err == nil && pid > 0 {
					_ = exec.Command("kill", "-9", strconv.Itoa(pid)).Run()
				}
			}
		}
		_ = exec.Command("pkill", "-f", "artisan serve --host 0.0.0.0 --port "+port).Run()
	}

	phpBin := "/data/data/com.termux/files/usr/bin/php"
	if _, err := os.Stat(phpBin); err != nil {
		phpBin = "php"
	}
	for _, port := range ports {
		cmd := exec.Command(phpBin, "artisan", "serve", "--host", "0.0.0.0", "--port", port)
		cmd.Dir = config.AppConfig.ProjectRoot
		cmd.SysProcAttr = detachSysProcAttr()
		logFile, err := os.OpenFile(
			filepath.Join(config.AppConfig.ProjectRoot, "storage", "logs", "serve-"+port+".log"),
			os.O_CREATE|os.O_WRONLY|os.O_APPEND, 0644)
		if err == nil {
			cmd.Stdout = logFile
			cmd.Stderr = logFile
		}
		if err := cmd.Start(); err != nil {
			log.Printf("⚠️ failed to restart artisan serve on port %s: %v", port, err)
		} else {
			go func() { _ = cmd.Wait() }() // reap on exit — prevent zombie accumulation
		}
		if logFile != nil {
			logFile.Close() // parent-side copy not needed after Start()
		}
	}
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

	nowStr := time.Now().Format("2006-01-02 15:04:05")
	deployMu.Lock()
	deployStatus = DeployStatusInfo{
		IsDeploying: true,
		Status:      "running",
		CurrentStep: "Starting deployment...",
		StartedAt:   nowStr,
		FinishedAt:  "",
		OutputLines: []string{fmt.Sprintf("[%s] Manual deploy initiated via Go Monitor.", nowStr)},
	}
	deployMu.Unlock()

	defer func() {
		deployMu.Lock()
		deployStatus.IsDeploying = false
		if deployStatus.Status == "running" {
			deployStatus.Status = "success"
			deployStatus.CurrentStep = "Deployment finished successfully"
			deployStatus.FinishedAt = time.Now().Format("2006-01-02 15:04:05")
		}
		deployMu.Unlock()
	}()

	fmt.Fprintf(f, "[%s] Manual deploy triggered via Go Monitor Web UI.\n", nowStr)

	// 1. Git fetch & reset
	updateDeployStep("Pulling latest code from origin/main...", "")
	cmdFetch := exec.Command("git", "fetch", "origin", "main")
	cmdFetch.Dir = appDir
	if err := runAndLog(cmdFetch, f); err != nil {
		deployMu.Lock()
		deployStatus.Status = "failed"
		deployStatus.Error = fmt.Sprintf("git fetch failed: %v", err)
		deployMu.Unlock()
		return
	}

	cmdReset := exec.Command("git", "reset", "--hard", "origin/main")
	cmdReset.Dir = appDir
	if err := runAndLog(cmdReset, f); err != nil {
		deployMu.Lock()
		deployStatus.Status = "failed"
		deployStatus.Error = fmt.Sprintf("git reset failed: %v", err)
		deployMu.Unlock()
		return
	}

	// 2. Clear cache if requested
	if clearCache {
		updateDeployStep("Clearing framework caches...", "")
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
	updateDeployStep("Clearing routes and configuration...", "")
	cmdConfig := exec.Command("php", "artisan", "config:clear")
	cmdConfig.Dir = appDir
	_ = runAndLog(cmdConfig, f)

	cmdRoute := exec.Command("php", "artisan", "route:clear")
	cmdRoute.Dir = appDir
	_ = runAndLog(cmdRoute, f)

	// 4. Build assets
	updateDeployStep("Building production frontend assets with Vite...", "")
	cmdBuild := exec.Command("npm", "run", "build")
	cmdBuild.Dir = appDir
	_ = runAndLog(cmdBuild, f)

	// 5. Reload app runtime (Octane if running, else rolling restart of artisan serve)
	updateDeployStep("Reloading application runtime & workers...", "")
	if octaneRunning() {
		fmt.Fprintf(f, "Octane detected — reloading Octane...\n")
		cmdOctane := exec.Command("php", "artisan", "octane:reload")
		cmdOctane.Dir = appDir
		_ = runAndLog(cmdOctane, f)
	} else {
		fmt.Fprintf(f, "No Octane — performing rolling restart of artisan serve workers...\n")
		reloadAppRuntime()
		fmt.Fprintf(f, "artisan serve workers restarted.\n")
	}

	fmt.Fprintf(f, "Deploy finished successfully.\n")

	// Per-commit log copy & commit info
	cmdHash := exec.Command("git", "rev-parse", "--short", "HEAD")
	cmdHash.Dir = appDir
	hash := ""
	if hashBytes, err := cmdHash.Output(); err == nil {
		hash = strings.TrimSpace(string(hashBytes))
	}
	cmdMsg := exec.Command("git", "log", "-1", "--pretty=%s")
	cmdMsg.Dir = appDir
	msg := ""
	if msgBytes, err := cmdMsg.Output(); err == nil {
		msg = strings.TrimSpace(string(msgBytes))
	}

	deployMu.Lock()
	deployStatus.CommitHash = hash
	deployStatus.CommitMsg = msg
	deployStatus.Status = "success"
	deployStatus.CurrentStep = "Deployment finished successfully."
	deployStatus.FinishedAt = time.Now().Format("2006-01-02 15:04:05")
	deployMu.Unlock()

	if hash != "" {
		perCommitLog := filepath.Join(appDir, "storage", "logs", fmt.Sprintf("git-sync-%s.log", hash))
		_ = copyFile(syncLog, perCommitLog)
		telegram.Send(fmt.Sprintf("🚀 <b>Deployment Succeeded!</b>\n━━━━━━━━━━━━━━━━━━━━\nCommit: <code>%s</code>\nMessage: %s\nStatus: Runtime Reloaded", hash, msg))
	}
}

// TriggerRestart restarts the PHP application runtime (php-fpm + app servers)
func TriggerRestart() {
	appDir := config.AppConfig.ProjectRoot
	_ = exec.Command("pkill", "-9", "-f", "php-fpm").Run()
	cmd := exec.Command("nohup", "php-fpm")
	cmd.Dir = appDir
	cmd.SysProcAttr = detachSysProcAttr()
	if err := cmd.Start(); err == nil {
		go func() { _ = cmd.Wait() }() // reap on exit — prevent zombie accumulation
	}

	reloadAppRuntime()

	telegram.Send("🔄 <b>PHP-FPM / App Servers Restarted</b> via Go Monitor")
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

	nowStr := time.Now().Format("2006-01-02 15:04:05")
	deployMu.Lock()
	deployStatus = DeployStatusInfo{
		IsDeploying: true,
		Status:      "running",
		CurrentStep: fmt.Sprintf("Rolling back to commit %s...", commitHash),
		StartedAt:   nowStr,
		FinishedAt:  "",
		CommitHash:  commitHash,
		OutputLines: []string{fmt.Sprintf("[%s] Rollback initiated to %s via Go Monitor.", nowStr, commitHash)},
	}
	deployMu.Unlock()

	defer func() {
		deployMu.Lock()
		deployStatus.IsDeploying = false
		if deployStatus.Status == "running" {
			deployStatus.Status = "success"
			deployStatus.CurrentStep = fmt.Sprintf("Rollback to %s completed", commitHash)
			deployStatus.FinishedAt = time.Now().Format("2006-01-02 15:04:05")
		}
		deployMu.Unlock()
	}()

	fmt.Fprintf(f, "[%s] Rollback executed to commit %s via Go Monitor Web UI.\n", nowStr, commitHash)

	updateDeployStep(fmt.Sprintf("Resetting code to commit %s...", commitHash), "")
	cmdReset := exec.Command("git", "reset", "--hard", commitHash)
	cmdReset.Dir = appDir
	if err := runAndLog(cmdReset, f); err != nil {
		deployMu.Lock()
		deployStatus.Status = "failed"
		deployStatus.Error = fmt.Sprintf("git reset failed: %v", err)
		deployMu.Unlock()
		return
	}

	updateDeployStep("Clearing configuration & routes...", "")
	cmdConfig := exec.Command("php", "artisan", "config:clear")
	cmdConfig.Dir = appDir
	_ = runAndLog(cmdConfig, f)

	cmdRoute := exec.Command("php", "artisan", "route:clear")
	cmdRoute.Dir = appDir
	_ = runAndLog(cmdRoute, f)

	updateDeployStep("Rebuilding frontend assets with Vite...", "")
	cmdBuild := exec.Command("npm", "run", "build")
	cmdBuild.Dir = appDir
	_ = runAndLog(cmdBuild, f)

	updateDeployStep("Reloading application runtime...", "")
	if octaneRunning() {
		fmt.Fprintf(f, "Octane detected — reloading Octane...\n")
		cmdOctane := exec.Command("php", "artisan", "octane:reload")
		cmdOctane.Dir = appDir
		_ = runAndLog(cmdOctane, f)
	} else {
		fmt.Fprintf(f, "No Octane — performing rolling restart of artisan serve workers...\n")
		reloadAppRuntime()
		fmt.Fprintf(f, "artisan serve workers restarted.\n")
	}

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

	// Support both JSON body { "commit_hash": "..." } or { "commit": "..." }, or URL query
	commitHash := r.URL.Query().Get("commit_hash")
	if commitHash == "" {
		commitHash = r.URL.Query().Get("commit")
	}

	if commitHash == "" && r.Body != nil {
		var req map[string]interface{}
		if err := json.NewDecoder(r.Body).Decode(&req); err == nil {
			if h, ok := req["commit_hash"].(string); ok && h != "" {
				commitHash = h
			} else if h, ok := req["commit"].(string); ok && h != "" {
				commitHash = h
			}
		}
	}

	commitHash = strings.TrimSpace(commitHash)
	if commitHash == "" {
		w.WriteHeader(http.StatusBadRequest)
		json.NewEncoder(w).Encode(map[string]interface{}{
			"status":  "error",
			"message": "Missing commit or commit_hash",
		})
		return
	}

	go TriggerRollback(commitHash)

	json.NewEncoder(w).Encode(map[string]interface{}{
		"status":  "ok",
		"message": fmt.Sprintf("Rollback to commit %s initiated!", commitHash),
	})
}

func HandleDeployStatus(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json; charset=utf-8")
	json.NewEncoder(w).Encode(GetDeployStatus())
}

func HandleDeployCommitLog(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json; charset=utf-8")
	hash := strings.TrimSpace(r.URL.Query().Get("hash"))
	if hash == "" {
		hash = strings.TrimSpace(r.URL.Query().Get("commit"))
	}
	appDir := config.AppConfig.ProjectRoot
	var content string
	if hash != "" {
		path := filepath.Join(appDir, "storage", "logs", fmt.Sprintf("git-sync-%s.log", hash))
		if b, err := os.ReadFile(path); err == nil {
			content = string(b)
		}
	}
	if content == "" {
		path := filepath.Join(appDir, "storage", "logs", "git-sync.log")
		if b, err := os.ReadFile(path); err == nil {
			content = string(b)
		}
	}
	json.NewEncoder(w).Encode(map[string]interface{}{
		"status":  "ok",
		"hash":    hash,
		"content": content,
	})
}
