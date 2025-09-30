# Find a Doctor - Faust.js Integration Guide

## 🚀 Headless WordPress Setup for WP Engine + Faust.js

### **API Endpoints Available**

#### **REST API**
```
GET /wp-json/fad/v1/physicians                 # List physicians with filters
GET /wp-json/fad/v1/physician/{slug}           # Single physician by slug
GET /wp-json/fad/v1/specialties               # All specialties
GET /wp-json/fad/v1/languages                 # All languages
GET /wp-json/fad/v1/sitemap                   # Sitemap data for SEO
```

#### **GraphQL Queries**
```graphql
query GetPhysicians($search: String, $specialty: String, $first: Int) {
  physicians(search: $search, specialty: $specialty, first: $first) {
    id
    slug
    firstName
    lastName
    degree
    specialties
    languages
    location {
      city
      state
      coordinates {
        latitude
        longitude
      }
    }
  }
}

query GetPhysicianBySlug($slug: String!) {
  doctorBySlug(slug: $slug) {
    id
    firstName
    lastName
    degree
    biography
    profileImageUrl
    specialties
    languages
    phoneNumber
    address
    city
    state
  }
}
```

### **Next.js Components (Faust.js)**

#### **1. Physician List Page**
```tsx
// pages/physicians/index.tsx
import { GetStaticProps } from 'next'
import { gql } from '@apollo/client'
import { getNextStaticProps } from '@faustwp/core'

const GET_PHYSICIANS = gql`
  query GetPhysicians($first: Int, $search: String) {
    physicians(first: $first, search: $search) {
      id
      slug
      firstName
      lastName
      degree
      specialties
      location {
        city
        state
      }
    }
    specialties
  }
`

export default function PhysiciansPage({ physicians, specialties }) {
  return (
    <div className="physicians-directory">
      <h1>Find a Doctor</h1>
      
      <div className="physicians-grid">
        {physicians.map(physician => (
          <PhysicianCard key={physician.id} physician={physician} />
        ))}
      </div>
    </div>
  )
}

export const getStaticProps: GetStaticProps = async (context) => {
  return getNextStaticProps(context, {
    Page: PhysiciansPage,
    query: GET_PHYSICIANS,
    variables: {
      first: 20
    }
  })
}
```

#### **2. Single Physician Page**
```tsx
// pages/physicians/[slug].tsx
import { GetStaticPaths, GetStaticProps } from 'next'
import { gql } from '@apollo/client'
import { getNextStaticProps, getNextStaticPaths } from '@faustwp/core'

const GET_PHYSICIAN = gql`
  query GetPhysician($slug: String!) {
    doctorBySlug(slug: $slug) {
      id
      firstName
      lastName
      degree
      biography
      profileImageUrl
      specialties
      languages
      phoneNumber
      address
      city
      state
      latitude
      longitude
    }
  }
`

const GET_PHYSICIAN_SLUGS = gql`
  query GetPhysicianSlugs {
    physicians(first: 1000) {
      slug
    }
  }
`

export default function PhysicianPage({ doctorBySlug: physician }) {
  if (!physician) {
    return <div>Physician not found</div>
  }

  return (
    <div className="physician-profile">
      <header>
        <h1>{physician.firstName} {physician.lastName} {physician.degree}</h1>
      </header>
      
      <div className="physician-content">
        {physician.profileImageUrl && (
          <img src={physician.profileImageUrl} alt={`${physician.firstName} ${physician.lastName}`} />
        )}
        
        <div className="physician-details">
          {physician.specialties?.length > 0 && (
            <div>
              <h3>Specialties</h3>
              <ul>
                {physician.specialties.map(specialty => (
                  <li key={specialty}>{specialty}</li>
                ))}
              </ul>
            </div>
          )}
          
          <div className="contact-info">
            <h3>Contact Information</h3>
            {physician.phoneNumber && (
              <p>Phone: <a href={`tel:${physician.phoneNumber}`}>{physician.phoneNumber}</a></p>
            )}
            {(physician.address || physician.city) && (
              <p>
                {physician.address && `${physician.address}, `}
                {physician.city && physician.state && `${physician.city}, ${physician.state}`}
              </p>
            )}
          </div>
          
          {physician.biography && (
            <div className="biography">
              <h3>About Dr. {physician.lastName}</h3>
              <p>{physician.biography}</p>
            </div>
          )}
        </div>
      </div>
    </div>
  )
}

export const getStaticPaths: GetStaticPaths = async () => {
  return getNextStaticPaths({
    query: GET_PHYSICIAN_SLUGS,
    mapPathsFromQuery: (physicians) => {
      return physicians.map(physician => ({
        params: { slug: physician.slug }
      }))
    }
  })
}

export const getStaticProps: GetStaticProps = async (context) => {
  return getNextStaticProps(context, {
    Page: PhysicianPage,
    query: GET_PHYSICIAN,
    variables: {
      slug: context.params?.slug
    }
  })
}
```

#### **3. Reusable Components**
```tsx
// components/PhysicianCard.tsx
interface PhysicianCardProps {
  physician: {
    id: string
    slug: string
    firstName: string
    lastName: string
    degree?: string
    specialties?: string[]
    location?: {
      city?: string
      state?: string
    }
  }
}

export function PhysicianCard({ physician }: PhysicianCardProps) {
  return (
    <div className="physician-card">
      <h3>
        <Link href={`/physicians/${physician.slug}`}>
          {physician.firstName} {physician.lastName} {physician.degree}
        </Link>
      </h3>
      
      {physician.specialties && (
        <p className="specialties">
          {physician.specialties.slice(0, 2).join(', ')}
          {physician.specialties.length > 2 && ` +${physician.specialties.length - 2} more`}
        </p>
      )}
      
      {physician.location?.city && (
        <p className="location">{physician.location.city}, {physician.location.state}</p>
      )}
    </div>
  )
}
```

### **SEO & Sitemap Generation**
```tsx
// pages/sitemap.xml.tsx
import { GetServerSideProps } from 'next'

export default function Sitemap() {
  return null
}

export const getServerSideProps: GetServerSideProps = async ({ res }) => {
  const sitemapResponse = await fetch(`${process.env.WORDPRESS_URL}/wp-json/fad/v1/sitemap`)
  const physicians = await sitemapResponse.json()
  
  const sitemap = `<?xml version="1.0" encoding="UTF-8"?>
    <urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
      ${physicians.map(physician => `
        <url>
          <loc>${process.env.NEXT_PUBLIC_SITE_URL}${physician.url}</loc>
          <lastmod>${physician.lastmod}</lastmod>
          <changefreq>${physician.changefreq}</changefreq>
          <priority>${physician.priority}</priority>
        </url>
      `).join('')}
    </urlset>`

  res.setHeader('Content-Type', 'text/xml')
  res.write(sitemap)
  res.end()
  
  return { props: {} }
}
```

### **WP Engine Configuration**
```javascript
// faust.config.js
import { setConfig } from '@faustwp/core'

export default setConfig({
  wpUrl: process.env.WORDPRESS_URL,
  apiClientSecret: process.env.WP_SECRET_KEY,
  
  // Enable ISR for physician pages
  experimentalToolbar: true,
  
  // Custom rewrite rules for physician URLs
  rewrites: async () => {
    return [
      {
        source: '/physicians/:slug',
        destination: '/physicians/[slug]'
      }
    ]
  }
})
```

### **Environment Variables**
```bash
# .env.local
WORDPRESS_URL=https://your-wp-engine-site.wpengine.com
WP_SECRET_KEY=your-secret-key
NEXT_PUBLIC_SITE_URL=https://your-frontend.com
```

## 🔧 **WordPress Plugin Changes Summary**

1. **✅ Headless API** - New REST endpoints optimized for frontend consumption
2. **✅ Enhanced GraphQL** - Filtering, pagination, and search capabilities  
3. **✅ CORS Support** - Headers for cross-origin requests
4. **✅ SEO Data** - Sitemap generation for physician pages
5. **✅ Conditional Loading** - Disables template system in headless mode
6. **✅ Optimized Data Format** - camelCase fields for JavaScript consumption

The plugin now works seamlessly with both traditional WordPress and headless setups!