#!/bin/sh
set -e

REPO="https://github.com/appstorecat/appstorecat.git"
DIR="appstorecat"

echo ""
echo "  ╔══════════════════════════════════════╗"
echo "  ║        APPSTORECAT INSTALLER         ║"
echo "  ║   Open-Source App Intelligence       ║"
echo "  ╚══════════════════════════════════════╝"
echo ""

# Check dependencies
command -v git >/dev/null 2>&1 || { echo "Error: git is required but not installed."; exit 1; }
command -v docker >/dev/null 2>&1 || { echo "Error: docker is required but not installed."; exit 1; }
command -v make >/dev/null 2>&1 || { echo "Error: make is required but not installed."; exit 1; }

# Check Docker is running
docker info >/dev/null 2>&1 || { echo "Error: Docker is not running. Please start Docker first."; exit 1; }

echo "[1/3] Cloning repository..."
if [ -d "$DIR" ]; then
  echo "  Directory '$DIR' already exists. Pulling latest..."
  cd "$DIR" && git pull && cd ..
else
  git clone "$REPO"
fi

echo "[2/3] Building and setting up..."
cd "$DIR"
make setup

echo "[3/3] Starting all services..."
make dev

echo ""
echo "  AppStoreCat is running!"
echo ""
echo "  Open your browser: http://localhost:7461"
echo "  Create an account and start tracking apps."
echo ""
echo "  Commands:"
echo "    make dev      Start all services"
echo "    make down     Stop all services"
echo "    make logs     Follow logs"
echo ""
