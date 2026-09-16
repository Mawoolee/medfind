# Google Maps Routing Integration Setup Instructions

## Step 1: Get Google Maps API Key

1. Go to https://console.cloud.google.com/
2. Create a new project or select existing project
3. Enable these APIs:
   - Directions API
   - Maps JavaScript API (if using Google Maps tiles)
4. Go to "Credentials" → "Create Credentials" → "API Key"
5. Copy your API key

## Step 2: Restrict API Key (IMPORTANT for security)

1. Click on your API key
2. Under "API restrictions" → Select "Restrict key"
3. Check only:
   - Directions API
   - Maps JavaScript API
4. Under "Website restrictions" → Add your domains:
   - localhost (for development)
   - Your production domain

## Step 3: Add API Key to .env

Add this line to your .env file:
GOOGLE_MAPS_API_KEY=AIza...your-actual-key...

## Step 4: Costs & Free Tier

Google Maps Directions API pricing:
- FREE: First $200 USD/month in API calls
- After free tier: $5 USD per 1,000 requests
- Most small apps stay within free tier

Example: 4,000 direction requests/month = FREE

## Implementation Notes

The code will:
- Use Google Directions API for routing
- Still use Leaflet for map display
- Show turn-by-turn directions
- Display route on map
- More accurate for Philippines

