# Railway Production Environment Variables Checklist

Set these in your Railway service → Variables tab:

## Critical (Security)
- [ ] APP_ENV=production
- [ ] APP_DEBUG=false
- [ ] APP_URL=https://medfind-production.up.railway.app
- [ ] SESSION_SECURE_COOKIE=true
- [ ] REVERB_ALLOWED_ORIGINS=https://medfind-production.up.railway.app

## Mail (already set, verify these are correct)
- [ ] MAIL_MAILER=smtp
- [ ] MAIL_HOST=smtp.gmail.com
- [ ] MAIL_PORT=587
- [ ] MAIL_ENCRYPTION=tls
- [ ] MAIL_USERNAME=your-gmail@gmail.com
- [ ] MAIL_PASSWORD=your-app-password (16-char Gmail App Password, no spaces)
- [ ] MAIL_FROM_ADDRESS=your-gmail@gmail.com
- [ ] MAIL_FROM_NAME=MedFind

## After setting all variables
1. Redeploy the Railway service so changes take effect.
2. Verify login still works at https://medfind-production.up.railway.app/login
3. Verify APP_DEBUG=false by triggering a 404 — should show a generic error page, NOT a stack trace.

## Cloudflare R2 Storage (Pharmacy Logos)
- [ ] FILESYSTEM_LOGO_DISK=r2
- [ ] CLOUDFLARE_R2_ACCESS_KEY_ID=`<your R2 access key id>`
- [ ] CLOUDFLARE_R2_SECRET_ACCESS_KEY=`<your R2 secret access key>`
- [ ] CLOUDFLARE_R2_BUCKET=`<your bucket name>`
- [ ] CLOUDFLARE_R2_ENDPOINT=`https://<account_id>.r2.cloudflarestorage.com`
- [ ] CLOUDFLARE_R2_PUBLIC_URL=`https://pub-<token>.r2.dev`

> **Note:** `FILESYSTEM_LOGO_DISK=public` is the local default (uses the storage symlink). Set it to `r2` on Railway to activate Cloudflare R2. The `local` and `prescriptions` disks are unaffected — pharmacy requirements and prescriptions stay on the server's private disk.
