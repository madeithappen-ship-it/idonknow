package com.example.sidequest;

import android.annotation.SuppressLint;
import android.os.Build;
import android.os.Bundle;
import android.webkit.WebSettings;
import android.webkit.WebView;
import android.webkit.WebViewClient;
import android.widget.ProgressBar;
import androidx.appcompat.app.AppCompatActivity;
import android.webkit.WebChromeClient;

public class MainActivity extends AppCompatActivity {

    private WebView webView;
    private ProgressBar progressBar;
    private static final String APP_URL = "https://your-domain.com"; // Change this
    private static final String LOCAL_DEV = "http://10.0.2.2:8000"; // For emulator local testing

    @SuppressLint("SetJavaScriptEnabled")
    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_main);

        webView = findViewById(R.id.webview);
        progressBar = findViewById(R.id.progress_bar);

        // Configure WebView settings for optimal performance
        WebSettings webSettings = webView.getSettings();
        
        // Enable JavaScript and storage
        webSettings.setJavaScriptEnabled(true);
        webSettings.setDomStorageEnabled(true);
        webSettings.setDatabaseEnabled(true);
        
        // Performance optimizations
        webSettings.setAllowContentAccess(true);
        webSettings.setAllowFileAccess(true);
        webSettings.setMixedContentMode(WebSettings.MIXED_CONTENT_ALWAYS_ALLOW);
        webSettings.setUseWideViewPort(true);
        webSettings.setLoadWithOverviewMode(true);
        webSettings.setDefaultZoom(WebSettings.ZoomDensity.FAR);
        
        // User agent for better compatibility
        String userAgent = webSettings.getUserAgentString();
        webSettings.setUserAgentString(userAgent + " SideQuest/1.0");
        
        // Cache settings
        webSettings.setAppCacheEnabled(true);
        webSettings.setAppCachePath(getCacheDir().getAbsolutePath());
        webSettings.setCacheMode(WebSettings.LOAD_DEFAULT);
        
        // Allow geolocation if needed
        webSettings.setGeolocationEnabled(true);
        
        // Set up WebViewClient to handle internal navigation
        webView.setWebViewClient(new SideQuestWebViewClient());
        
        // Set up WebChromeClient for JavaScript dialogs and progress
        webView.setWebChromeClient(new WebChromeClient() {
            @Override
            public void onProgressChanged(WebView view, int newProgress) {
                super.onProgressChanged(view, newProgress);
                progressBar.setProgress(newProgress);
                progressBar.setVisibility(newProgress == 100 ? android.view.View.GONE : android.view.View.VISIBLE);
            }
        });

        // Load the app URL
        webView.loadUrl(APP_URL);
    }

    // Custom WebViewClient to handle internal links
    private static class SideQuestWebViewClient extends WebViewClient {
        @Override
        public boolean shouldOverrideUrlLoading(WebView view, String url) {
            if (url.contains(APP_URL) || url.contains("10.0.2.2")) {
                view.loadUrl(url);
                return true;
            }
            return false;
        }
    }

    // Handle back button to navigate within the app
    @Override
    public void onBackPressed() {
        if (webView.canGoBack()) {
            webView.goBack();
        } else {
            super.onBackPressed();
        }
    }

    @Override
    protected void onPause() {
        super.onPause();
        webView.onPause();
    }

    @Override
    protected void onResume() {
        super.onResume();
        webView.onResume();
    }

    @Override
    protected void onDestroy() {
        webView.destroy();
        super.onDestroy();
    }
}
