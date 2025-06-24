#!/bin/bash

########################################################################
# Script: alert_server.sh
# Purpose: Alert if 1-min load average > 1.0 or disk usage on /srv > 95%
# Sends email alerts and avoids spamming by limiting to once per hour
########################################################################

# Configuration
CPU_THRESHOLD=1.0         # 1-minute load average threshold
DISK_THRESHOLD=90         # % usage threshold on /srv
ALERT_FILE="/tmp/server_health_mail.out"
TO="ian.johnson.heurist@gmail.com,info.heurist@gmail.com,support@heuristnetwork.org"
HOSTNAME=$(hostname)

# Avoid duplicate alerts within 60 minutes
should_send_alert() {
    [[ ! -f "$ALERT_FILE" || $(find "$ALERT_FILE" -mmin +60) ]]
}

### CPU Monitor
# Get 1-minute load average (field 1 from /proc/loadavg)
cpu_load=$(awk '{print $1}' /proc/loadavg)

# Use bc to compare floating point values - since this may notbe installed, use fudgy alternative below
# if should_send_alert && (( $(echo "$cpu_load > $CPU_THRESHOLD" | bc -l) )); then
threshold_int=$(LC_ALL=C awk -v t="$CPU_THRESHOLD" 'BEGIN { printf("%.0f", t * 100) }')
cpu_load_int=$(LC_ALL=C awk '{ printf("%.0f", $1 * 100) }' /proc/loadavg)

if should_send_alert && [ "$cpu_load_int" -gt "$threshold_int" ]; then
    {
        echo "CPU load alert on $HOSTNAME"
        echo "Current 1-minute load average: $cpu_load (threshold: $CPU_THRESHOLD)"
        echo ""
        echo "+------------------------------------------------------------------+"
        echo "Top 20 processes (from top -bn1)"
        echo "+------------------------------------------------------------------+"
        top -bn1 | head -20
        echo ""
        echo "+------------------------------------------------------------------+"
        echo "Top 10 processes by %CPU (from ps)"
        echo "+------------------------------------------------------------------+"
        ps -eo pcpu,pid,user,args --sort=-pcpu | head -10
    } > "$ALERT_FILE"

    mail -s "⚠️ CPU Load Alert on $HOSTNAME" "$TO" < "$ALERT_FILE"
    echo "[INFO] CPU alert sent: $cpu_load"
else
    echo "[INFO] CPU load OK: $cpu_load"
fi

### DISK Monitor
# Get disk usage percentage (numeric) for /srv
disk_use=$(df -P /data | awk 'NR==2 {gsub("%", "", $5); print $5}')

if should_send_alert && [ "$disk_use" -ge "$DISK_THRESHOLD" ]; then
    {
        echo "Disk usage alert on $HOSTNAME"
        echo "Current /srv disk usage: $disk_use% (threshold: ${DISK_THRESHOLD}%)"
        echo ""
        echo "+------------------------------------------------------------------+"                nan
        echo "Disk usage (df -H)"
        echo "+------------------------------------------------------------------+"
        df -H
    } > "$ALERT_FILE"

    mail -s "⚠️ Disk Usage Alert on $HOSTNAME" "$TO" < "$ALERT_FILE"
    echo "[INFO] Disk alert sent: $disk_use%"
else
    echo "[INFO] Disk usage OK: $disk_use%"
fi

