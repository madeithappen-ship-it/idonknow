# ProGuard rules for SideQuest App
# This file specifies the code that should be kept during minification

# Keep the main application class
-keep class com.example.sidequest.MainActivity { *; }

# Keep all Activities, Services, and Broadcast Receivers
-keep public class * extends android.app.Activity
-keep public class * extends android.app.Service
-keep public class * extends android.content.BroadcastReceiver
-keep public class * extends android.content.ContentProvider
-keep public class * extends android.app.Fragment
-keep public class * extends androidx.fragment.app.Fragment

# Keep WebView JavaScript interface
-keepclassmembers class com.example.sidequest.** {
    public *;
}

# JavaScript interface for WebView
-keep class * {
    @android.webkit.JavascriptInterface <methods>;
}

# Keep enums
-keepclassmembers enum * {
    public static **[] values();
    public static ** valueOf(java.lang.String);
}

# Keep Parcelize classes
-keep class ** implements android.os.Parcelable {
    public static final android.os.Parcelable$Creator *;
}

# Keep exception classes
-keep public class * extends java.lang.Throwable

# Keep Support Library classes
-keep class android.support.** { *; }
-keep class androidx.** { *; }
-keep interface androidx.** { *; }

# Keep OkHttp
-keep class okhttp3.** { *; }
-keep interface okhttp3.** { *; }
-dontwarn okhttp3.**
-dontwarn okio.**

# Keep Gson
-keep class com.google.gson.** { *; }
-keep interface com.google.gson.** { *; }
-keep class * implements com.google.gson.TypeAdapterFactory
-keep class * implements com.google.gson.JsonSerializer
-keep class * implements com.google.gson.JsonDeserializer
-keepclassmembers class * {
    @com.google.gson.annotations.SerializedName <fields>;
}

# Keep Glide
-keep public class * implements com.bumptech.glide.module.GlideModule
-keep public class * extends com.bumptech.glide.module.AppGlideModule
-keep public enum com.bumptech.glide.load.ImageHeaderParser$** {
    **[] $VALUES;
    public *;
}

# Keep native methods
-keepclasseswithmembernames class * {
    native <methods>;
}

# Keep getter/setter methods
-keepclassmembers public class * {
    *** get*(...);
    void set*(...);
}

# Remove logging
-assumenosideeffects class android.util.Log {
    public static *** d(...);
    public static *** v(...);
    public static *** i(...);
}
