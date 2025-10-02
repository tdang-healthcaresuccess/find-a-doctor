# Changelog

All notable changes to the Find a Doctor WordPress plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### TODO - Future Development
- **Specialty Custom Post Type**: Implement specialty custom post type with slug functionality
  - Create custom post type for specialties with proper slug handling
  - Update URL rewrite rules for specialty-based routing
  - Enhance specialty management with WordPress native post capabilities
  - Consider SEO implications and URL structure for specialty pages

## [2.4.0] - 2025-10-01

### Added
- **GraphQL Search Enhancement**: Added degree and insurance search parameters to `doctorsList` query
  - New `degree` parameter for filtering by physician degrees (M.D., D.O., N.P., etc.)
  - New `insurance` parameter for filtering by insurance networks
  - **Multiple Specialties Support**: Enhanced `specialty` parameter to accept array of specialties
  - Case-insensitive search matching for all parameters
  - Efficient JOIN-based filtering using normalized relationship tables

### Enhanced
- **Specialty Filtering**: `specialty` parameter now supports multiple values with OR logic
  - Accepts array: `specialty: ["Cardiology", "Internal Medicine", "Family Medicine"]`
  - Backwards compatible: single specialty queries still work
  - Finds doctors who match ANY of the specified specialties
- GraphQL query description updated to reflect new search capabilities
- Database queries optimized with proper foreign key relationships
- Comprehensive test documentation for new search features

### Technical
- Updated `class-fad-graphql.php` with enhanced search functionality
- Added support for multiple specialty filtering with dynamic placeholder generation
- Added support for `wp_doctor_degrees` and `wp_doctor_insurance` table relationships
- Implemented comprehensive version management system
- Maintained backwards compatibility with existing queries

## [2.3.0] - Previous Release

### Added
- Complete insurance relationship normalization system
- Normalized database schema with junction tables for all reference data
- Degree processing and classification system
- API-based import processing with relationship handling

### Enhanced
- Import system with normalized relationship management
- GraphQL API with specialty and language filtering
- Admin interface with reference data management
- Degree type classification (medical, doctoral, masters, other)

### Fixed
- Degree processing in batch imports now properly normalizes to reference tables
- Degree type classification correctly identifies medical degrees
- Import pipeline properly handles all relationship types

### Technical
- Implemented `fad_update_physician_relationships()` function
- Enhanced database schema with comprehensive foreign key relationships
- Added normalized reference tables for specialties, languages, degrees, and insurances

## Version Update Instructions

### To update the plugin version:

1. **Update the main plugin file header**:
   ```php
   // In find-a-doctor.php, line 4:
   * Version: 2.4.0
   ```

2. **Add new changelog entry**:
   - Update this CHANGELOG.md file with new version details
   - Follow the format above for consistency

3. **Update any version constants** (if they exist):
   ```php
   // Check for any defined version constants in the codebase
   define('FAD_VERSION', '2.4.0');
   ```

4. **Test the changes**:
   - Verify plugin activation/deactivation
   - Test new features
   - Validate database migrations (if any)

### Versioning Guidelines

Follow [Semantic Versioning](https://semver.org/):

- **MAJOR** version (X.0.0): Breaking changes, incompatible API changes
- **MINOR** version (0.X.0): New features, backwards-compatible
- **PATCH** version (0.0.X): Bug fixes, backwards-compatible

### Common Change Categories

- **Added**: New features
- **Changed**: Changes in existing functionality  
- **Deprecated**: Soon-to-be removed features
- **Removed**: Now removed features
- **Fixed**: Bug fixes
- **Security**: Vulnerability fixes
- **Enhanced**: Improvements to existing features
- **Technical**: Under-the-hood improvements

### Example Entry Template

```markdown
## [X.Y.Z] - YYYY-MM-DD

### Added
- Description of new features

### Enhanced  
- Description of improvements

### Fixed
- Description of bug fixes

### Technical
- Description of internal changes
```