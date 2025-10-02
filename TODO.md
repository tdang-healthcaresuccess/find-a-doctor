# TODO - Development Roadmap

## High Priority

### 🔲 Specialty Custom Post Type Implementation
**Priority**: High  
**Estimated Effort**: Medium-Large  
**Description**: Implement specialty custom post type with slug functionality

**Requirements:**
- Create custom post type for specialties with proper slug handling
- Update URL rewrite rules for specialty-based routing  
- Enhance specialty management with WordPress native post capabilities
- Consider SEO implications and URL structure for specialty pages

**Technical Considerations:**
- Integration with existing normalized `wp_specialties` table
- URL structure: `/specialty/cardiology/` vs `/specialties/cardiology/`
- Custom post type registration with proper capabilities
- Template hierarchy for specialty archive and single pages
- Migration strategy from current reference data to custom post type
- Impact on existing GraphQL/REST API endpoints

**Files to Review/Update:**
- `includes/url-rewrite.php` - URL routing
- `includes/create-tables.php` - Database schema considerations
- `includes/class-fad-graphql.php` - API impact assessment
- `includes/admin/manage-reference-data.php` - Admin interface updates

**Related Issues:**
- Potential conflicts with existing specialty management
- SEO optimization for specialty landing pages
- Performance implications for large specialty datasets

---

## Medium Priority

### 🔲 Future Enhancements
*Add additional development tasks here as they come up*

---

## Development Notes

### When Working on Specialty Custom Post Type:

1. **Research Phase**:
   - Review current URL rewrite implementation in `includes/url-rewrite.php`
   - Analyze existing specialty data structure and usage patterns
   - Plan migration strategy from reference table to custom post type

2. **Implementation Phase**:
   - Register custom post type with appropriate capabilities
   - Update URL rewrite rules for specialty routing
   - Create template hierarchy for specialty pages
   - Update admin interface for specialty management

3. **Testing Phase**:
   - Test URL routing and slug generation
   - Validate existing API functionality remains intact
   - Performance testing with large specialty datasets
   - SEO verification for specialty pages

4. **Migration Phase**:
   - Plan data migration from `wp_specialties` to custom post type
   - Update existing relationships and references
   - Backward compatibility considerations

### Version Impact
- This will likely be a **MINOR** version bump (2.5.0) as it adds new functionality
- Consider **MAJOR** version if breaking changes to existing specialty handling

### Documentation Updates Needed
- Update API documentation for any endpoint changes
- Add specialty custom post type usage examples
- Update admin user guide for new specialty management interface