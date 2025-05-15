#!/bin/bash
set -e

BASE_DIR="/docker/clients"

# Run once initially for all existing compose.yaml files
for dir in "$BASE_DIR"/*/; do
    COMPOSE_FILE="${dir}compose.yaml"
    if [[ -f "$COMPOSE_FILE" ]]; then
        echo "Initial docker compose up in $dir"
        docker compose -f "$COMPOSE_FILE" up -d --build
    fi
done

# Watch for changes: creation/modification of compose.yaml files inside any client dir
inotifywait -m -e close_write,create,move "$BASE_DIR" --format '%w%f' --recursive | while read FILE; do
    # Normalize path and check if the event relates to a compose.yaml file
    if [[ "$FILE" =~ compose.yaml$ ]]; then
        DIR=$(dirname "$FILE")
        echo "Detected change in $FILE, running docker compose up -d in $DIR"
        docker compose -f "$FILE" up -d
    fi
done