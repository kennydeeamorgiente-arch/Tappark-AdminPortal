# Vercel Deployment

This project is a CodeIgniter 4 PHP app. Vercel needs the PHP community runtime configured in `vercel.json`.

## Vercel Project Settings

Use these values when importing the project:

```text
Application Preset: Other
Root Directory: ./
Build Command: leave empty
Output Directory: leave empty
Install Command: leave empty
```

The PHP runtime will install Composer dependencies during the Vercel build.

## Environment Variables

Add these in Vercel under Project Settings > Environment Variables:

```env
CI_ENVIRONMENT=production
APP_BASE_URL=https://your-project.vercel.app/

DB_HOST=your-database-host
DB_DATABASE=your-database-name
DB_USERNAME=your-database-user
DB_PASSWORD=your-database-password
DB_PORT=3306
DB_DRIVER=MySQLi

ENCRYPTION_KEY=generate-a-long-random-secret
GEOAPIFY_API_KEY=your-geoapify-key
```

If your MIS/Foundation API requires tokens, also add:

```env
FOUNDATION_API_BASE_URL=https://mis.foundationu.com/api/tappark
FOUNDATION_REFRESH_URL=https://mis.foundationu.com/api/token/refresh
FOUNDATION_ACCESS_TOKEN=your-access-token
FOUNDATION_REFRESH_TOKEN=your-refresh-token
```

Generate an encryption key locally with:

```bash
php -r "echo bin2hex(random_bytes(32));"
```

## Important Limitation

Vercel is serverless, so local file writes are temporary. Sessions/cache are moved to `/tmp` for Vercel, but uploaded profile pictures and other files written inside `public/uploads` will not be permanent. For production uploads, use external storage like Vercel Blob, S3, or Cloudinary, or deploy this app to a traditional PHP host.
