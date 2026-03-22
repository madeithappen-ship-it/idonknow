#!/bin/bash
# Quick APK Generator using EAS Build
# This script generates a real native Android APK

set -e  # Exit on error

echo "================================"
echo "🚀 Boring Life - APK Builder"
echo "================================"
echo ""

# Check if npm is installed
if ! command -v npm &> /dev/null; then
    echo "❌ Node.js/npm not found. Install from: https://nodejs.org/"
    exit 1
fi

# Install EAS CLI globally if not already installed
if ! command -v eas &> /dev/null; then
    echo "📦 Installing EAS Build CLI..."
    npm install -g eas-cli
else
    echo "✅ EAS CLI already installed"
fi

echo ""
echo "📋 Next steps:"
echo "1. Create free Expo/EAS account at: https://expo.dev/signup"
echo "2. Run: eas login"
echo "3. Run: eas build --platform android --local"
echo "4. Select 'apk' when prompted"
echo "5. APK will download to: ./builds/"
echo ""
echo "⚠️  First build takes 5-10 minutes (and uses ~2GB disk space)"
echo ""
echo "Would you like to:"
echo "  [1] Continue with eas build now"
echo "  [2] View detailed documentation (APK_GENERATION.md)"
echo "  [3] Set up automatic GitHub Actions builds"
echo "  [0] Exit"
echo ""
read -p "Choose option [0-3]: " choice

case $choice in
    1)
        echo ""
        echo "Starting EAS Build..."
        eas login --non-interactive 2>/dev/null || {
            echo "📧 Visit: https://expo.dev/signup"
            echo "Then run: eas login"
            eas login
        }
        echo ""
        eas build --platform android --local
        echo ""
        echo "✅ APK ready! Check ./builds/ directory"
        echo "📲 Download to phone and tap to install"
        ;;
    2)
        echo ""
        cat APK_GENERATION.md
        ;;
    3)
        echo ""
        echo "Setting up GitHub Actions CI/CD..."
        echo "📚 See: APK_GENERATION.md (Method 4: Using GitHub Actions)"
        ;;
    *)
        echo "Exiting..."
        exit 0
        ;;
esac

echo ""
echo "Need help?"
echo "📖 Read: APK_GENERATION.md"
echo "💬 Issues: github.com/madeithappen-ship-it/idonknow/issues"
