# CI/CD Documentation - Rakez ERP
## دليل شامل للتكامل والنشر المستمر

---

## 📋 Table of Contents

1. [What is CI/CD?](#what-is-cicd)
2. [Our CI/CD Architecture](#our-cicd-architecture)
3. [Prerequisites](#prerequisites)
4. [Step-by-Step Setup Guide](#step-by-step-setup-guide)
5. [GitHub Actions Workflow Explained](#github-actions-workflow-explained)
6. [Server Configuration](#server-configuration)
7. [GitHub Secrets Configuration](#github-secrets-configuration)
8. [Deployment Flow](#deployment-flow)
9. [Troubleshooting](#troubleshooting)
10. [Best Practices](#best-practices)

---

## What is CI/CD?

### CI - Continuous Integration (التكامل المستمر)
Automatically build and test code changes when developers push to the repository.

### CD - Continuous Deployment (النشر المستمر)
Automatically deploy code to production after successful builds.

### Benefits:
- ✅ **Faster Releases**: Deploy in minutes, not hours
- ✅ **Fewer Bugs**: Automated testing catches issues early
- ✅ **Consistency**: Same deployment process every time
- ✅ **Rollback Capability**: Easy to revert if issues occur
- ✅ **Team Productivity**: No manual deployment steps

---

## Our CI/CD Architecture

```
┌─────────────────┐     ┌─────────────────┐     ┌─────────────────┐
│   Developer     │────▶│    GitHub       │────▶│  Production     │
│   Push Code     │     │    Actions      │     │  Server         │
└─────────────────┘     └─────────────────┘     └─────────────────┘
        │                       │                       │
        │                       ▼                       │
        │               ┌───────────────┐               │
        │               │ Build & Test  │               │
        │               │ - PHP Setup   │               │
        │               │ - Composer    │               │
        │               │ - NPM Build   │               │
        │               └───────────────┘               │
        │                       │                       │
        │                       ▼                       │
        │               ┌───────────────┐               │
        │               │ Deploy via    │──────────────▶│
        │               │ SSH           │               │
        │               └───────────────┘               │
        │                                               │
        └───────────────────────────────────────────────┘
```

---

## Prerequisites

### 1. GitHub Repository
- Repository hosted on GitHub
- Access to repository settings

### 2. Production Server Requirements
- Ubuntu/Linux server (we use Ubuntu)
- PHP 8.2+
- Composer
- Node.js 20+
- NPM
- MySQL/MariaDB
- Git installed
- SSH access enabled

### 3. Required Access
- SSH private key for server
- GitHub repository admin access

---

## Step-by-Step Setup Guide

### Step 1: Create Workflow Directory

Create the following directory structure in your project:

```
your-project/
├── .github/
│   └── workflows/
│       └── deploy.yml
```

### Step 2: Create the Workflow File

Create `.github/workflows/deploy.yml`:

```yaml
# CI/CD Pipeline for Rakez ERP
name: Deploy to Production

on:
  push:
    branches:
      - main  # Triggers on push to main branch

jobs:
  deploy:
    name: Deploy to Server
    runs-on: ubuntu-latest

    steps:
      - name: Checkout code
        uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
          extensions: mbstring, xml, ctype, json, bcmath, curl, zip, pdo, mysql
          coverage: none

      - name: Install Composer dependencies
        run: composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

      - name: Setup Node.js
        uses: actions/setup-node@v4
        with:
          node-version: '20'
          cache: 'npm'

      - name: Install NPM dependencies
        run: npm ci

      - name: Build assets
        run: npm run build

      - name: Deploy to Server via SSH
        uses: appleboy/ssh-action@v1.0.3
        with:
          host: ${{ secrets.SSH_HOST }}
          username: ${{ secrets.SSH_USERNAME }}
          key: ${{ secrets.SSH_PRIVATE_KEY }}
          port: ${{ secrets.SSH_PORT }}
          script: |
            cd /var/www/Rakez/rakez-erp

            # Force update to match GitHub
            git fetch origin
            git reset --hard origin/main

            # Install dependencies
            composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist
            npm ci
            npm run build

            # Laravel optimizations
            php artisan config:cache
            php artisan route:cache
            php artisan view:cache
            php artisan event:cache

            # Run migrations
            php artisan migrate --force

            # Restart queue workers
            php artisan queue:restart

            # Optimize
            php artisan optimize

            echo "Deployment completed successfully!"
```

### Step 3: Generate SSH Key Pair

On your **local machine**, generate an SSH key pair:

```bash
# Generate SSH key (no passphrase for automation)
ssh-keygen -t ed25519 -C "github-actions-deploy" -f ~/.ssh/github_deploy_key

# This creates:
# - ~/.ssh/github_deploy_key (private key - KEEP SECRET)
# - ~/.ssh/github_deploy_key.pub (public key)
```

### Step 4: Configure Server SSH

On your **production server**:

```bash
# Add the public key to authorized_keys
cat ~/.ssh/github_deploy_key.pub >> ~/.ssh/authorized_keys

# Set correct permissions
chmod 700 ~/.ssh
chmod 600 ~/.ssh/authorized_keys
```

### Step 5: Configure GitHub Secrets

Go to your GitHub repository:

1. Navigate to **Settings** → **Secrets and variables** → **Actions**
2. Click **New repository secret**
3. Add the following secrets:

| Secret Name | Value | Description |
|-------------|-------|-------------|
| `SSH_HOST` | `your-server-ip` | Server IP address (e.g., 192.168.1.100) |
| `SSH_USERNAME` | `your-username` | SSH username (e.g., root, ubuntu, deploy) |
| `SSH_PRIVATE_KEY` | `-----BEGIN OPENSSH PRIVATE KEY-----...` | Contents of private key file |
| `SSH_PORT` | `22` | SSH port (default: 22) |

### Step 6: Initial Server Setup

On your **production server**, set up the project:

```bash
# Create project directory
sudo mkdir -p /var/www/Rakez
cd /var/www/Rakez

# Clone the repository
git clone https://github.com/your-username/rakez-erp.git
cd rakez-erp

# Set permissions
sudo chown -R www-data:www-data /var/www/Rakez
sudo chmod -R 755 /var/www/Rakez
sudo chmod -R 775 /var/www/Rakez/rakez-erp/storage
sudo chmod -R 775 /var/www/Rakez/rakez-erp/bootstrap/cache

# Install dependencies
composer install --no-dev --optimize-autoloader
npm ci
npm run build

# Setup environment
cp .env.example .env
php artisan key:generate

# Configure .env with database credentials
nano .env

# Run migrations
php artisan migrate

# Optimize Laravel
php artisan optimize
```

### Step 7: Test the Pipeline

```bash
# Make a small change
echo "// CI/CD Test" >> routes/api.php

# Commit and push
git add .
git commit -m "Test CI/CD pipeline"
git push origin main
```

Check GitHub Actions tab to see the deployment running!

---

## GitHub Actions Workflow Explained

### Trigger Configuration

```yaml
on:
  push:
    branches:
      - main
```
**Explanation**: The workflow runs automatically when code is pushed to the `main` branch.

### Job Configuration

```yaml
jobs:
  deploy:
    name: Deploy to Server
    runs-on: ubuntu-latest
```
**Explanation**: Creates a job named "deploy" that runs on GitHub's Ubuntu runner.

### Step 1: Checkout Code

```yaml
- name: Checkout code
  uses: actions/checkout@v4
```
**Explanation**: Downloads your repository code to the runner.

### Step 2: Setup PHP

```yaml
- name: Setup PHP
  uses: shivammathur/setup-php@v2
  with:
    php-version: '8.2'
    extensions: mbstring, xml, ctype, json, bcmath, curl, zip, pdo, mysql
```
**Explanation**: Installs PHP 8.2 with required extensions for Laravel.

### Step 3: Install Composer Dependencies

```yaml
- name: Install Composer dependencies
  run: composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist
```
**Explanation**: 
- `--no-dev`: Skip development dependencies
- `--optimize-autoloader`: Generate optimized autoload files
- `--no-interaction`: Don't ask questions
- `--prefer-dist`: Download from dist (faster)

### Step 4: Setup Node.js

```yaml
- name: Setup Node.js
  uses: actions/setup-node@v4
  with:
    node-version: '20'
    cache: 'npm'
```
**Explanation**: Installs Node.js 20 and caches npm packages for faster builds.

### Step 5: Build Assets

```yaml
- name: Install NPM dependencies
  run: npm ci

- name: Build assets
  run: npm run build
```
**Explanation**: 
- `npm ci`: Clean install (faster, more reliable than `npm install`)
- `npm run build`: Build production assets (Vite/Mix)

### Step 6: Deploy via SSH

```yaml
- name: Deploy to Server via SSH
  uses: appleboy/ssh-action@v1.0.3
  with:
    host: ${{ secrets.SSH_HOST }}
    username: ${{ secrets.SSH_USERNAME }}
    key: ${{ secrets.SSH_PRIVATE_KEY }}
    port: ${{ secrets.SSH_PORT }}
    script: |
      # Deployment commands here
```
**Explanation**: Connects to your server via SSH and runs deployment commands.

---

## Server Configuration

### Nginx Configuration Example

```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /var/www/Rakez/rakez-erp/public;

    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

### Required Server Packages

```bash
# Update system
sudo apt update && sudo apt upgrade -y

# Install PHP 8.2
sudo add-apt-repository ppa:ondrej/php -y
sudo apt install php8.2 php8.2-fpm php8.2-mysql php8.2-mbstring php8.2-xml php8.2-bcmath php8.2-curl php8.2-zip -y

# Install Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Install Node.js 20
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install nodejs -y

# Install Nginx
sudo apt install nginx -y

# Install MySQL
sudo apt install mysql-server -y
```

---

## GitHub Secrets Configuration

### How to Add Secrets

1. Go to your GitHub repository
2. Click **Settings** tab
3. Click **Secrets and variables** → **Actions**
4. Click **New repository secret**

### Required Secrets

| Secret | Example | Description |
|--------|---------|-------------|
| `SSH_HOST` | `192.168.1.100` or `example.com` | Server IP or domain |
| `SSH_USERNAME` | `deploy` | SSH login username |
| `SSH_PRIVATE_KEY` | `-----BEGIN OPENSSH PRIVATE KEY-----...` | Full private key content |
| `SSH_PORT` | `22` | SSH port number |

### Getting Private Key Content

```bash
# Display private key (copy entire output including BEGIN/END lines)
cat ~/.ssh/github_deploy_key
```

---

## Deployment Flow

```
1. Developer pushes to main branch
         │
         ▼
2. GitHub detects push, triggers workflow
         │
         ▼
3. GitHub Actions runner starts
         │
         ├── Checkout code from repository
         │
         ├── Setup PHP 8.2 environment
         │
         ├── Install Composer dependencies
         │
         ├── Setup Node.js 20
         │
         ├── Install NPM dependencies
         │
         ├── Build frontend assets
         │
         ▼
4. SSH connection to production server
         │
         ├── git fetch & reset to latest
         │
         ├── composer install
         │
         ├── npm ci & build
         │
         ├── Laravel cache optimizations
         │
         ├── Run database migrations
         │
         ├── Restart queue workers
         │
         ▼
5. Deployment complete! ✅
```

---

## Troubleshooting

### Common Issues

#### 1. SSH Connection Failed

**Error**: `ssh: connect to host xxx.xxx.xxx.xxx port 22: Connection refused`

**Solutions**:
```bash
# On server - Check SSH is running
sudo systemctl status ssh

# Start SSH if not running
sudo systemctl start ssh

# Check firewall
sudo ufw status
sudo ufw allow 22
```

#### 2. Permission Denied

**Error**: `Permission denied (publickey)`

**Solutions**:
```bash
# On server - Check authorized_keys
cat ~/.ssh/authorized_keys

# Verify permissions
chmod 700 ~/.ssh
chmod 600 ~/.ssh/authorized_keys

# Check SSH config allows key auth
sudo nano /etc/ssh/sshd_config
# Ensure: PubkeyAuthentication yes
```

#### 3. Composer Memory Error

**Error**: `Allowed memory size exhausted`

**Solution**:
```bash
# Run with increased memory
php -d memory_limit=-1 /usr/local/bin/composer install
```

#### 4. NPM Build Failed

**Error**: `npm ERR! code ELIFECYCLE`

**Solutions**:
```bash
# Clear npm cache
npm cache clean --force

# Remove node_modules and reinstall
rm -rf node_modules package-lock.json
npm install
```

#### 5. Migration Failed

**Error**: `SQLSTATE[HY000] [2002] Connection refused`

**Solution**:
```bash
# Check .env database configuration
nano .env

# Verify MySQL is running
sudo systemctl status mysql

# Test database connection
mysql -u your_user -p -h localhost your_database
```

### Viewing Deployment Logs

1. Go to GitHub repository
2. Click **Actions** tab
3. Click on the failed workflow run
4. Click on the job to see detailed logs

---

## Best Practices

### 1. Branch Protection

Enable branch protection for `main`:
- Go to **Settings** → **Branches**
- Add rule for `main`
- Enable "Require pull request reviews"

### 2. Environment Files

Never commit `.env` to repository. Use:
```bash
# Add to .gitignore
.env
.env.local
.env.production
```

### 3. Backup Before Deploy

Add backup step to workflow:
```yaml
script: |
  # Backup database before deploy
  mysqldump -u user -p database > backup_$(date +%Y%m%d_%H%M%S).sql
  
  # Continue with deployment...
```

### 4. Rollback Strategy

Keep previous releases:
```bash
# Create releases directory structure
/var/www/Rakez/
├── releases/
│   ├── 20260110_120000/
│   ├── 20260110_130000/
│   └── 20260110_140000/
├── current -> releases/20260110_140000  # Symlink
└── shared/
    ├── .env
    └── storage/
```

### 5. Notifications

Add Slack/Discord notifications:
```yaml
- name: Notify on Success
  if: success()
  run: |
    curl -X POST -H 'Content-type: application/json' \
    --data '{"text":"✅ Deployment successful!"}' \
    ${{ secrets.SLACK_WEBHOOK }}

- name: Notify on Failure
  if: failure()
  run: |
    curl -X POST -H 'Content-type: application/json' \
    --data '{"text":"❌ Deployment failed!"}' \
    ${{ secrets.SLACK_WEBHOOK }}
```

### 6. Health Check

Add post-deployment health check:
```yaml
- name: Health Check
  run: |
    response=$(curl -s -o /dev/null -w "%{http_code}" https://your-domain.com/api/health)
    if [ $response != "200" ]; then
      echo "Health check failed!"
      exit 1
    fi
```

---

## Quick Reference Commands

### Manual Deployment

```bash
# SSH to server
ssh user@your-server-ip

# Go to project
cd /var/www/Rakez/rakez-erp

# Pull latest changes
git pull origin main

# Install dependencies
composer install --no-dev --optimize-autoloader
npm ci && npm run build

# Clear caches
php artisan optimize:clear

# Run migrations
php artisan migrate --force

# Rebuild caches
php artisan optimize
```

### Useful Artisan Commands

```bash
# Clear all caches
php artisan optimize:clear

# Rebuild all caches
php artisan optimize

# Check routes
php artisan route:list

# Run specific migration
php artisan migrate --path=/database/migrations/2026_01_10_000001_xxx.php

# Rollback last migration
php artisan migrate:rollback
```

---

## Summary

| Component | Technology |
|-----------|------------|
| CI/CD Platform | GitHub Actions |
| Deployment Method | SSH |
| Server | Ubuntu Linux |
| PHP Version | 8.2 |
| Node.js Version | 20 |
| Framework | Laravel |
| Web Server | Nginx |
| Database | MySQL |

---

## Support

For issues with CI/CD pipeline:
1. Check GitHub Actions logs
2. Verify SSH connectivity
3. Check server permissions
4. Review this documentation

---

*Last Updated: January 2026*
*Rakez ERP Team*

