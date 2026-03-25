# 📱 Android Studio Setup Guide - Side Quest App

Complete guide to create, configure, and deploy the Side Quest Android app using Android Studio.

## 📋 Prerequisites

### System Requirements
- **OS**: Windows 10+, macOS 10.14+, or Linux (Ubuntu 18.04+)
- **RAM**: 8GB minimum (16GB recommended)
- **Disk Space**: 10GB free space
- **JDK**: Java 11+ (Android Studio handles this)

### Software to Install
1. **Android Studio** - Download from https://developer.android.com/studio
2. **Git** - For version control
3. **Android SDK** - Installed via Android Studio

## 🚀 Step 1: Create Project in Android Studio

### 1.1 Open Android Studio
- Launch Android Studio
- Click "New Project"

### 1.2 Project Configuration
**Choose Template:**
- Select: `Empty Activity`
- Click `Next`

**Name Your Project:**
```
Project name: SideQuest
Package name: com.example.sidequest
Save location: /path/to/boringlife/android
Language: Java
Minimum SDK: API 24 (Android 7.0)
```

**Click Finish** - Android Studio will create the project structure

### 1.3 Project Structure
```
SideQuest/
├── app/
│   ├── src/
│   │   ├── main/
│   │   │   ├── java/com/example/sidequest/
│   │   │   │   ├── MainActivity.java
│   │   │   │   ├── NetworkUtils.java
│   │   │   │   └── PreferencesManager.java
│   │   │   ├── res/
│   │   │   │   ├── layout/
│   │   │   │   │   └── activity_main.xml
│   │   │   │   ├── values/
│   │   │   │   │   ├── strings.xml
│   │   │   │   │   ├── colors.xml
│   │   │   │   │   └── themes.xml
│   │   │   │   ├── drawable/
│   │   │   │   └── mipmap/
│   │   │   └── AndroidManifest.xml
│   │   └── test/
│   ├── build.gradle
│   └── proguard-rules.pro
├── build.gradle (project level)
├── settings.gradle
└── gradle.properties
```

## 📁 Step 2: Copy Android Files

### 2.1 Copy from Repository
```bash
# Replace these files with the ones from /android folder:

# Java Files
cp android/MainActivity.java app/src/main/java/com/example/sidequest/
cp android/NetworkUtils.java app/src/main/java/com/example/sidequest/
cp android/PreferencesManager.java app/src/main/java/com/example/sidequest/

# Layout Files
cp android/activity_main.xml app/src/main/res/layout/

# Resource Files
cp android/strings.xml app/src/main/res/values/
cp android/colors.xml app/src/main/res/values/

# Configuration Files
cp android/AndroidManifest.xml app/src/main/
cp android/build.gradle app/
cp android/proguard-rules.pro app/
cp android/build.gradle.root build.gradle
cp android/settings.gradle .
```

### 2.2 Update gradle.properties
Create `gradle.properties` in project root:
```properties
# Project-wide Gradle settings
org.gradle.jvmargs=-Xmx2048m -XX:MaxPermSize=512m

android.useAndroidX=true
android.enableJetifier=true

# Build behavior
org.gradle.parallel=true
org.gradle.caching=true
```

## 🔧 Step 3: Update AndroidManifest.xml

### 3.1 Complete Manifest
```xml
<?xml version="1.0" encoding="utf-8"?>
<manifest xmlns:android="http://schemas.android.com/apk/res/android"
    package="com.example.sidequest">

    <!-- Internet Permission -->
    <uses-permission android:name="android.permission.INTERNET" />
    <uses-permission android:name="android.permission.ACCESS_NETWORK_STATE" />
    
    <!-- Optional: For push notifications -->
    <uses-permission android:name="com.google.android.c2dm.permission.RECEIVE" />
    <uses-permission android:name="android.permission.WAKE_LOCK" />
    
    <!-- Optional: For geolocation features -->
    <uses-permission android:name="android.permission.ACCESS_FINE_LOCATION" />
    <uses-permission android:name="android.permission.ACCESS_COARSE_LOCATION" />

    <application
        android:allowBackup="true"
        android:icon="@mipmap/ic_launcher"
        android:label="@string/app_name"
        android:roundIcon="@mipmap/ic_launcher_round"
        android:supportsRtl="true"
        android:theme="@style/Theme.SideQuest"
        android:usesCleartextTraffic="true">

        <activity
            android:name=".MainActivity"
            android:exported="true"
            android:configChanges="orientation|screenSize|keyboardHidden"
            android:windowSoftInputMode="adjustResize"
            android:screenOrientation="portrait">
            <intent-filter>
                <action android:name="android.intent.action.MAIN" />
                <category android:name="android.intent.category.LAUNCHER" />
            </intent-filter>
        </activity>

        <!-- Optional: Notification receiver -->
        <receiver
            android:name=".NotificationReceiver"
            android:exported="false" />

        <!-- Optional: Service for background tasks -->
        <service
            android:name=".BackgroundService"
            android:exported="false" />

    </application>

</manifest>
```

## 🎨 Step 4: Create Resources

### 4.1 Create Color Scheme
File: `app/src/main/res/values/colors.xml`
```xml
<?xml version="1.0" encoding="utf-8"?>
<resources>
    <color name="primary">#4CAF50</color>
    <color name="primary_dark">#388E3C</color>
    <color name="accent">#FF5722</color>
    <color name="white">#FFFFFF</color>
    <color name="black">#000000</color>
    <color name="background">#F5F5F5</color>
</resources>
```

### 4.2 Create App Theme
File: `app/src/main/res/values/themes.xml`
```xml
<?xml version="1.0" encoding="utf-8"?>
<resources>
    <style name="Theme.SideQuest" parent="Theme.MaterialComponents.Light.NoActionBar">
        <item name="colorPrimary">@color/primary</item>
        <item name="colorPrimaryVariant">@color/primary_dark</item>
        <item name="colorSecondary">@color/accent</item>
        <item name="android:colorBackground">@color/background</item>
        <item name="android:textColorPrimary">@color/black</item>
    </style>
</resources>
```

### 4.3 Create Progress Bar Drawable
File: `app/src/main/res/drawable/progress_drawable.xml`
```xml
<?xml version="1.0" encoding="utf-8"?>
<shape xmlns:android="http://schemas.android.com/apk/res/android"
    android:shape="rectangle">
    <solid android:color="@color/primary" />
    <size
        android:height="4dp"
        android:width="100%" />
</shape>
```

## 🔑 Step 5: Key Configuration Changes

### 5.1 Update MainActivity.java
Change this line to your actual domain:
```java
private static final String APP_URL = "https://your-domain.com";
```

Or for local development (emulator):
```java
private static final String LOCAL_DEV = "http://10.0.2.2:8000";
```

### 5.2 Enable Network Interceptor (Optional)
Add OkHttp for request logging and intercepting:
```java
// In MainActivity onCreate()
OkHttpClient client = new OkHttpClient.Builder()
    .addInterceptor(new HttpLoggingInterceptor())
    .connectTimeout(10, TimeUnit.SECONDS)
    .readTimeout(10, TimeUnit.SECONDS)
    .writeTimeout(10, TimeUnit.SECONDS)
    .build();
```

## 📲 Step 6: Build & Test

### 6.1 Build the App
```bash
# In Android Studio Terminal, or:
./gradlew build

# Or build and run:
./gradlew installDebug
```

### 6.2 Run on Emulator
1. Click "Device Manager" in Android Studio
2. Create/select an emulator (e.g., Pixel 5, API 30)
3. Click green Play button
4. Click "Run" → Select emulator
5. App should launch

### 6.3 Run on Physical Device
1. Enable "Developer Mode" on your Android phone:
   - Go to Settings → About phone
   - Tap "Build number" 7 times
   - Go back to Settings → Developer options
   - Enable "USB debugging"

2. Connect phone via USB
3. In Android Studio, click "Run"
4. Select your phone from device list
5. Click OK

## 📊 Step 7: Signing and Release Build

### 7.1 Create Signing Key
```bash
# Generate keystore
keytool -genkey -v -keystore my-release-key.jks \
  -keyalg RSA -keysize 2048 -validity 10000 \
  -alias my-key-alias
```

### 7.2 Update build.gradle
```gradle
signingConfigs {
    release {
        storeFile file('my-release-key.jks')
        storePassword 'your_store_password'
        keyAlias 'my-key-alias'
        keyPassword 'your_key_password'
    }
}

buildTypes {
    release {
        signingConfig signingConfigs.release
        minifyEnabled true
        proguardFiles getDefaultProguardFile('proguard-android-optimize.txt'), 'proguard-rules.pro'
    }
}
```

### 7.3 Build Release APK
```bash
./gradlew assembleRelease

# Output: app/build/outputs/apk/release/app-release.apk
```

### 7.4 Build AAB (for Play Store)
```bash
./gradlew bundleRelease

# Output: app/build/outputs/bundle/release/app-release.aab
```

## 🎯 Step 8: Publishing to Play Store

### 8.1 Prepare Store Listing
1. Go to https://play.google.com/console
2. Create new app
3. Fill in app details:
   - App name: "Side Quest"
   - Category: "Games"
   - Content rating
   - Target audience

### 8.2 Create App Store Listing
- App description
- Screenshots (5-8 images)
- Feature graphic
- Icon (512x512)
- Promo video (optional)

### 8.3 Create Release
1. Go to "Release" → "Create new release"
2. Select "Production"
3. Upload AAB file
4. Add release notes
5. Review and publish

## 🔒 Security Checklist

### Before Publishing
- [ ] Change `APP_URL` from localhost to production domain
- [ ] Remove all debug logging
- [ ] Enable ProGuard/R8 minification
- [ ] Add API key obfuscation
- [ ] Enable certificate pinning (optional)
- [ ] Test on multiple devices
- [ ] Test network error handling
- [ ] Verify SSL/TLS certificates
- [ ] Remove hardcoded credentials

## 🐛 Troubleshooting

### Build Issues

**Issue: Gradle sync failed**
```
Solution:
1. File → Sync Now
2. Go to Build → Clean Project
3. Build → Rebuild Project
4. If still failing, delete .gradle folder and retry
```

**Issue: "Cannot find symbol" errors**
```
Solution:
1. Check imports are correct
2. Rebuild project: Build → Rebuild Project
3. Invalidate cache: File → Invalidate Caches
```

**Issue: WebView not loading page**
```
Solution:
1. Check internet permission in AndroidManifest.xml
2. For emulator, use http://10.0.2.2:port instead of localhost
3. For physical device, use your actual domain
4. Check device has internet connection
```

### Runtime Issues

**App crashes on startup**
- Check `logcat` in Android Studio
- Look for errors in red text
- Common causes: Missing permissions, wrong package name

**WebView shows blank page**
- Check if domain is accessible
- Verify SSL certificate is valid
- Check if app has internet permission
- For local dev: verify server is running

**Performance issues**
- Clear cache: DataStore & files
- Reduce WebView buffer cache
- Enable hardware acceleration
- Profile with Android Profiler

## 📚 Additional Resources

- **Official Docs**: https://developer.android.com/docs
- **WebView Guide**: https://developer.android.com/guide/webapps
- **Play Store**: https://play.google.com/console
- **Material Design**: https://material.io/design

## 🎓 Learning Path

1. **Basics**: Android fundamentals, Activities, Intents
2. **UI**: Layouts, Views, Navigation
3. **Advanced**: Services, Notifications, Workers
4. **Optimization**: Profiling, Performance, Security

## ✅ Final Checklist

Before submitting to Play Store:
- [ ] App runs on API 24+ devices
- [ ] App runs on tablet and phone
- [ ] All permissions requested appropriately
- [ ] No crashes or ANRs in testing
- [ ] Network errors handled gracefully
- [ ] Back button works correctly
- [ ] Orientation changes handled
- [ ] Screenshots and description are accurate
- [ ] Privacy policy linked and accessible
- [ ] Terms of service included (if applicable)

---

**Status**: 🟢 Ready for Development
**Last Updated**: March 25, 2026
**Version**: 1.0
