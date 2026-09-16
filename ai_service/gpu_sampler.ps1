param([int]$Seconds = 25, [int]$TargetPid = 0)
# gpu_sampler.ps1 - prove WHICH adapter a process's D3D work runs on.
# Samples \GPU Engine(*)\Utilization Percentage filtered to TargetPid and
# reports per-engine-type maxima plus the LUID embedded in each instance
# name, GPU shared-memory usage, and the process's CPU share for contrast
# (if the work were on WARP/CPU, no Intel-LUID GPU activity would appear
# and the CPU number would jump instead).

if ($TargetPid -le 0) { Write-Output "usage: -Seconds N -TargetPid <pid>"; exit 1 }

$proc0 = Get-Process -Id $TargetPid -ErrorAction SilentlyContinue
$t0 = Get-Date
$cpu0 = if ($proc0) { $proc0.CPU } else { 0 }
$maxByEng = @{}
$luidsSeen = @{}
$samples = 0

$end = (Get-Date).AddSeconds($Seconds)
while ((Get-Date) -lt $end) {
    $s = Get-Counter "\GPU Engine(*)\Utilization Percentage" -ErrorAction SilentlyContinue
    if ($s) {
        foreach ($c in $s.CounterSamples) {
            if ($c.InstanceName -like "*pid_${TargetPid}_*" -and $c.CookedValue -gt 0) {
                $samples++
                $eng = ($c.InstanceName -split '_')[-1]
                if (-not $maxByEng.ContainsKey($eng) -or $c.CookedValue -gt $maxByEng[$eng]) { $maxByEng[$eng] = $c.CookedValue }
                if ($c.InstanceName -match 'luid_(0x[0-9A-Fa-f]+)') { $luidsSeen[$Matches[1]] = $true }
                "{0:HH:mm:ss}  {1}  {2:N1}%" -f (Get-Date), $c.InstanceName, $c.CookedValue
            }
        }
    }
    Start-Sleep -Milliseconds 600
}

$proc1 = Get-Process -Id $TargetPid -ErrorAction SilentlyContinue
$elapsed = ((Get-Date) - $t0).TotalSeconds
$cpu1 = if ($proc1) { $proc1.CPU } else { 0 }
$cores = [Environment]::ProcessorCount
$cpuPct = 100.0 * ($cpu1 - $cpu0) / ($elapsed * $cores)

Write-Output "=== summary ==="
Write-Output ("samples with GPU activity : {0}" -f $samples)
foreach ($k in $maxByEng.Keys) { Write-Output ("  max {0}: {1:N1}%" -f $k, $maxByEng[$k]) }
Write-Output ("LUIDs seen                : {0}" -f (($luidsSeen.Keys | Sort-Object) -join ', '))
Write-Output ("PID {0} CPU while sampling: {1:N1}% of machine ({2:N1} core-sec over {3:N0}s)" -f $TargetPid, $cpuPct, ($cpu1 - $cpu0), $elapsed)
$m = Get-Counter "\GPU Process Memory(pid_${TargetPid}*)\Shared Usage" -ErrorAction SilentlyContinue
if ($m) {
    foreach ($c in $m.CounterSamples) {
        if ($c.CookedValue -gt 0) { Write-Output ("GPU shared mem: {0}  {1:N1} MB" -f $c.InstanceName, ($c.CookedValue / 1MB)) }
    }
}
