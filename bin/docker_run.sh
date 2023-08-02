#!/bin/bash

# Check if the image name and bash script file are provided as arguments
if [ $# -lt 2 ]; then
  echo "Usage: $0 <image_name> <bash_script_file>"
  exit 1
fi

image_name="$1"
bash_script_file="$2"

# Check if the provided bash script file exists
if [ ! -f "$bash_script_file" ]; then
  echo "Bash script file not found: $bash_script_file"
  exit 1
fi

# Find the last running instance ID of the container with the given image name
container_id=$(docker ps -q -n 1 -f ancestor="$image_name")

# Check if the container exists
if [ -z "$container_id" ]; then
  echo "No containers found with the image '$image_name'."
  exit 1
fi

# Execute the contents of the bash script file inside the container
docker cp "$bash_script_file" "$container_id:/tmp/script.sh"
docker exec "$container_id" bash /tmp/script.sh
docker exec "$container_id" rm /tmp/script.sh
