#!/bin/bash

WATCH_FILE="/docker/clients/path.txt"
STATE_FILE="/docker/clients/count.txt"

mkdir -p "$(dirname "$STATE_FILE")"

# Ensure the watch file exists
touch "$WATCH_FILE"
touch "$STATE_FILE"

# Get last known line count (persisted across reboots)
last_line_num=$(cat "$STATE_FILE")

inotifywait -m -e modify "$WATCH_FILE" | while read -r filename event; do
    current_line_num=$(wc -l < "$WATCH_FILE")
    
    if [[ "$current_line_num" -gt "$last_line_num" ]]; then
        new_lines=$(tail -n "$((current_line_num - last_line_num))" "$WATCH_FILE")
        while IFS= read -r line; do
            if [[ -n "$line" && -d "$line" ]]; then
                echo "[INFO] Running docker compose for: $line"
                docker compose -d --project-directory "$line" up --build
            else
                echo "[WARNING] Skipping invalid or non-existent directory: $line"
            fi
        done <<< "$new_lines"

        echo "$current_line_num" > "$STATE_FILE"
        last_line_num=$current_line_num
    fi
done