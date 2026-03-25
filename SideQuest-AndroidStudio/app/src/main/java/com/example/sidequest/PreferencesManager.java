package com.example.sidequest;

import android.content.Context;
import android.content.SharedPreferences;

/**
 * Utility class for managing app preferences and local storage
 */
public class PreferencesManager {
    
    private static final String PREFS_NAME = "SideQuestPrefs";
    private SharedPreferences sharedPreferences;

    public PreferencesManager(Context context) {
        sharedPreferences = context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE);
    }

    // User authentication
    public void setUserId(String userId) {
        sharedPreferences.edit().putString("user_id", userId).apply();
    }

    public String getUserId() {
        return sharedPreferences.getString("user_id", "");
    }

    public void setAuthToken(String token) {
        sharedPreferences.edit().putString("auth_token", token).apply();
    }

    public String getAuthToken() {
        return sharedPreferences.getString("auth_token", "");
    }

    public void setUsername(String username) {
        sharedPreferences.edit().putString("username", username).apply();
    }

    public String getUsername() {
        return sharedPreferences.getString("username", "");
    }

    // User preferences
    public void setLastUrl(String url) {
        sharedPreferences.edit().putString("last_url", url).apply();
    }

    public String getLastUrl() {
        return sharedPreferences.getString("last_url", "");
    }

    public void setDarkMode(boolean enabled) {
        sharedPreferences.edit().putBoolean("dark_mode", enabled).apply();
    }

    public boolean isDarkMode() {
        return sharedPreferences.getBoolean("dark_mode", false);
    }

    public void setNotificationsEnabled(boolean enabled) {
        sharedPreferences.edit().putBoolean("notifications_enabled", enabled).apply();
    }

    public boolean isNotificationsEnabled() {
        return sharedPreferences.getBoolean("notifications_enabled", true);
    }

    // App version tracking
    public void setAppVersion(String version) {
        sharedPreferences.edit().putString("app_version", version).apply();
    }

    public String getAppVersion() {
        return sharedPreferences.getString("app_version", "");
    }

    // First launch tracking
    public boolean isFirstLaunch() {
        boolean isFirstLaunch = sharedPreferences.getBoolean("is_first_launch", true);
        if (isFirstLaunch) {
            sharedPreferences.edit().putBoolean("is_first_launch", false).apply();
        }
        return isFirstLaunch;
    }

    // Cache management
    public void setCacheSize(long size) {
        sharedPreferences.edit().putLong("cache_size", size).apply();
    }

    public long getCacheSize() {
        return sharedPreferences.getLong("cache_size", 0);
    }

    // Clear all
    public void clearAll() {
        sharedPreferences.edit().clear().apply();
    }

    // Clear user data (on logout)
    public void clearUserData() {
        SharedPreferences.Editor editor = sharedPreferences.edit();
        editor.remove("user_id");
        editor.remove("auth_token");
        editor.remove("username");
        editor.apply();
    }
}
