# Find a Doctor - WordPress Plugin Development Guide

## Architecture Overview

This is a WordPress plugin that manages doctor profiles with a normalized database schema, Excel/CSV import capabilities, and dual API exposure (REST + GraphQL). The plugin follows a functional programming approach rather than OOP patterns.

## Core Components

### Database Schema
- **Main table**: `wp_doctors` - Primary doctor information with 35+ fields including medical credentials, contact info, and practice details
- **Junction tables**: `wp_doctor_specialties`, `wp_doctor_language`, `wp_doctor_zocdoc` - Many-to-many relationships
- **Reference tables**: `wp_specialties`, `wp_languages`, `wp_zocdoc` - Normalized lookup data
- **Key constraint**: Unique slug system for SEO-friendly doctor URLs (`fad_slugify_doctor()` in `includes/helpers.php`)

### File Structure Patterns
- `find-a-doctor.php` - Main plugin file with WordPress hooks and menu registration
- `includes/create-tables.php` - Database schema definitions with foreign key constraints
- `includes/import-data.php` - PHPSpreadsheet-based Excel/CSV import with column mapping
- `includes/rest-api.php` - Simple REST endpoints (`/wp-json/finddoctor/v1/doctors`)
- `includes/class-fad-graphql.php` - WPGraphQL integration (requires external plugin)
- `includes/admin/` - Admin page controllers following `fnd_render_*_page()` naming

### GraphQL Integration
- **Dependency**: Requires WPGraphQL plugin activation
- **Types**: `Doctor`, `DoctorList` with pagination support
- **Queries**: `doctorBySlug(slug)`, `doctorById(id)`, `doctorsList()` with filtering
- **Conditional loading**: GraphQL features only activate when WPGraphQL is available

## Development Conventions

### Function Naming
- All functions prefixed with `fnd_` (find doctor) or `fad_` (find a doctor)
- Admin page functions: `fnd_render_{page}_page()`
- AJAX handlers: `fnd_handle_{action}_ajax()`
- Helper functions: `fad_slugify_doctor()`, `fad_ensure_unique_slug()`

### Asset Management
- CSS/JS assets in `assets/` directory
- Conditional loading based on admin page hooks (`strpos($hook, 'find-a-doctor')`)
- ThickBox specifically loaded for reference data management page

### Data Import Process
1. File upload via AJAX form (`assets/js/custom.js`)
2. PHPSpreadsheet parsing for Excel/CSV files (`includes/import-data.php`)
3. Dynamic column mapping using `combine_from_columns()` helper
4. Automatic slug generation during import with collision handling

## Key Integration Points

### WordPress Hooks
- `admin_menu` - Plugin menu structure with submenu pages
- `rest_api_init` - REST API endpoint registration
- `graphql_register_types` - GraphQL schema extension (conditional)
- `admin_enqueue_scripts` - Asset loading with page-specific conditions

### External Dependencies
- **Composer autoloader**: `vendor/autoload.php` loaded in import functionality
- **PHPSpreadsheet**: For Excel/CSV processing
- **WPGraphQL**: Optional dependency for GraphQL API

### Database Patterns
- Use `global $wpdb` for all database operations
- Table names: `$wpdb->prefix . 'doctors'` pattern
- Foreign key constraints with CASCADE operations
- Index creation in `class-fad-activator.php` for performance

## Critical Workflows

### Doctor Import
```bash
# No CLI commands - all via WordPress admin interface
# Navigate to: wp-admin > Find a Doctor > Doctor Import
# Supports: .csv, .xlsx, .xls files with automatic column detection
```

### Slug Management
- Automatic slug generation: `firstname-lastname-degreeCode`
- Collision handling with numeric suffixes (`-2`, `-3`, etc.)
- One-time backfill available via `?backfill_doctor_slugs` query parameter

### API Access
- REST: `/wp-json/finddoctor/v1/doctors` (public access)
- GraphQL: Available when WPGraphQL plugin is active
- Admin notices guide missing dependency installation

## Common Patterns

When adding new fields to doctors table, update:
1. `includes/create-tables.php` - Database schema
2. `includes/class-fad-graphql.php` - GraphQL field definitions  
3. `includes/admin/doctor-edit.php` - Admin form fields
4. Import column mapping in `includes/import-data.php`

When creating new admin pages, follow the pattern in `includes/admin/` with `fnd_render_*_page()` functions and register via `add_submenu_page()` in main plugin file.