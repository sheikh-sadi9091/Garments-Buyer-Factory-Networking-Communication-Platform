<?php
declare(strict_types=1);

$dashboardPath = dirname(__DIR__) . '/dashboard.php';
$dashboardSource = file_get_contents($dashboardPath);
if ($dashboardSource === false) {
    throw new RuntimeException('Unable to read dashboard.php.');
}

$requiredInclude = "require_once __DIR__ . '/includes/domain.php';";
if (!str_contains($dashboardSource, $requiredInclude)) {
    throw new RuntimeException('Dashboard must load the domain helpers before calling current_company().');
}

require_once dirname(__DIR__) . '/includes/domain.php';
if (!function_exists('current_company')) {
    throw new RuntimeException('The domain helpers do not define current_company().');
}

echo "PASS dashboard loads current_company() before use.\n";
