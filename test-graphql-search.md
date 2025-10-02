# GraphQL Search Enhancement - Test Queries

## New Search Capabilities

The `doctorsList` GraphQL query now supports searching by degrees and insurance networks in addition to the existing search parameters.

### Updated Query Arguments

```graphql
doctorsList(
  search: String
  specialty: [String]       # UPDATED: Now accepts array of specialties
  language: String
  gender: String
  primaryCare: Boolean
  degree: String
  insurance: String
  page: Int
  perPage: Int
  orderBy: String
  order: String
)
```

### Example Queries

#### Search by Multiple Specialties (NEW)
```graphql
query {
  doctorsList(specialty: ["Cardiology", "Internal Medicine", "Family Medicine"], page: 1, perPage: 10) {
    items {
      first_name
      last_name
      practice_name
      specialties
    }
    total
    page
    perPage
  }
}
```

#### Search by Single Specialty (Backwards Compatible)
```graphql
query {
  doctorsList(specialty: ["Cardiology"], page: 1, perPage: 10) {
    items {
      first_name
      last_name
      practice_name
      specialties
    }
    total
    page
    perPage
  }
}
```

#### Search by Degree
```graphql
query {
  doctorsList(degree: "M.D.", page: 1, perPage: 10) {
    items {
      first_name
      last_name
      degree
      practice_name
    }
    total
    page
    perPage
  }
}
```

#### Search by Insurance Network
```graphql
query {
  doctorsList(insurance: "Blue Cross Blue Shield", page: 1, perPage: 10) {
    items {
      first_name
      last_name
      practice_name
      phone
    }
    total
    page
    perPage
  }
}
```

#### Combined Search (Multiple Filters)
```graphql
query {
  doctorsList(
    specialty: ["Cardiology", "Orthopedic Surgery"]
    degree: "M.D."
    insurance: "Aetna"
    gender: "Female"
    primaryCare: false
    page: 1
    perPage: 20
  ) {
    items {
      first_name
      last_name
      degree
      gender
      practice_name
      phone
      specialties
    }
    total
    page
    perPage
  }
}
```

#### Real-World Example: Find Primary Care Providers
```graphql
query {
  doctorsList(
    specialty: ["Family Medicine", "Internal Medicine", "Pediatrics"]
    primaryCare: true
    insurance: "Blue Cross Blue Shield"
    page: 1
    perPage: 25
  ) {
    items {
      first_name
      last_name
      practice_name
      phone
      specialties
      degrees
    }
    total
    page
    perPage
  }
}
```

#### Search with Text + Degree Filter
```graphql
query {
  doctorsList(
    search: "smith"
    degree: "D.O."
    page: 1
    perPage: 15
  ) {
    items {
      first_name
      last_name
      degree
      practice_name
    }
    total
    page
    perPage
  }
}
```

## Implementation Details

### Multiple Specialties Logic
- **Array input**: `specialty: ["Cardiology", "Dermatology", "Neurology"]`
- **SQL logic**: Uses `OR` conditions to match doctors who have ANY of the specified specialties
- **Backwards compatibility**: Single specialty queries still work (must be in array format)
- **Case-insensitive**: All specialty matching uses `LOWER()` for consistent results

### Database Joins
- **Degree filtering**: Uses `wp_doctor_degrees` junction table and `wp_degrees` reference table
- **Insurance filtering**: Uses `wp_doctor_insurance` junction table and `wp_insurances` reference table
- **Specialty filtering**: Uses `wp_doctor_specialties` junction table and `wp_specialties` reference table
- **Language filtering**: Uses `wp_doctor_language` junction table and `wp_languages` reference table
- **Case-insensitive matching**: All searches use `LOWER()` for consistent results

### Query Performance
- Efficient JOIN operations with proper foreign key relationships
- Uses `DISTINCT` to avoid duplicate results when multiple relationships exist
- Pagination support for large result sets

## Testing Checklist

1. ✅ **Syntax validation** - PHP syntax is valid
2. 🔲 **Multiple specialties search** - Test filtering by array of specialties
3. 🔲 **Single specialty backwards compatibility** - Test single specialty in array format
4. 🔲 **Degree search** - Test filtering by degree names
5. 🔲 **Insurance search** - Test filtering by insurance networks  
6. 🔲 **Combined filters** - Test multiple parameters together including specialty array
7. 🔲 **Case sensitivity** - Verify case-insensitive matching
8. 🔲 **Performance** - Check query execution time with large datasets and multiple specialties

## Common Specialty Combinations to Test
- Primary Care: `["Family Medicine", "Internal Medicine", "Pediatrics"]`
- Heart Specialists: `["Cardiology", "Cardiovascular Surgery", "Cardiothoracic Surgery"]`
- Women's Health: `["Obstetrics & Gynecology", "Gynecology", "Maternal-Fetal Medicine"]`
- Mental Health: `["Psychiatry", "Psychology", "Behavioral Health"]`
- Surgery: `["General Surgery", "Orthopedic Surgery", "Plastic Surgery"]`

## Common Insurance Networks to Test
- Blue Cross Blue Shield
- Aetna
- Cigna
- United Healthcare
- Humana
- Medicare