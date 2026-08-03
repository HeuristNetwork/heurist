#!/bin/bash

########################################################################
# Script: alert_server.sh (rewritten by ChatGPT 16 Nov 2025)
# Purpose: Send alerts if:
#   - 1-minute load average > threshold
#   - /data disk usage >= threshold
# Alerts are throttled to once every 60 minutes.
########################################################################

# ---------------------- CONFIGURATION ---------------------------------

LOAD_THRESHOLD=1.0           # 1-minute load average
DISK_PATH="/" 
DISK_THRESHOLD=80            # % usage threshold for DISK_PATH 
ALERT_FILE="/tmp/server_health_mail.out"
ALERT_INTERVAL_MINUTES=60
TO="ian.johnson.heurist@gmail.com"

HOSTNAME=$(hostname)

# ---------------------- FUNCTIONS ------------------------------------

should_send_alert() {
    # true if the alert file does not exist OR was modified > ALERT_INTERVAL minutes ago
    [[ ! -f "$ALERT_FILE" || $(find "$ALERT_FILE" -mmin +$ALERT_INTERVAL_MINUTES) ]]
}

float_greater() {
    # Compare two floats reliably without bc
    awk -v a="$1" -v b="$2" 'BEGIN { exit (a>b) ? 0 : 1 }'
}

send_mail() {
    local subject="$1"
    /usr/bin/mail -s "$subject" "$TO" < "$ALERT_FILE"
}

# ---------------------- LOAD AVERAGE CHECK ---------------------------

# Field 1 of /proc/loadavg is the 1-minute load average
load_1m=$(awk '{print $1}' /proc/loadavg)

if float_greater "$load_1m" "$LOAD_THRESHOLD"; then

    if should_send_alert; then
        {
            echo "Load average alert on $HOSTNAME"
            echo "Current 1-minute load: $load_1m (threshold: $LOAD_THRESHOLD)"
            echo
            echo "+------------------------------------------------------------------+"
            echo "Top 20 processes (top -bn1)"
            echo "+------------------------------------------------------------------+"
            top -bn1 | head -20
            echo
            echo "+------------------------------------------------------------------+"
            echo "Top 10 processes by %CPU (ps)"
            echo "+------------------------------------------------------------------+"
            ps -eo pcpu,pid,user,args --sort=-pcpu | head -10
        } > "$ALERT_FILE"

        send_mail "⚠️ Load Alert on $HOSTNAME"
        echo "[INFO] Load alert sent: $load_1m"
    else
        echo "[WARNING] Load is $load_1m; alert email suppressed by 60-minute limit"
    fi

else
    echo "[INFO] Load OK: $load_1m"
fi
# ---------------------- DISK USAGE CHECK -----------------------------

if [ ! -e "$DISK_PATH" ]; then
    echo "[ERROR] Disk path does not exist: $DISK_PATH"
else
    disk_use=$(df -P "$DISK_PATH" | awk 'NR==2 {
        gsub("%", "", $5)
        print $5
    }')

if [[ "$disk_use" =~ ^[0-9]+$ ]] &&
   [ "$disk_use" -ge "$DISK_THRESHOLD" ]; then

    if should_send_alert; then
        {
            echo "Disk usage alert on $HOSTNAME"
            echo "Current $DISK_PATH disk usage: $disk_use% (threshold: ${DISK_THRESHOLD}%)"
            echo
            echo "Disk usage (df -H)"
            df -H
        } > "$ALERT_FILE"

        send_mail "⚠️ Disk Usage Alert on $HOSTNAME"
        echo "[INFO] Disk alert sent: $DISK_PATH is $disk_use% full"
    else
        echo "[WARNING] Disk usage is $disk_use%; alert email suppressed by 60-minute limit"
    fi
else
    echo "[INFO] Disk OK: $DISK_PATH is $disk_use% full"
fi

fi

