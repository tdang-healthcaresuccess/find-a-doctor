#!/bin/bash

# Find a Doctor Plugin - API Integration Test Script
# This script helps test the new API integration

echo "==================================="
echo "Find a Doctor Plugin - API Test"
echo "==================================="

# Check if we're in the right directory
if [ ! -f "find-a-doctor.php" ]; then
    echo "❌ Error: Please run this script from the plugin directory"
    echo "Expected to find find-a-doctor.php in current directory"
    exit 1
fi

echo "✅ Plugin directory found"

# Check if all new API files exist
api_files=(
    "includes/api-config.php"
    "includes/api-client.php"
    "includes/api-import.php"
    "includes/api-ajax.php"
    "includes/admin/api-import.php"
    "includes/api-test.php"
)

echo ""
echo "Checking API files..."
missing_files=0

for file in "${api_files[@]}"; do
    if [ -f "$file" ]; then
        echo "✅ $file"
    else
        echo "❌ $file (missing)"
        missing_files=$((missing_files + 1))
    fi
done

if [ $missing_files -gt 0 ]; then
    echo ""
    echo "❌ Error: $missing_files API files are missing"
    echo "Please ensure all API integration files have been created"
    exit 1
fi

echo ""
echo "✅ All API files are present"

# Check PHP syntax
echo ""
echo "Checking PHP syntax..."
syntax_errors=0

for file in "${api_files[@]}"; do
    if ! php -l "$file" > /dev/null 2>&1; then
        echo "❌ Syntax error in $file"
        php -l "$file"
        syntax_errors=$((syntax_errors + 1))
    else
        echo "✅ $file syntax OK"
    fi
done

if [ $syntax_errors -gt 0 ]; then
    echo ""
    echo "❌ Error: $syntax_errors files have PHP syntax errors"
    echo "Please fix the syntax errors before proceeding"
    exit 1
fi

echo ""
echo "✅ All PHP files have valid syntax"

# Check WordPress configuration
echo ""
echo "Checking WordPress configuration..."

# Look for wp-config.php in parent directories
wp_config=""
current_dir=$(pwd)
check_dir="$current_dir"

for i in {1..6}; do
    if [ -f "$check_dir/wp-config.php" ]; then
        wp_config="$check_dir/wp-config.php"
        break
    fi
    check_dir=$(dirname "$check_dir")
done

if [ -n "$wp_config" ]; then
    echo "✅ WordPress configuration found at: $wp_config"
else
    echo "⚠️  Warning: WordPress configuration not found"
    echo "   API test script may not work properly"
fi

# Generate test URLs
echo ""
echo "Testing URLs:"
echo "============="

# Try to determine the site URL
if [ -n "$wp_config" ]; then
    # Extract site URL from wp-config or make educated guess
    site_dir=$(dirname "$wp_config")
    relative_path=$(realpath --relative-to="$site_dir" "$current_dir")
    
    echo "Plugin test URL:"
    echo "http://localhost/$(basename "$site_dir")/$relative_path/includes/api-test.php"
    echo ""
else
    echo "Cannot determine site URL automatically."
    echo "Manual test URL (adjust as needed):"
    echo "http://your-site.com/wp-content/plugins/find-a-doctor/includes/api-test.php"
    echo ""
fi

echo "WordPress Admin URLs (after plugin activation):"
echo "- API Import: /wp-admin/admin.php?page=find-a-doctor"
echo "- File Import: /wp-admin/admin.php?page=file-import"
echo "- Doctor List: /wp-admin/admin.php?page=doctor-list"
echo ""

echo "REST API Endpoints:"
echo "- Local doctors: /wp-json/finddoctor/v1/doctors"
echo "- API search: /wp-json/finddoctor/v1/api/search"
echo "- API languages: /wp-json/finddoctor/v1/api/languages"
echo "- API hospitals: /wp-json/finddoctor/v1/api/hospitals"
echo ""

# Summary
echo "==================================="
echo "Setup Summary"
echo "==================================="
echo "✅ All required files are present"
echo "✅ PHP syntax is valid"
echo "✅ Plugin structure is correct"
echo ""
echo "Next steps:"
echo "1. Activate the plugin in WordPress admin"
echo "2. Visit the API test URL to verify connectivity"
echo "3. Use the API Import page to import physician data"
echo "4. Monitor the WordPress debug log for any issues"
echo ""
echo "🚀 API integration is ready for testing!"