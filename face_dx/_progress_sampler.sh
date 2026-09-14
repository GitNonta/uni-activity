#!/usr/bin/env bash
# progress sampler: appends (timestamp, rows) every 120 s until the state
# file stops growing for 3 consecutive samples or the final report appears
STATE=reports/celeba_512d_full_fp16.json.state.jsonl
OUT=reports/full_run_progress.log
last=-1; stall=0
while true; do
  n=$(wc -l < "$STATE" 2>/dev/null || echo 0)
  echo "$(date +%H:%M:%S) $n" >> "$OUT"
  if [ "$n" -ge 202599 ]; then echo "$(date +%H:%M:%S) DONE" >> "$OUT"; break; fi
  if [ "$n" -eq "$last" ]; then
    stall=$((stall+1))
    [ "$stall" -ge 30 ] && { echo "$(date +%H:%M:%S) STALLED at $n" >> "$OUT"; break; }
  else
    stall=0; last=$n
  fi
  sleep 120
done
