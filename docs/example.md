# 💡 Examples

## Current Implementation

**Note**: Swift CSV is primarily designed as a WordPress admin interface plugin. The examples below show the internal structure, but actual usage is through the admin interface.

## Admin Interface Usage

### CSV Export via Admin Interface

1. Navigate to **Admin Dashboard → Swift CSV → Export**
2. Select post type from dropdown
3. Set number of posts (large datasets will be processed in batches)
4. Click **Export CSV** to download file

### CSV Import via Admin Interface

1. Navigate to **Admin Dashboard → Swift CSV → Import**
2. Select target post type
3. Choose CSV file (UTF-8, Shift-JIS, EUC-JP, JIS auto-detected)
4. Configure import options
5. Click **Import CSV**

For large files (>1000 rows or >10MB), automatic batch processing with progress tracking will be used.

## CSV Format Examples

### Basic CSV Structure

```csv
post_title,post_content,post_excerpt,post_status
"Sample Post","This is the content","Sample excerpt",publish
"Another Post","More content here","",draft
```

### Custom Fields Example

```csv
post_title,post_content,cf_Name,cf_Email,cf_Phone,cf_Tags
"John Doe","Content about John","John","john@example.com","555-1234","developer|wordpress|php"
"Jane Smith","Content about Jane","Jane","jane@example.com","555-5678","designer|ui|ux"
```

**Note**:

- Custom fields must use `cf_` prefix in the CSV header (e.g., `cf_Name`, `cf_Email`, `cf_Phone`).
- **Multi-value support**: Use `|` (pipe) to separate multiple values (e.g., `cf_Tags` with `developer|wordpress|php`).

### Hierarchical Taxonomies Example

```csv
post_title,post_content,category,post_tag
"Tech Post","About technology","Technology > WordPress > Plugins","tech|wordpress|php"
"Design Post","About design","Design > UI > Web","design|ui|ux"
```

**Note**: Use `>` to separate hierarchy levels in taxonomies (e.g., `Technology > WordPress > Plugins` creates a three-level hierarchy). Multiple taxonomy terms are pipe-separated for consistency with custom fields.

**Auto-creation**: Missing terms are automatically created with unique IDs and URL-friendly slugs (e.g., "Technology" → slug: "technology").

## Real-World Use Cases

### Blog Migration from Another Platform

```csv
post_title,post_content,post_status,post_date,category,post_tag,cf_original_id,cf_author
"Welcome to Our Blog","<p>This is our first post...</p>","publish","2024-01-15 10:00:00","Announcements","welcome|blog","123","Admin"
"Product Launch","<p>Exciting news about our new product...</p>","publish","2024-01-16 14:30:00","Products > New","product|launch","124","Marketing"
```

**Key Features**:

- **Date Preservation**: `post_date` maintains original publishing schedule
- **Category Hierarchy**: `Products > New` creates nested categories
- **Original ID**: `cf_original_id` preserves source system reference
- **Author Tracking**: `cf_author` tracks original content creator

### E-commerce Product Catalog

```csv
post_title,post_content,post_status,cf_price,cf_sku,cf_stock,cf_color,cf_size,cf_tags,category
"Classic T-Shirt","Premium cotton t-shirt with comfortable fit","publish",19.99,"TS-001",50,"black|white|gray","S|M|L","apparel|casual|cotton","Clothing > T-Shirts"
"Denim Jeans","Classic fit denim jeans","publish",49.99,"DJ-001",25,"blue","30|32|34","apparel|denim|casual","Clothing > Pants > Jeans"
```

**Key Features**:

- **Multi-value Attributes**: `cf_color` and `cf_size` use pipe separation
- **Product Data**: `cf_price`, `cf_sku`, `cf_stock` for e-commerce integration
- **Category Structure**: Hierarchical product categories
- **Tag System**: `cf_tags` for flexible product tagging

### Real Estate Listings

```csv
post_title,post_content,post_status,cf_price,cf_address,cf_bedrooms,cf_bathrooms,cf_square_feet,cf_property_type,cf_features,category
"Downtown Apartment","Modern apartment in city center","publish",250000,"123 Main St, Downtown",2,1,850,"apartment","parking|gym|balcony","Properties > Residential > Apartments"
"Suburban House","Spacious family home with garden","publish",450000,"456 Oak Ave, Suburbs",4,3,2200,"house","garage|garden|pool","Properties > Residential > Houses"
```

**Key Features**:

- **Real Estate Data**: Price, address, bedrooms, bathrooms, square footage
- **Property Types**: `cf_property_type` for classification
- **Amenities**: `cf_features` with pipe-separated amenities
- **Location Categories**: Hierarchical property categorization

### Event Calendar Import

```csv
post_title,post_content,post_status,cf_event_date,cf_event_time,cf_event_location,cf_event_price,cf_event_organizer,cf_event_category,category
"Tech Conference 2024","Annual technology conference","publish","2024-03-15","09:00","Convention Center",299,"Tech Association","conference|technology","Events > Conferences"
"Local Meetup","Monthly WordPress meetup","publish","2024-02-20","18:30","Community Center",0,"WordPress Group","meetup|wordpress|free","Events > Meetups"
```

**Key Features**:

- **Event Management**: Date, time, location, price information
- **Organizer Tracking**: `cf_event_organizer` for event hosts
- **Event Categories**: Flexible event categorization
- **Free/Paid Events**: `cf_event_price` supports free events (0)

### Team Member Directory

```csv
post_title,post_content,post_status,cf_name,cf_title,cf_email,cf_phone,cf_department,cf_skills,cf_bio,category
"John Smith","Senior developer with 10+ years experience","publish","John Smith","Senior Developer","john@company.com","555-0101","Engineering","php|wordpress|javascript|mysql","John is a senior developer...","Team > Engineering"
"Jane Doe","UX designer focused on user experience","publish","Jane Doe","UX Designer","jane@company.com","555-0102","Design","ux|ui|figma|prototyping","Jane specializes in user experience...","Team > Design"
```

**Key Features**:

- **Professional Profiles**: Name, title, contact information
- **Department Structure**: `cf_department` for team organization
- **Skill Tracking**: `cf_skills` with pipe-separated competencies
- **Biographical Information**: `cf_bio` for detailed descriptions

## Advanced Examples

### Multi-language Content

```csv
post_title,post_content,post_status,cf_language,cf_translation_id,cf_original_title,category
"Welcome","<p>Welcome to our site</p>","publish","en","1","","Announcements"
"ようこそ","<p>私たちのサイトへようこそ</p>","publish","ja","1","Welcome","Announcements"
"Bienvenue","<p>Bienvenue sur notre site</p>","publish","fr","1","Welcome","Announcements"
```

**Key Features**:

- **Language Identification**: `cf_language` for content language
- **Translation Linking**: `cf_translation_id` connects related content
- **Original Reference**: `cf_original_title` tracks source content

### Content with Media References

```csv
post_title,post_content,post_status,cf_featured_image,cf_gallery_images,cf_video_url,cf_attachments,category
"Product Showcase","<p>Check out our new product</p>","publish","/uploads/product-hero.jpg","/uploads/gallery1.jpg|/uploads/gallery2.jpg","https://youtube.com/watch?v=123","/uploads/specs.pdf|/uploads/manual.pdf","Products"
```

**Key Features**:

- **Media References**: Image paths and URLs
- **Gallery Support**: Multiple images with pipe separation
- **Video Integration**: YouTube and other video platforms
- **Document Attachments**: PDFs and other downloadable files

## Best Practices

### File Preparation

1. **Use UTF-8 Encoding**: Save all CSV files as UTF-8
2. **Include Headers**: Always include descriptive header row
3. **Test Small Files**: Start with 5-10 rows for testing
4. **Backup Data**: Always backup before large imports

### Data Quality

1. **Consistent Formatting**: Use consistent date and number formats
2. **Escape Special Characters**: Use quotes for fields with commas
3. **Validate Required Fields**: Ensure `post_title` is never empty
4. **Check Hierarchies**: Verify category/taxonomy structures

### Performance Optimization

1. **Batch Large Files**: Split files >10MB into smaller chunks
2. **Monitor Memory**: Watch server memory during large imports
3. **Use Progress Tracking**: Let automatic batch processing handle large datasets
4. **Test Server Limits**: Know your PHP memory and time limits

## Troubleshooting Examples

### Common Issues and Solutions

**Problem**: Import fails with "Invalid CSV format"

```csv
# WRONG - Missing quotes around content with commas
post_title,post_content
"Post Title","Content with, comma here"

# CORRECT - Properly quoted
post_title,post_content
"Post Title","Content with, comma here"
```

**Problem**: Custom fields not importing

```csv
# WRONG - Missing cf_ prefix
post_title,name,email
"Post Title","John","john@example.com"

# CORRECT - With cf_ prefix
post_title,cf_name,cf_email
"Post Title","John","john@example.com"
```

**Problem**: Hierarchical categories not working

```csv
# WRONG - Wrong separator
post_title,category
"Post Title","Technology/WordPress/Plugins"

# CORRECT - Using > separator
post_title,category
"Post Title","Technology > WordPress > Plugins"
```

## Internal API Structure

**For Developers**: The following classes handle the core functionality:

- `Swift_CSV_Importer`: Handles CSV import processing
- `Swift_CSV_Exporter`: Handles CSV export generation
- `Swift_CSV_Batch`: Manages batch processing for large files
- `Swift_CSV_Admin`: Provides admin interface

**Note**: Direct API usage is not currently documented as the plugin is designed for admin interface usage.
