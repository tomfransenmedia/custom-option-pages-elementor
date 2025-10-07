#!/bin/bash

# Prompt for version number
read -p "Enter new version (e.g. 1.1.1): " version

# Commit and push changes
echo "Committing version $version..."
git add .
git commit -m "Release version $version"
git push

# Create and push tag
git tag "$version"
git push origin "$version"

echo "✅ Version $version pushed to GitHub successfully!"