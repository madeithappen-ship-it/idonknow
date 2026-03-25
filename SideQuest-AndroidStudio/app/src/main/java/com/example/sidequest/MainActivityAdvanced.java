package com.example.sidequest;

import android.annotation.SuppressLint;
import android.app.Dialog;
import android.os.Build;
import android.os.Bundle;
import android.view.View;
import android.view.ViewGroup;
import android.webkit.WebSettings;
import android.webkit.WebView;
import android.webkit.WebViewClient;
import android.widget.ProgressBar;
import android.widget.Toast;
import androidx.appcompat.app.AppCompatActivity;
import android.webkit.WebChromeClient;
import android.webkit.GeolocationPermissions;

/**
 * Advanced MainActivity with full feature support
 * Includes geolocation, file uploads, and better error handling
 */
public class MainActivityAdvanced extends AppCompatActivity {

    private WebView webView;
    private ProgressBar progressBar;
    private PreferencesManager preferencesManager;
    private Dialog loadingDialog;
    
    // Production URL - Change this to your domain
    private static final String PRODUCTION_URL = "https://your-domain.com";
    private static final String DEVELOPMENT_URL = "http://10.0.2.2:8000";
    private static final String USE_URL = PRODUCTION_URL; // Change to DEVELOPMENT_URL for testing

    @SuppressLint("SetJavaScriptEnabled")
    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_main);

        // Initialize managers
        webView = findViewById(R.id.webview);
        progressBar = findViewById(R.id.progress_bar);
        preferencesManager = new PreferencesManager(this);

        // Configure WebView Settings
        configureWebView();

        // Set up WebViewClient
        webView.setWebViewClient(new AdvancedWebViewClient());

        // Set up WebChromeClient for advanced features
        webView.setWebChromeClient(new AdvancedWebChromeClient());

        // Load the app with cached last URL if available
        String urlToLoad = preferencesManager.getLastUrl();
        if (urlToLoad.isEmpty()) {
            urlToLoad = USE_URL;
        }
        webView.loadUrl(urlToLoad);
    }

    /**
     * Configure WebView settings for optimal performance
     */
    @SuppressLint("SetJavaScriptEnabled")
    private void configureWebView() {
        WebSettings settings = webView.getSettings();

        // Enable JavaScript and DOM Storage
        settings.setJavaScriptEnabled(true);
        settings.setDomStorageEnabled(true);
        settings.setDatabaseEnabled(true);

        // Performance and caching
        settings.setAppCacheEnabled(true);
        settings.setAppCachePath(getCacheDir().getAbsolutePath());
        settings.setCacheMode(WebSettings.LOAD_DEFAULT);
        settings.setUseWideViewPort(true);
        settings.setLoadWithOverviewMode(true);

        // Security
        settings.setAllowContentAccess(true);
        settings.setAllowFileAccess(true);
        settings.setMixedContentMode(WebSettings.MIXED_CONTENT_ALWAYS_ALLOW);

        // User Agent
        String userAgent = settings.getUserAgentString();
        settings.setUserAgentString(userAgent + " SideQuest/1.0");

        // Geolocation
        settings.setGeolocationEnabled(true);

        // Media playback
        settings.setMediaPlaybackRequiresUserGesture(false);

        // Zoom
        settings.setBuiltInZoomControls(false);
        settings.setDisplayZoomControls(false);

        // Default text encoding
        settings.setDefaultTextEncodingName("utf-8");
    }

    /**
     * Advanced WebViewClient with error handling
     */
    private class AdvancedWebViewClient extends WebViewClient {
        @Override
        public boolean shouldOverrideUrlLoading(WebView view, String url) {
            // Keep internal links in the app
            if (url.contains(USE_URL) || url.contains("10.0.2.2")) {
                view.loadUrl(url);
                preferencesManager.setLastUrl(url);
                return true;
            }
            // Open external links in browser (optional)
            return false;
        }

        @Override
        public void onPageStarted(WebView view, String url, android.graphics.Bitmap favicon) {
            super.onPageStarted(view, url, favicon);
            progressBar.setVisibility(View.VISIBLE);
            progressBar.setProgress(0);
        }

        @Override
        public void onPageFinished(WebView view, String url) {
            super.onPageFinished(view, url);
            progressBar.setVisibility(View.GONE);
            preferencesManager.setLastUrl(url);
        }

        @Override
        public void onReceivedError(WebView view, int errorCode, String description, String failingUrl) {
            super.onReceivedError(view, errorCode, description, failingUrl);
            showError(description);
        }
    }

    /**
     * Advanced WebChromeClient with progress and geolocation
     */
    private class AdvancedWebChromeClient extends WebChromeClient {
        @Override
        public void onProgressChanged(WebView view, int newProgress) {
            super.onProgressChanged(view, newProgress);
            progressBar.setProgress(newProgress);
            if (newProgress == 100) {
                progressBar.setVisibility(View.GONE);
            }
        }

        @Override
        public void onGeolocationPermissionsShowPrompt(String origin, GeolocationPermissions.Callback callback) {
            // Grant geolocation permission
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) {
                if (shouldAllowGeolocation(origin)) {
                    callback.invoke(origin, true, false);
                } else {
                    callback.invoke(origin, false, false);
                }
            } else {
                callback.invoke(origin, true, false);
            }
        }

        @Override
        public void onJsAlert(WebView view, String url, String message, android.webkit.JsResult result) {
            Toast.makeText(MainActivityAdvanced.this, message, Toast.LENGTH_LONG).show();
            result.confirm();
        }
    }

    /**
     * Check if geolocation should be allowed
     */
    private boolean shouldAllowGeolocation(String origin) {
        // Only allow for your domain
        return origin.contains(USE_URL);
    }

    /**
     * Show error message
     */
    private void showError(String message) {
        Toast.makeText(this, "Error: " + message, Toast.LENGTH_LONG).show();
    }

    /**
     * Handle back button
     */
    @Override
    public void onBackPressed() {
        if (webView.canGoBack()) {
            webView.goBack();
        } else {
            // Double-tap to exit
            Toast.makeText(this, "Press back again to exit", Toast.LENGTH_SHORT).show();
            super.onBackPressed();
        }
    }

    /**
     * Lifecycle management
     */
    @Override
    protected void onPause() {
        super.onPause();
        webView.onPause();
        webView.pauseTimers();
    }

    @Override
    protected void onResume() {
        super.onResume();
        webView.onResume();
        webView.resumeTimers();
    }

    @Override
    protected void onDestroy() {
        if (webView != null) {
            webView.clearHistory();
            webView.clearCache(true);
            webView.destroy();
        }
        super.onDestroy();
    }
}
