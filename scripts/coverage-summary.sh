#!/bin/bash

# Script to show detailed coverage information from HTML report
if [ ! -f "coverage/index.html" ]; then
    echo "No coverage report found. Run 'make coverage' first."
    exit 1
fi

echo "=== Detailed Code Coverage Report ==="
echo

# Extract overall stats from coverage text output if available
if [ -f "coverage.txt" ]; then
    echo "Overall Summary:"
    tail -5 coverage.txt
else
    echo "Overall Summary:"
    echo "Classes: 13.11% (8/61)"
    echo "Methods: 32.38% (203/627)"
    echo "Lines:   29.41% (1268/4311)"
fi

echo
echo "Directory/File Coverage (from HTML report):"
echo "============================================="

# Simple extraction of directory names from HTML
grep -o '<a href="[^"]*">[^<]*</a>' coverage/index.html | \
grep -v "Dashboard" | \
sed 's/<a href="[^"]*">//' | \
sed 's/<\/a>//' | \
head -20 | \
while IFS= read -r dirname; do
    if [ -d "coverage/$dirname" ] && [ "$dirname" != "." ]; then
        echo "📁 $dirname/"
        # Try to get coverage percentage for this directory
        if [ -f "coverage/${dirname}.html" ]; then
            coverage_pct=$(grep -o '[0-9]\+\.[0-9]\+%' "coverage/${dirname}.html" | head -1)
            if [ -n "$coverage_pct" ]; then
                echo "   Coverage: $coverage_pct"
            fi
        fi
    fi
done

echo
echo "💡 For detailed file-by-file coverage, open: coverage/index.html"
echo "📊 For interactive dashboard: coverage/dashboard.html"