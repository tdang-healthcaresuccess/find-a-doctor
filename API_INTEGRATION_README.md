# Find a Doctor Plugin - API Integration

## Overview

The Find a Doctor plugin has been refactored to use an external API for importing physician data instead of relying on file uploads. This provides real-time data synchronization and eliminates the need for manual file management.

## API Configuration

The plugin is configured to connect to the following API:

- **Hostname:** https://providersvc.altais.health
- **Username:** sysPvdPrd
- **Password:** Ua|q*.ppg{+([o!CB9NsA_9]%Bw{]V)F
- **Authentication:** Basic Auth

## Available Endpoints

The plugin integrates with the following API endpoints:

1. **Search Physicians:** `/api/provider/v1/fad/physicians/search`
2. **Get Physician:** `/api/provider/v1/fad/physicians/{id}`
3. **Physician by Term:** `/api/provider/v1/fad/physicians/{term}`
4. **Insurance Networks:** `/api/provider/v1/fad/networks`
5. **Languages:** `/api/provider/v1/fad/provider/languages`
6. **Hospital Affiliations:** `/api/provider/v1/fad/hospitalAffiliation`

## New Features

### 1. API Import Page

Navigate to **Find a Doctor > API Import** in the WordPress admin to access the new import functionality.

Features include:
- **Connection Test:** Verify API connectivity before importing
- **Physician Import:** Import doctors with optional search filters
- **Reference Data Sync:** Sync languages and hospital affiliations
- **Real-time Progress:** Live feedback during import operations

### 2. Search Parameters

When importing physicians, you can use the following optional filters:
- **Specialty:** Filter by medical specialty (e.g., "Cardiology")
- **Location:** Filter by geographic location (e.g., "New York, NY")
- **Limit:** Maximum number of physicians to import (1-1000)

### 3. Legacy File Import

The original file-based import is still available under **Find a Doctor > File Import (Legacy)** for backward compatibility.

## Technical Implementation

### File Structure

New files added:
- `includes/api-config.php` - API configuration and constants
- `includes/api-client.php` - HTTP client for API communication
- `includes/api-import.php` - Data import logic from API
- `includes/api-ajax.php` - AJAX handlers for admin interface
- `includes/admin/api-import.php` - Admin page for API operations
- `includes/api-test.php` - Standalone test script for API connectivity

### Data Mapping

The plugin automatically maps API response fields to database fields. The mapping function `fad_map_api_to_db_fields()` can be customized based on the actual API response structure.

### Error Handling

- Network errors are caught and displayed to users
- Invalid API responses are handled gracefully
- Authentication failures are reported with helpful messages
- Import errors are logged and displayed in the admin interface

## Testing the API

### Option 1: Admin Interface
1. Go to **Find a Doctor > API Import**
2. Click "Test API Connection"
3. Review the connection status

### Option 2: Direct Script
1. Navigate to `/wp-content/plugins/find-a-doctor/includes/api-test.php` in your browser
2. Review the detailed test results

## Usage Instructions

### Importing Physicians

1. **Navigate to API Import:**
   - Go to WordPress Admin > Find a Doctor > API Import

2. **Test Connection:**
   - Click "Test API Connection" to verify connectivity
   - Ensure you see a success message before proceeding

3. **Configure Import Parameters (Optional):**
   - Specialty: Enter specific medical specialty to filter results
   - Location: Enter city/state to filter by geographic location
   - Limit: Set maximum number of physicians to import

4. **Start Import:**
   - Click "Start Import" to begin the process
   - Monitor the progress indicator
   - Review the results when complete

### Syncing Reference Data

1. **Navigate to API Import page**
2. **Click "Sync Reference Data"**
3. **Review sync results for:**
   - Languages synced
   - Hospital affiliations synced

## Data Processing

### Physician Data
- **Deduplication:** Physicians are identified by `idme` field to prevent duplicates
- **Updates:** Existing physicians are updated with new data from API
- **Required Fields:** Address, city, state, and zip are required for import
- **Status Filtering:** Physicians with "Terminated" status are automatically skipped

### Relationships
- **Specialties:** Automatically created and linked to physicians
- **Languages:** Automatically created and linked to physicians
- **Batch Processing:** Relationships are inserted in batches for performance

### Slug Generation
- **Automatic:** SEO-friendly slugs are generated for new physicians
- **Format:** `firstname-lastname-degreecode`
- **Uniqueness:** Collision handling with numeric suffixes

## Troubleshooting

### Common Issues

1. **Connection Failed:**
   - Verify API credentials in `includes/api-config.php`
   - Check network connectivity
   - Ensure WordPress can make external HTTP requests

2. **Import Errors:**
   - Review error messages in the admin interface
   - Check API response format matches expected structure
   - Verify required fields are present in API data

3. **Authentication Issues:**
   - Confirm username and password are correct
   - Check if API requires IP whitelisting
   - Verify Basic Auth is properly configured

### Debug Mode

Enable WordPress debug mode to see detailed error messages:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

Check `/wp-content/debug.log` for detailed error information.

## Customization

### API Response Mapping

To customize how API fields map to database fields, modify the `fad_map_api_to_db_fields()` function in `includes/api-import.php`.

### Search Parameters

Add custom search parameters by modifying the API import form in `includes/admin/api-import.php` and updating the API client methods.

### Error Handling

Customize error messages and handling in the respective AJAX handlers in `includes/api-ajax.php`.

## Security Considerations

- API credentials are stored in PHP constants (not in database)
- AJAX requests are nonce-protected
- Admin capabilities are verified for all operations
- API responses are sanitized before database insertion

## Performance

- Batch processing for relationship data
- Connection pooling for multiple API requests
- Progress indicators for long-running operations
- Configurable import limits to prevent timeouts

## Support

For technical issues or questions:
1. Check the error logs in WordPress admin
2. Review the API test results
3. Verify all required plugins are activated
4. Ensure proper user permissions are in place