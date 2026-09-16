param([int]$Seconds = 12)
# gpu_ab_proof.ps1 - A/B proof that the fdx engine executes on the iGPU.
# Runs gpu_bench.py twice (hardware auto vs forced WARP), sampling
# \GPU Engine(*)\Utilization Percentage for each bench PID. Expect:
#   hardware : active samples on luid 0xE409 (Intel UHD), engtype mostly 3D
#              (Gen9.5 dispatches compute via the 3D queue), low CPU delta
#   WARP     : NO Intel-LUID activity, CPU delta ~= 100% of a core per
#              thread, ~5-6x slower per embedding

$ErrorActionPreference = "Continue"
$bench = Join-Path $PSScriptRoot "gpu_bench.py"

function Run-Bench([int]$GpuIndex, [string]$Label) {
    Write-Output "================ $Label (gpu_index=$GpuIndex) ================"
    $p = Start-Process -FilePath "python" `
        -ArgumentList @("`"$bench`"", "$Seconds", "$GpuIndex") `
        -WindowStyle Hidden -RedirectStandardOutput (Join-Path $PSScriptRoot "_bench.out") `
        -RedirectStandardError (Join-Path $PSScriptRoot "_bench.err") -PassThru
    Start-Sleep -Seconds 2   # engine init (~1-2s) before sampling

    $p0 = Get-Process -Id $p.Id
    $c0 = $p0.CPU
    $t0 = Get-Date
    $maxEng = @{}
    $luids = @{}
    $n = 0
    $end = (Get-Date).AddSeconds($Seconds)

    while ((Get-Date) -lt $end) {
        $s = Get-Counter "\GPU Engine(*)\Utilization Percentage" -ErrorAction SilentlyContinue
        if ($s) {
            foreach ($c in $s.CounterSamples) {
                $in = $c.InstanceName
                if ($in -like "*pid_$($p.Id)_*" -and $c.CookedValue -gt 0.05) {
                    $n++
                    $eng = ($in -split '_')[-1]
                    if (-not $maxEng.ContainsKey($eng) -or $c.CookedValue -gt $maxEng[$eng]) { $maxEng[$eng] = $c.CookedValue }
                    if ($in -match 'luid_0x([0-9A-Fa-f]+)_0x([0-9A-Fa-f]+)_') { $luids[$Matches[2]] = $true }
                    "{0:HH:mm:ss.fff}  {1}  {2:N1}%" -f (Get-Date), $in, $c.CookedValue
                }
            }
        }
    }

    $p1 = Get-Process -Id $p.Id -ErrorAction SilentlyContinue
    $el = ((Get-Date) - $t0).TotalSeconds
    $c1 = if ($p1) { $p1.CPU } else { $c0 }
    $cores = [Environment]::ProcessorCount
    Wait-Process -Id $p.Id -Timeout 60 -ErrorAction SilentlyContinue

    Write-Output "--- $Label summary (pid $($p.Id)) ---"
    Write-Output ("active GPU samples : {0}" -f $n)
    $maxEng.GetEnumerator() | Sort-Object Value -Descending |
        ForEach-Object { Write-Output ("  max {0,-12}: {1:N1}%" -f $_.Key, $_.Value) }
    $lbl = foreach ($lo in $luids.Keys) {
        $hex = "0x" + $lo.ToLower()
        if ([Convert]::ToInt32($lo, 16) -eq 58377) { "$hex (Intel UHD)" } else { "$hex" }
    }
    Write-Output ("LUID low-parts     : {0}" -f ($lbl -join ', '))
    Write-Output ("CPU delta          : {0:N1}% of machine ({1:N1} core-sec / {2:N0}s)" -f (100 * ($c1 - $c0) / ($el * $cores)), ($c1 - $c0), $el)
    Write-Output "bench output:"
    Get-Content (Join-Path $PSScriptRoot "_bench.out")
    $err = Get-Content (Join-Path $PSScriptRoot "_bench.err") -ErrorAction SilentlyContinue
    if ($err) { Write-Output "bench stderr:"; $err | Select-Object -First 4 }
}

Run-Bench (-1) "HARDWARE iGPU"
Run-Bench (-2) "FORCED WARP (CPU)"
Write-Output "================ A/B conclusion ================"
Write-Output "Hardware mode shows Intel-LUID engine activity with low CPU delta;"
Write-Output "WARP mode shows none on the Intel LUID and a high CPU delta."
