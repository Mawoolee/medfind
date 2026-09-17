# Google Maps API Setup Guide

## Overview

This document provides instructions for setting up and configuring the Google Maps API key for the MedFind pharmacy locator application.

## Prerequisites

- Google Cloud Platform account
- Project created in Google Cloud Console
- Billing enabled (Google Maps offers $200 free monthly credit)

## API Key Setup

### 1. Create API Key

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Navigate to **APIs & Services** → **Credentials**
3. Click **+ CREATE CREDENTIALS** → **API key**
4. Copy the generated API key
5. Add it to your `.env` file:
   ```
   GOOGLE_MAPS_API_KEY=your_api_key_here
   ```

### 2. Enable Required APIs

Navigate to **APIs & Services** → **Library** and enable the following APIs:

- ✅ **Maps JavaScript API** - Core map display functionality
- ✅ **Places API** - Address autocomplete and place search
- ✅ **Directions API** - Turn-by-turn routing from user to pharmacy
- ✅ **Geocoding API** - Convert addresses to coordinates and vice versa

### 3. Configure API Restrictions

#### Application Restrictions (HTTP Referrers)

1. Go to **APIs & Services** → **Credentials**
2. Click on your API key to edit
3. Under **Application restrictions**, select **HTTP referrers (web sites)**
4. Add your domain referrers:
   ```
   yourdomain.com/*
   *.yourdomain.com/*
   localhost:8000/*  (for local development)
   127.0.0.1:8000/*  (for local development)
   ```

#### API Restrictions

1. Under **API restrictions**, select **Restrict key**
2. Enable only the required APIs:
   - Maps JavaScript API
   - Places API
   - Directions API
   - Geocoding API

### 4. Set Usage Limits (Optional but Recommended)

1. Go to **APIs & Services** → **Dashboard**
2. Click on each enabled API
3. Navigate to **Quotas**
4. Set daily request limits to prevent unexpected charges:
   - Maps JavaScript API: 25,000 loads/day (adjust based on traffic)
   - Directions API: 10,000 requests/day
   - Geocoding API: 5,000 requests/day
   - Places API: 10,000 requests/day

### 5. Configure Billing Alerts

1. Go to **Billing** → **Budgets & alerts**
2. Click **CREATE BUDGET**
3. Set alert thresholds (e.g., 50%, 75%, 90%, 100% of $200 free credit)
4. Add email recipients for notifications

## Environment Configuration

### Local Development (.env)

```env
GOOGLE_MAPS_API_KEY=AIzaSyB1smct-CgBTsUbAcQhTtLM-br7nRwndpU
```

### Production (.env or Environment Variables)

For production deployments (Railway, AWS, etc.), set the API key as an environment variable:

```bash
GOOGLE_MAPS_API_KEY=your_production_api_key_here
```

⚠️ **Security Best Practices**:
- Never commit API keys to version control
- Use separate API keys for development and production
- Enable API restrictions to prevent unauthorized use
- Monitor usage in Google Cloud Console regularly

## Testing the API Key

### Quick Test (Browser Console)

1. Open your application in a browser
2. Open Developer Tools (F12)
3. Navigate to the Console tab
4. Paste and run:
   ```javascript
   fetch('https://maps.googleapis.com/maps/api/js?key=YOUR_API_KEY')
     .then(response => response.ok ? console.log('✅ API key valid') : console.error('❌ API key invalid'))
   ```

### Test Page

Use the provided test page at `/test-google-maps.html` to verify:
- Map loads correctly
- API key has required permissions
- All necessary APIs are enabled

## Troubleshooting

### Error: "This page can't load Google Maps correctly"

**Cause**: Invalid API key or API not enabled

**Solution**:
1. Verify API key is correct in `.env` file
2. Check that Maps JavaScript API is enabled
3. Clear browser cache and reload

### Error: "RefererNotAllowedMapError"

**Cause**: HTTP referrer restrictions blocking your domain

**Solution**:
1. Go to Google Cloud Console → Credentials
2. Edit your API key
3. Add your domain to allowed referrers list
4. Include `localhost:8000/*` for local development

### Error: "ApiNotActivatedMapError"

**Cause**: Required API not enabled

**Solution**:
1. Navigate to APIs & Services → Library
2. Enable all required APIs listed above
3. Wait 1-2 minutes for changes to propagate

### Error: Quota exceeded

**Cause**: Daily request limit reached

**Solution**:
1. Check usage in Google Cloud Console → APIs & Services → Dashboard
2. Increase quotas or upgrade billing plan
3. Optimize application to reduce API calls

## Cost Estimation

Google Maps provides **$200 free credit per month**, which covers:

- **Maps JavaScript API**: ~28,000 free map loads/month ($7 per 1,000 loads after)
- **Directions API**: ~40,000 free requests/month ($5 per 1,000 requests after)
- **Geocoding API**: ~40,000 free requests/month ($5 per 1,000 requests after)
- **Places API**: ~17,000 free requests/month ($17 per 1,000 requests after)

### Estimated Usage (Small-Medium Pharmacy Locator)

| API | Monthly Requests | Cost |
|-----|------------------|------|
| Maps JavaScript API | 10,000 loads | **Free** |
| Directions API | 3,000 requests | **Free** |
| Geocoding API | 1,000 requests | **Free** |
| Places API | 2,000 requests | **Free** |
| **Total** | | **$0** |

Most small to medium applications will stay within the free tier.

## Support and Resources

- [Google Maps JavaScript API Documentation](https://developers.google.com/maps/documentation/javascript)
- [Google Maps Platform Pricing](https://mapsplatform.google.com/pricing/)
- [Google Cloud Console](https://console.cloud.google.com/)
- [API Key Best Practices](https://developers.google.com/maps/api-security-best-practices)

## Checklist

Before deploying to production, verify:

- [ ] API key added to `.env` file
- [ ] All 4 required APIs enabled (Maps, Places, Directions, Geocoding)
- [ ] HTTP referrer restrictions configured
- [ ] API restrictions configured (only required APIs enabled)
- [ ] Billing alerts set up
- [ ] Test page successfully loads map
- [ ] No console errors when loading map
- [ ] Separate API keys for development and production
