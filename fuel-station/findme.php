<?php
echo "<h1>🔍 FILE LOCATION FINDER</h1>";

echo "<div style='background: #e8f4fd; padding: 20px; margin: 10px; border-radius: 10px;'>";
echo "<h3>Server Information:</h3>";
echo "Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "<br>";
echo "Current File: " . __FILE__ . "<br>";
echo "Current Directory: " . __DIR__ . "<br>";
echo "Requested URL: " . $_SERVER['REQUEST_URI'] . "<br>";
echo "</div>";

echo "<div style='background: #fff3cd; padding: 20px; margin: 10px; border-radius: 10px;'>";
echo "<h3>Files in Document Root (" . $_SERVER['DOCUMENT_ROOT'] . "):</h3>";
$root_files = scandir($_SERVER['DOCUMENT_ROOT']);
echo "<ul>";
foreach ($root_files as $file) {
    if ($file != "." && $file != ".." && is_dir($_SERVER['DOCUMENT_ROOT'] . "\\" . $file)) {
        echo "<li>📁 $file</li>";
    }
}
echo "</ul>";
echo "</div>";

// Test if we can access this file
echo "<div style='background: #d4edda; padding: 20px; margin: 10px; border-radius: 10px;'>";
echo "<h3>✅ SUCCESS!</h3>";
echo "<p>If you can see this, PHP is working and this file is accessible!</p>";
echo "</div>";
?>