# MarineSync: Seamless Boat Listing Management for WordPress

MarineSync is a WordPress plugin that automates boat listing synchronization and management, enabling marine businesses to effortlessly import, export, and maintain their boat inventory with support for multiple data providers.

The plugin provides a comprehensive solution for marine businesses to manage their boat listings through a user-friendly WordPress interface. It features automated XML feed synchronization, custom fields for detailed boat specifications, media management for boat images, and flexible import/export capabilities. Built with Advanced Custom Fields (ACF) integration, it offers extensive customization options for boat details including dimensions, engine specifications, and equipment lists.

## Repository Structure
```
wp-marinesync/
├── admin/                 # Admin interface components and settings pages
├── assets/               # Frontend and admin assets (CSS, JavaScript)
├── includes/            # Core plugin functionality
│   ├── ACF/            # Advanced Custom Fields integration and field definitions
│   ├── Importer/       # CSV and XML import functionality
│   └── PostType/       # Custom post type and taxonomy definitions
├── languages/          # Internationalization files
├── product-updater.php # XML feed synchronization logic
└── wp-marinesync.php   # Plugin bootstrap and initialization
```

## Usage Instructions
### Prerequisites
- WordPress 5.0 or higher
- PHP 7.0 or higher
- Advanced Custom Fields PRO plugin installed and activated
- Write permissions on the WordPress uploads directory
- WordPress REST API enabled

### Installation

1. **Via WordPress Admin Panel:**
```bash
1. Go to Plugins > Add New > Upload Plugin
2. Upload the wp-marinesync.zip file
3. Click "Install Now"
4. Activate the plugin
```

2. **Manual Installation:**
```bash
1. Download the plugin zip file
2. Extract to /wp-content/plugins/
3. Rename the folder to 'wp-marinesync' if necessary
4. Activate via WordPress plugins page
```

### Quick Start
1. Navigate to MarineSync in the WordPress admin menu
2. Configure your feed settings:
   ```
   - Enter your XML feed URL
   - Set update frequency
   - Configure import/export preferences
   ```
3. Click "Run Feed" to perform initial import

### More Detailed Examples

**Importing Boats via CSV:**
```php
// Example CSV structure
title,featured_image,boat_media,content,boat_ref
"Example Boat","https://example.com/image.jpg","https://example.com/gallery1.jpg,https://example.com/gallery2.jpg","Description","REF001"
```

**Using Shortcodes:**
```php
// Display boat reference
[ms_field field="boat_ref"]

// Display boat specifications
[ms_field field="length"]
[ms_field field="beam"]
```

### Troubleshooting

**Feed Not Updating**
- Error: "Feed update failed"
  1. Check XML feed URL accessibility
  2. Verify WordPress cron is functioning
  3. Check error logs at wp-content/debug.log
  4. Ensure proper permissions on uploads directory

**Image Import Issues**
- Error: "Failed to import images"
  1. Verify PHP memory limit is sufficient
  2. Check image URLs are accessible
  3. Confirm WordPress upload directory permissions
  4. Review server error logs for timeout issues

**ACF Fields Not Showing**
1. Verify ACF Pro is activated
2. Reset field configurations
3. Check for conflicts with other plugins

## Data Flow
MarineSync processes boat listings through a structured pipeline, from XML/CSV import through to WordPress post creation with associated media and metadata.

```ascii
XML/CSV Feed → Import Handler → Media Processing → Post Creation → Custom Fields
     ↑                                                                  ↓
     └──────────────── Export Handler ←─── Data Validation ←───────────┘
```

Key Component Interactions:
1. Import Handler validates and normalizes incoming data
2. Media Processor downloads and attaches images
3. Post Creator generates WordPress posts with proper taxonomies
4. Custom Fields Manager handles ACF field population
5. Export Handler formats data for external systems
6. Validation Layer ensures data integrity
7. Cron Manager handles scheduled updates