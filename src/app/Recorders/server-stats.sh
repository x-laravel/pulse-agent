#!/bin/bash
# Outputs newline-separated metrics for Laravel Pulse.
# Usage: server-stats.sh [directory1] [directory2] ...
# Default directory: /
#
# Output format:
#   line 1 : MemTotal      (KB)
#   line 2 : MemAvailable  (KB)
#   line 3 : CPU usage     (%)
#   line 4+: for each directory — used (KB) then total (KB)

# Memory
grep MemTotal    /proc/meminfo | grep -Eo '[0-9]+'
grep MemAvailable /proc/meminfo | grep -Eo '[0-9]+'

# CPU — two /proc/stat readings with 0.2s gap for accurate sample
read_cpu() {
    awk '/^cpu / {idle=$5; total=0; for(i=2;i<=NF;i++) total+=$i; print idle, total}' /proc/stat
}
read -r idle1 total1 <<< "$(read_cpu)"
sleep 0.2
read -r idle2 total2 <<< "$(read_cpu)"
idle_diff=$(( idle2  - idle1  ))
total_diff=$(( total2 - total1 ))
awk -v idle="$idle_diff" -v total="$total_diff" \
    'BEGIN { printf "%d\n", (total > 0) ? int((1 - idle/total) * 100 + 0.5) : 0 }'

# Storage — default to / if no args given
dirs=("${@:-/}")
for dir in "${dirs[@]}"; do
    df -k "$dir" 2>/dev/null | awk 'NR==2 { print $3; print $2 }'
done
