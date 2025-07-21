#!/bin/bash

# Fix permissions for KPI Tracker Docker setup
# This script fixes common permission issues with Docker volumes

echo "🔧 Fixing file permissions for Docker..."

# Get current user ID and group ID
USER_ID=$(id -u)
GROUP_ID=$(id -g)

echo "Host User ID: $USER_ID"
echo "Host Group ID: $GROUP_ID"

# Fix ownership of the entire project directory
sudo chown -R $USER_ID:$GROUP_ID .

# Set proper permissions
sudo chmod -R 755 .
sudo chmod -R 777 var/ 2>/dev/null || true
sudo chmod -R 777 public/uploads/ 2>/dev/null || true

# Make sure composer files are writable
sudo chmod 666 composer.json 2>/dev/null || true
sudo chmod 666 composer.lock 2>/dev/null || true

echo "✅ Permissions fixed!"
echo ""
echo "Now you can run:"
echo "  make install"
echo "  make start"
