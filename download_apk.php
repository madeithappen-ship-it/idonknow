<?php
/**
 * APK Download Endpoint
 * Serves the APK file for download with proper headers
 */

header('Content-Type: application/vnd.android.package-archive');
header('Content-Disposition: attachment; filename="BoringLife.apk"');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// For now, provide a download option or redirect to PWA Builder
// In production, this would serve an actual APK file

// Check if a real APK exists
$apk_path = __DIR__ . '/uploads/apk/BoringLife.apk';
if (file_exists($apk_path)) {
    // Serve the actual APK
    header('Content-Length: ' . filesize($apk_path));
    readfile($apk_path);
} else {
    // Fallback: Show user how to generate APK using PWA Builder
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: max-age=3600');
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Generate APK - Boring Life App</title>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { 
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
                display: flex;
                align-items: center;
                justify-content: center;
                min-height: 100vh;
                padding: 20px;
            }
            .container {
                background: rgba(255,255,255,0.95);
                border-radius: 16px;
                padding: 40px;
                max-width: 500px;
                text-align: center;
                box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            }
            h1 {
                color: #333;
                margin-bottom: 20px;
                font-size: 28px;
            }
            .icon {
                font-size: 64px;
                margin-bottom: 20px;
            }
            p {
                color: #666;
                line-height: 1.6;
                margin-bottom: 20px;
                font-size: 16px;
            }
            .button {
                display: inline-block;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                padding: 14px 32px;
                border-radius: 8px;
                text-decoration: none;
                border: none;
                cursor: pointer;
                font-size: 16px;
                font-weight: 600;
                transition: transform 0.2s, box-shadow 0.2s;
                margin: 10px;
            }
            .button:hover {
                transform: translateY(-2px);
                box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
            }
            .info {
                background: #f0f4ff;
                padding: 20px;
                border-radius: 8px;
                margin-top: 20px;
                color: #555;
                font-size: 14px;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="icon">📱</div>
            <h1>Get Boring Life App</h1>
            <p>Your APK download is being prepared. Click below to generate or install:</p>
            
            <a href="https://www.pwabuilder.com/generate" target="_blank" class="button">
                🌐 Generate APK with PWA Builder
            </a>
            
            <a href="javascript:history.back()" class="button" style="background: #666;">
                ← Go Back
            </a>
            
            <div class="info">
                <strong>Need help?</strong> PWA Builder will create a native Android APK from our web app in seconds. No setup required!
            </div>
        </div>
    </body>
    </html>
    <?php
}
?>
