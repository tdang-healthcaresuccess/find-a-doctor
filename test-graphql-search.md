# GraphQL Search Enhancement - Test Queries

## New Search Capabilities

The `doctorsList` GraphQL query now supports searching by degrees and insurance networks in addition to the existing search parameters.

### Updated Query Arguments

```graphql
doctorsList(
  search: String
  specialty: String
  language: String
  gender: String
  primaryCare: Boolean
  degree: String        # NEW: Search by degree name (e.g., "M.D.", "D.O.", "N.P.")
  insurance: String     # NEW: Search by insurance network name
  page: Int
  perPage: Int
  orderBy: String
  order: String
)
```

### Example Queries

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
    specialty: "Cardiology"
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

### Database Joins
- **Degree filtering**: Uses `wp_doctor_degrees` junction table and `wp_degrees` reference table
- **Insurance filtering**: Uses `wp_doctor_insurance` junction table and `wp_insurances` reference table
- **Case-insensitive matching**: All searches use `LOWER()` for consistent results

### Query Performance
- Efficient JOIN operations with proper foreign key relationships
- Uses `DISTINCT` to avoid duplicate results when multiple relationships exist
- Pagination support for large result sets

## Testing Checklist

1. ✅ **Syntax validation** - PHP syntax is valid
2. 🔲 **Degree search** - Test filtering by degree names
3. 🔲 **Insurance search** - Test filtering by insurance networks  
4. 🔲 **Combined filters** - Test multiple parameters together
5. 🔲 **Case sensitivity** - Verify case-insensitive matching
6. 🔲 **Performance** - Check query execution time with large datasets

## Common Degree Values to Test
- M.D.
- D.O.
- N.P. (Nurse Practitioner)
- P.A. (Physician Assistant)
- D.P.M. (Podiatrist)
- Pharm.D. (Pharmacist)

## Common Insurance Networks to Test
- Blue Cross Blue Shield
- Aetna
- Cigna
- United Healthcare
- Humana
- Medicare