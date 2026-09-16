param(
    [int]$TargetPid = 0,
    [string]$LoadScript = "load_lan.py",
    [int]$LoadSeconds = 22
)
# gpu_proof.ps1 - prove WHICH adapter a process's D3D work runs on.
# Starts load_lan.py (hidden, logged), then tight-loop samples
# \GPU Engine(*)\Utilization Percentage filtered to TargetPid. Every active
# sample is printed with its full instance name, which embeds the adapter
# LUID: pid_<n>_luid_0x<high>_0x<low>_phys_<p>_eng_<type>.
# Intel UHD here = LUID low part 0xE409 (58377). WARP/MSBRD would show a
# different LUID and the CPU delta would climb instead.

$ErrorActionPreference = "Continue"
if ($TargetPid -le 0) { Write-Output "usage: -TargetPid <pid> [-LoadScript x.py] [-LoadSeconds n]"; exit 1 }
if (-not [IO.Path]::IsPathRooted($LoadScript)) { $LoadScript = Join-Path $PSScriptRoot $LoadScript }

$log = Join-Path $PSScriptRoot "_load.log"
$lp = $null
if (Test-Path $log) { Remove-Item $log -Force }
if (Test-Path "$log.err") { Remove-Item "$log.err" -Force }
if (Test-Path $LoadScript) {
    $lp = Start-Process -FilePath "python" `
        -ArgumentList @("`"$LoadScript`"", "127.0.0.1:8001", "$LoadSeconds", "3") `
        -WindowStyle Hidden -RedirectStandardOutput $log -RedirectStandardError "$log.err" -PassThru
    Write-Output "load driver pid $($lp.Id) started (enroll ~2-3s, then verify loop)"
    Start-Sleep -Seconds 2
} else {
    Write-Output "no load script - sampling idle PID $TargetPid"
}

$p0 = Get-Process -Id $TargetPid -ErrorAction SilentlyContinue
$t0 = Get-Date
$c0 = if ($p0) { $p0.CPU } else { 0 }
$maxEng = @{}
$luids = @{}
$n = 0
$end = (Get-Date).AddSeconds($LoadSeconds)

while ((Get-Date) -lt $end) {
    $s = Get-Counter "\GPU Engine(*)\Utilization Percentage" -ErrorAction SilentlyContinue
    if ($s) {
        foreach ($c in $s.CounterSamples) {
            $in = $c.InstanceName
            if ($in -like "*pid_${TargetPid}_*" -and $c.CookedValue -gt 0.05) {
                $n++
                $eng = ($in -split '_')[-1]
                if (-not $maxEng.ContainsKey($eng) -or $c.CookedValue -gt $maxEng[$eng]) { $maxEng[$eng] = $c.CookedValue }
                if ($in -match 'luid_0x([0-9A-Fa-f]+)_0x([0-9A-Fa-f]+)_') { $luids[$Matches[2]] = $true }
                "{0:HH:mm:ss.fff}  {1}  {2:N1}%" -f (Get-Date), $in, $c.CookedValue
            }
        }
    }
}

$p1 = Get-Process -Id $TargetPid -ErrorAction SilentlyContinue
$el = ((Get-Date) - $t0).TotalSeconds
$c1 = if ($p1) { $p1.CPU } else { 0 }
$cores = [Environment]::ProcessorCount
if ($lp) { Wait-Process -Id $lp.Id -Timeout 90 -ErrorAction SilentlyContinue }

Write-Output "=== GPU Engine summary (pid $TargetPid) ==="
Write-Output ("active samples          : {0}" -f $n)
$maxEng.GetEnumerator() | Sort-Object Value -Descending |
    ForEach-Object { Write-Output ("  max {0,-14}: {1:N1}%" -f $_.Key, $_.Value) }
$lbl = foreach ($lo in $luids.Keys) {
    $hex = "0x" + $lo.ToLower()
    if ([Convert]::ToInt32($lo, 16) -eq 58377) { "$hex (Intel UHD)" } else { "$hex" }
}
Write-Output ("LUID low-parts seen     : {0}" -f ($lbl -join ', '))
Write-Output ("CPU delta while sampling: {0:N1}% of machine over {1:N0}s ({2:N1} core-sec)" -f (100 * ($c1 - $c0) / ($el * $cores)), $el, ($c1 - $c0))
$m = Get-Counter "\GPU Process Memory(pid_${TargetPid}*)\Shared Usage" -ErrorAction SilentlyContinue
if ($m) {
    $m.CounterSamples | Where-Object { $_.CookedValue -gt 0 } |
        ForEach-Object { Write-Output ("GPU shared mem          : {0}  {1:N1} MB" -f $_.InstanceName, ($_.CookedValue / 1MB)) }
}
if ($lp -and (Test-Path $log)) {
    Write-Output "=== load log tail ==="
    Get-Content $log -Tail 8
    if (Test-Path "$log.err") {
        $e = Get-Content "$log.err" -Tail 4
        if ($e) { Write-Output "=== load stderr tail ==="; $e }
    }
}
