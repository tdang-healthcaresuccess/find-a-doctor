# GraphQL Search Enhancement Summary - AI Prompt Instructions

## Request 1: Multiple Specialties Support
**Issue**: GraphQL `doctorsList` query only supported single specialty search
**Solution**: Enhanced `specialty` parameter to accept array with OR logic
**Behavior**: `specialty: ["Cardiology", "Internal Medicine"]` finds doctors with ANY of these specialties

## Request 2: OR Logic for All Filter Arrays  
**Issue**: Language and insurance filters used restrictive AND logic when multiple values selected
**Desired**: When user selects "English" AND "Spanish", show doctors who speak EITHER language (not both required)
**Solution**: Updated `language`, `insurance`, `gender`, and `degree` parameters to accept arrays with OR logic

## Technical Implementation Required:

### GraphQL Schema Updates:
```graphql
# Change from single strings to arrays
specialty: [String]   # OR logic: ANY of these specialties
language: [String]    # OR logic: ANY of these languages  
insurance: [String]   # OR logic: ANY of these insurances
gender: [String]      # OR logic: ANY of these genders
degree: [String]      # OR logic: ANY of these degrees
```

### SQL Logic Updates:
```sql
-- Example for multiple specialties
WHERE (LOWER(s.specialty_name) = LOWER(%s) OR LOWER(s.specialty_name) = LOWER(%s))

-- Example for multiple languages  
WHERE (LOWER(l.language) = LOWER(%s) OR LOWER(l.language) = LOWER(%s))
```

### Key Requirements:
1. **OR Logic**: Arrays use OR conditions (find doctors matching ANY value)
2. **Backwards Compatibility**: Single values still work (must be in array format)
3. **Case Insensitive**: All matching uses LOWER() for consistency
4. **Dynamic Placeholders**: Generate correct number of %s placeholders for array length

### Files to Update:
- `includes/class-fad-graphql.php` - GraphQL schema and resolver logic
- Update args definitions to accept `['list_of' => 'String']`
- Update filtering logic with `array_fill()` and `implode(' OR ')` for dynamic placeholders
- Maintain existing JOIN patterns with normalized tables

### Expected Behavior:
```graphql
# Find doctors who speak English OR Spanish (not requiring both)
doctorsList(language: ["English", "Spanish"])

# Find doctors with M.D. OR D.O. degrees  
doctorsList(degree: ["M.D.", "D.O."])

# Find doctors who accept Aetna OR Cigna insurance
doctorsList(insurance: ["Aetna", "Cigna"])
```

This enhances user experience by providing more inclusive search results when multiple options are selected.