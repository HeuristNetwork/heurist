#!/bin/bash
########################################################################
# Script: alert_server.sh
# Purpose: Alert if 1-min load average > 1.0 or disk usage on /srv > 90%
# Sends email alerts, but suppresses repeat alerts for same issue for 60 minutes
########################################################################

# Configuration
CPU_THRESHOLD=1          # 1-minute load average threshold
DISK_THRESHOLD=90        # % usage threshold on /srv
ALERT_FILE="/tmp/server_health_mail.out"
ALERT_TIMESTAMP="/tmp/server_health_mail.ts"
TO="ian.johnson.heurist@gmail.com,info.heurist@gmail.com,support@heuristnetwork.org"
HOSTNAME=$(hostname)

# Function to check if 60 minutes have passed since last alert
should_send_alert() {
    [ ! -f "$ALERT_TIMESTAMP" ] || find "$ALERT_TIMESTAMP" -mmin +60 | grep -q .
}

# Function to reset alert window (remove timestamp if conditions no longer met)
reset_alert_window_if_needed() {
    if [ -f "$ALERT_TIMESTAMP" ]; then
        rm -f "$ALERT_TIMESTAMP"
        echo "[INFO] Alert window reset."
    fi
}

### CPU Load Check
cpu_load=$(awk '{print $1}' /proc/loadavg)
threshold_int=$(LC_ALL=C awk -v t="$CPU_THRESHOLD" 'BEGIN { printf("%.0f", t * 100) }')
cpu_load_int=$(LC_ALL=C awk '{ printf("%.0f", $1 * 100) }' /proc/loadavg)

if [ "$cpu_load_int" -gt "$threshold_int" ]; then
    if should_send_alert; then
        {
            echo "⚠️ CPU load alert on $HOSTNAME"
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

        SUBJECT="Heurist CPU Load Alert [$cpu_load] on $HOSTNAME"
        mail -s "$SUBJECT" "$TO" < "$ALERT_FILE" && touch "$ALERT_TIMESTAMP"
        echo "[INFO] CPU alert sent: $cpu_load"
    else
        echo "[INFO] CPU alert already sent recently. Skipping."
    fi
else
    echo "[INFO] CPU load OK: $cpu_load"
    reset_alert_window_if_needed
fi

### Disk Usage Check
disk_use=$(df -P /srv | awk 'NR==2 {gsub("%", "", $5); print $5}')

if [ "$disk_use" -ge "$DISK_THRESHOLD" ]; then
    if should_send_alert; then
        {
            echo "⚠️ Disk usage alert on $HOSTNAME"
            echo "Current /srv disk usage: $disk_use% (threshold: ${DISK_THRESHOLD}%)"
            echo ""
            echo "+------------------------------------------------------------------+"
            echo "Disk usage (df -H)"
            echo "+------------------------------------------------------------------+"
            df -H
        } > "$ALERT_FILE"

        SUBJECT="Heurist Disk Usage Alert [${disk_use}%] on $HOSTNAME"
        mail -s "$SUBJECT" "$TO" < "$ALERT_FILE" && touch "$ALERT_TIMESTAMP"
        echo "[INFO] Disk alert sent: $disk_use%"
    else
        echo "[INFO] Disk alert already sent recently. Skipping."
    fi
else
    echo "[INFO] Disk usage OK: $disk_use%"
    reset_alert_window_if_needed
fi
