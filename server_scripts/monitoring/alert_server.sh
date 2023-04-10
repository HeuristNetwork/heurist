#!/bin/bash

### CPU monitor
cpuuse=$(cat /proc/loadavg | awk '{print $3}')

# was using this to get an integer, but this seems unnecessary: |cut -f 1 -d "."


if [ "$cpuuse" -ge 1 ] && [[ ! -f "/tmp/Mail.out" || $(find "/tmp/Mail.out" -mmin +60) ]]; then
  SUBJECT="ATTENTION: CPU high on $(hostname)"
  MESSAGE="/tmp/Mail.out"
  TO="person-email@mydomain.xyz,support@heuristnetwork"

  echo > $MESSAGE
  echo "CPU current usage is: $cpuuse x 100%" >> $MESSAGE
  echo "" >> $MESSAGE
  echo "+------------------------------------------------------------------+" >> $MESSAGE
  echo "Top 20 processes which are consuming high CPU" >> $MESSAGE
  echo "+------------------------------------------------------------------+" >> $MESSAGE
  echo "$(top -bn1 | head -20)" >> $MESSAGE
  echo "" >> $MESSAGE
  echo "+------------------------------------------------------------------+" >> $MESSAGE
  echo "Top 10 Processes which are consuming high CPU using the ps command" >> $MESSAGE
  echo "+------------------------------------------------------------------+" >> $MESSAGE
  echo "$(ps -eo pcpu,pid,user,args | sort -k 1 -r | head -10)" >> $MESSAGE

  mail -s "$SUBJECT" "$TO" < $MESSAGE
#  rm /tmp/Mail.out
else
  echo "Server CPU usage is under threshold"
fi


### DISK monitor
diskuse=$(df /data | tail -1 | awk '{ print $4-0 }')

if [ "$diskuse" -ge 70 ] &&  [[ ! -f "/tmp/Mail.out" || $(find "/tmp/Mail.out" -mmin +60) ]]; then
  SUBJECT="ATTENTION: Disk high on $(hostname)"
  MESSAGE="/tmp/Mail.out"
  TO="person-email@mydomain.xyz,support@heuristnetwork.org"

  echo > $MESSAGE
  echo "Disk current usage is: $diskuse%" >> $MESSAGE
  echo "" >> $MESSAGE
  echo "Usage details" >> $MESSAGE
  echo "+------------------------------------------------------------------+" >> $MESSAGE
  echo "$(df -H)" >> $MESSAGE
  echo "" >> $MESSAGE
  echo "+------------------------------------------------------------------+" >> $MESSAGE

  mail -s "$SUBJECT" "$TO" < $MESSAGE
#  rm /tmp/Mail.out
else
  echo "Server DISK usage is under threshold of 80pct"
fi



