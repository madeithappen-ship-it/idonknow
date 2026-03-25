#!/usr/bin/php
<?php
/**
 * Performance Optimization Verification Tool
 * Checks if all optimizations are properly installed
 * Run: php verify-performance.php
 */

echo "\n╔════════════════════════════════════════════════════════════════╗\n";
echo "║  Performance Optimization Verification Tool                     ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

$checks = [
    'success' => 0,
    'warning' => 0,
    'error' => 0,
];

$results = [];

// Check 1: Cache file exists
echo "1️⃣  Checking cache layer... ";
if (file_exists(__DIR__ . '/cache.php')) {
    echo "✅ OK\n";
    $checks['success']++;
} else {
    echo "❌ MISSING\n";
    $checks['error']++;
}

// Check 2: Query optimizer exists
echo "2️⃣  Checking query optimizer... ";
if (file_exists(__DIR__ . '/query-optimizer.php')) {
    echo "✅ OK\n";
    $checks['success']++;
} else {
    echo "❌ MISSING\n";
    $checks['error']++;
}

// Check 3: API batch endpoint exists
echo "3️⃣  Checking API batch endpoint... ";
if (file_exists(__DIR__ . '/api_batch.php')) {
    echo "✅ OK\n";
    $checks['success']++;
} else {
    echo "❌ MISSING\n";
    $checks['error']++;
}

// Check 4: Performance indexes SQL exists
echo "4️⃣  Checking performance indexes... ";
if (file_exists(__DIR__ . '/performance-indexes.sql')) {
    echo "✅ OK\n";
    $checks['success']++;
} else {
    echo "❌ MISSING\n";
    $checks['error']++;
}

// Check 5: Cache directory
echo "5️⃣  Checking cache directory... ";
if (is_dir(__DIR__ . '/cache')) {
    if (is_writable(__DIR__ . '/cache')) {
        echo "✅ OK\n";
        $checks['success']++;
    } else {
        echo "⚠️  EXISTS BUT NOT WRITABLE\n";
        $checks['warning']++;
    }
} else {
    echo "⚠️  MISSING (will be created)\n";
    $checks['warning']++;
}

// Check 6: Config.php includes
echo "6️⃣  Checking config.php integration... ";
$config = file_get_contents(__DIR__ . '/config.php');
if (strpos($config, 'cache.php') !== false && strpos($config, 'query-optimizer.php') !== false) {
    echo "✅ OK\n";
    $checks['success']++;
} else {
    echo "❌ NOT INTEGRATED\n";
    $checks['error']++;
}

// Check 7: Dashboard uses caching
echo "7️⃣  Checking dashboard optimization... ";
$dashboard = file_get_contents(__DIR__ . '/dashboard.php');
if (strpos($dashboard, 'cache_remember') !== false) {
    echo "✅ OK\n";
    $checks['success']++;
} else {
    echo "⚠️  NOT USING CACHE\n";
    $checks['warning']++;
}

// Check 8: PHP Settings
echo "8️⃣  Checking PHP configuration... ";
$required_extensions = ['pdo', 'pdo_mysql'];
$missing = [];
foreach ($required_extensions as $ext) {
    if (!extension_loaded($ext)) {
        $missing[] = $ext;
    }
}

if (empty($missing)) {
    echo "✅ OK\n";
    $checks['success']++;
} else {
    echo "❌ MISSING: " . implode(', ', $missing) . "\n";
    $checks['error']++;
}

// Check 9: OPCache
echo "9️⃣  Checking OPCache... ";
if (extension_loaded('Zend OPcache')) {
    if (ini_get('opcache.enable')) {
        echo "✅ OK (enabled)\n";
        $checks['success']++;
    } else {
        echo "⚠️  INSTALLED BUT DISABLED\n";
        $checks['warning']++;
    }
} else {
    echo "⚠️  NOT INSTALLED\n";
    $checks['warning']++;
}

// Check 10: GZIP Support
echo "🔟 Checking GZIP support... ";
if (extension_loaded('zlib')) {
    echo "✅ OK\n";
    $checks['success']++;
} else {
    echo "⚠️  NOT AVAILABLE\n";
    $checks['warning']++;
}

// Check 11: Try including cache
echo "1️⃣ 1️⃣  Testing cache initialization... ";
try {
    require_once(__DIR__ . '/config.php');
    if (function_exists('cache_get')) {
        echo "✅ OK\n";
        $checks['success']++;
    } else {
        echo "❌ FUNCTION NOT FOUND\n";
        $checks['error']++;
    }
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    $checks['error']++;
}

// Check 12: Database connection
echo "1️⃣ 2️⃣  Testing database... ";
try {
    $stmt = $pdo->query("SELECT 1");
    if ($stmt) {
        echo "✅ OK\n";
        $checks['success']++;
    } else {
        echo "❌ QUERY FAILED\n";
        $checks['error']++;
    }
} catch (Exception $e) {
    echo "⚠️  NOT AVAILABLE (will work in web mode)\n";
    $checks['warning']++;
}

// Print Summary
echo "\n╔════════════════════════════════════════════════════════════════╗\n";
echo "║  Summary                                                         ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

$total = $checks['success'] + $checks['warning'] + $checks['error'];
$percentage = round(($checks['success'] / $total) * 100, 1);

echo "✅ Success:  {$checks['success']}/{$total}\n";
echo "⚠️  Warning: {$checks['warning']}/{$total}\n";
echo "❌ Error:    {$checks['error']}/{$total}\n";
echo "\nOverall: {$percentage}%\n\n";

// Status
if ($checks['error'] === 0) {
    if ($percentage >= 90) {
        echo "🟢 STATUS: READY FOR PRODUCTION\n";
    } else {
        echo "🟡 STATUS: READY WITH WARNINGS\n";
    }
} else {
    echo "🔴 STATUS: INCOMPLETE - NEEDS FIXES\n";
}

// Recommendations
echo "\n╔════════════════════════════════════════════════════════════════╗\n";
echo "║  Next Steps                                                      ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

if ($checks['error'] > 0) {
    echo "⚠️  Fix errors above before proceeding.\n";
} else if ($checks['warning'] > 0) {
    echo "✅ Most checks passed! Optional improvements:\n";
    if (!is_dir(__DIR__ . '/cache') || !is_writable(__DIR__ . '/cache')) {
        echo "  • Create/fix cache directory: mkdir -p cache && chmod 755 cache\n";
    }
    if (!extension_loaded('Zend OPcache') || !ini_get('opcache.enable')) {
        echo "  • Enable OPCache in PHP configuration\n";
    }
} else {
    echo "🟢 All checks passed! You're ready to go!\n";
}

echo "\n📋 Final Setup Steps:\n";
echo "  1. Run: php apply-performance.php\n";
echo "  2. Clear cache: rm -rf cache/*\n";
echo "  3. Test dashboard: curl -I https://your-domain/dashboard.php\n";
echo "  4. Check Network tab in browser DevTools\n";

echo "\n📚 Documentation:\n";
echo "  • PERFORMANCE.md - Overview\n";
echo "  • DEPLOYMENT.md - Deployment steps\n";
echo "  • QUICK_REFERENCE.md - Commands collection\n";
echo "  • OPTIMIZATION_SUMMARY.md - Complete summary\n";

echo "\n";
?>
