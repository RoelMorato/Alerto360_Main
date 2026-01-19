# Alerto360 Database Setup Guide

## 🚀 Quick Setup with Supabase (Free)

### Step 1: Create Supabase Account
1. Go to https://supabase.com
2. Sign up with GitHub/Google
3. Create new project: "alerto360-db"
4. Wait for setup (2-3 minutes)

### Step 2: Get Database URL
1. Go to Project Settings → Database
2. Copy the "Connection string" (URI format)
3. It looks like: `postgresql://postgres:[password]@[host]:5432/postgres`

### Step 3: Import Database Schema
1. Go to SQL Editor in Supabase
2. Copy and paste your `alerto360.sql` file content
3. Click "Run" to create all tables

### Step 4: Add to Vercel Environment Variables
1. Go to Vercel Dashboard → Your Project
2. Settings → Environment Variables
3. Add new variable:
   - **Name:** `DATABASE_URL`
   - **Value:** Your Supabase connection string
4. Redeploy your project

## 🔧 Alternative: Other Free Databases

### Option 1: Neon (PostgreSQL)
- Go to https://neon.tech
- Free tier: 512MB storage
- Same setup process as Supabase

### Option 2: PlanetScale (MySQL)
- Go to https://planetscale.com
- Free tier: 1GB storage
- Need to convert SQL schema from MySQL to PostgreSQL

### Option 3: Railway (PostgreSQL)
- Go to https://railway.app
- Free tier with usage limits
- Easy GitHub integration

## 📊 Database Schema (PostgreSQL)

Your `alerto360.sql` needs to be converted from MySQL to PostgreSQL:

```sql
-- Users table
CREATE TABLE users (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(50) DEFAULT 'citizen',
    email_verified BOOLEAN DEFAULT FALSE,
    verification_required BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert default admin user
INSERT INTO users (name, email, password, role, email_verified, verification_required) 
VALUES (
    'Admin User', 
    'admin@alerto360.com', 
    '$2b$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 
    'admin', 
    TRUE, 
    FALSE
);
```

## ✅ Testing

After setup, test your API:
- Login: `https://alerto360-main.vercel.app/api/login`
- Register: `https://alerto360-main.vercel.app/api/register`

## 🔒 Security Notes

- Database URL contains sensitive credentials
- Never commit DATABASE_URL to GitHub
- Use environment variables only
- Enable SSL connections (default in Supabase)

## 🆘 Troubleshooting

**Connection Error:**
- Check DATABASE_URL format
- Verify Supabase project is active
- Ensure SSL is enabled

**Query Errors:**
- Convert MySQL syntax to PostgreSQL
- Check table names and column types
- Verify user permissions

**Deployment Issues:**
- Redeploy after adding environment variables
- Check Vercel function logs
- Verify package.json dependencies