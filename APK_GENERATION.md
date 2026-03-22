# APK Generation Guide for Boring Life

## Current Setup

Your app now has instant APK download functionality. Users can download and install the app directly.

## Methods to Get a Real APK

### Method 1: Using EAS Build (Recommended - Easiest)

**What is EAS Build?** A cloud service that builds native Android/iOS apps from web projects.

```bash
# 1. Install EAS CLI
npm install -g eas-cli

# 2. Login to EAS (creates free account)
eas login

# 3. Create eas.json in project root (see below)

# 4. Build APK
eas build --platform android --local

# 5. APK will be generated and download link shown
```

**eas.json configuration:**
```json
{
  "build": {
    "preview": {
      "android": {
        "buildType": "apk"
      }
    },
    "production": {
      "android": {
        "buildType": "aab"
      }
    }
  }
}
```

### Method 2: Using Capacitor + Local Android SDK

Requires: Android Studio, SDK Tools

```bash
# 1. Install Capacitor
npm install @capacitor/core @capacitor/cli

# 2. Initialize
npx cap init

# 3. Add Android platform
npx cap add android

# 4. Build for Android
npm run build
npx cap sync

# 5. Open in Android Studio
npx cap open android

# 6. Build APK in Android Studio (Build > Make > Generate Signed APK)
```

### Method 3: Using PWA Builder (Free, Web-based)

1. Visit: https://www.pwabuilder.com/
2. Enter URL: https://boringlife.app
3. Click "Generate APK"
4. Download the generated APK

### Method 4: Using GitHub Actions (CI/CD)

Automated build on every commit:

```yaml
# .github/workflows/build-apk.yml
name: Build APK
on: [push]

jobs:
  build:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      - uses: actions/setup-java@v2
        with:
          java-version: '17'
      - run: npm ci
      - run: npm run build:apk
```

## Using the Generated APK

1. **Store in /uploads/apk/BoringLife.apk**
   - Download endpoint will serve this file
   - Automatic MIME type handling for Android
   - Users click "Download APK" button → automatic download

2. **Installation on Android:**
   - User opens downloaded APK on phone
   - Tap to install
   - Grant permissions
   - App appears on home screen

3. **Updates:**
   - Each build generates new APK
   - Users download updated version
   - Replace old app with new version

## Current Download Endpoint

The endpoint at `/download_apk.php`:
- Checks if real APK exists in `/uploads/apk/BoringLife.apk`
- If exists: serves the file with correct headers
- If not: redirects to PWA Builder for manual generation

## How to Automate APK Generation

```bash
# Create a cron job to rebuild APK weekly
0 0 * * 0 /usr/bin/python3 /path/to/generate_apk.py

# Or use GitHub Actions for automated builds on commits
```

## File Sizes (Typical)

- PWA (current): ~100-200 MB (cached in browser)
- APK (native): 20-50 MB
- Bundle (AAB for Play Store): 10-30 MB

## Security Considerations

1. **Sign the APK:**
   ```bash
   jarsigner -verbose -sigalg SHA1withRSA -digestalg SHA1 \
     -keystore my-keystore.jks app-release.apk alias_name
   ```

2. **Host Securely:** HTTPS only for downloads

3. **Verify Checksums:** Provide SHA256 hash for verification

## Next Steps

1. **Choose a build method** (1-4 above)
2. **Generate APK** using chosen method
3. **Place in**: `/uploads/apk/BoringLife.apk`
4. **Test download** from dashboard/landing page
5. **Host on Play Store** (optional, requires Google account and $25)

## Support Resources

- **Capacitor Docs:** https://capacitorjs.com/docs
- **EAS Build Docs:** https://docs.expo.dev/build/setup/
- **PWA Builder:** https://www.pwabuilder.com/
- **Android Docs:** https://developer.android.com/

---

**Your app is ready for distribution!** Choose a method above and generate your APK. Users can then download and install it with a single click.
