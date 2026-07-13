# Moloni ON WHMCS - Setup & Installation Guide

## Prerequisites

- **WHMCS 7.0+** installed and running
- **PHP 7.4+** on server
- **Composer** installed
- **Moloni ON Account** with API access (https://www.molonion.pt/)
- **Git** (for version control, optional but recommended)

---

## Step 1: Initial Project Setup

### 1a. Clone or Initialize Repository
```bash
# Option A: Clone from git
git clone https://github.com/your-org/moloni-on-whmcs.git
cd moloni-on-whmcs

# Option B: Create from scratch
mkdir moloni-on-whmcs
cd moloni-on-whmcs
git init
```

### 1b. Install Composer Dependencies
```bash
composer install
```

This installs:
- PSR logging interface
- PHPUnit for testing
- PHP CodeSniffer for code quality
- Any HTTP client for API calls

### 1c. Copy to WHMCS Modules Directory
```bash
# Copy addon module to WHMCS
cp -r . /path/to/whmcs/modules/addons/moloni_on/

# Or create symlink for development
ln -s $(pwd) /path/to/whmcs/modules/addons/moloni_on
```

---

## Step 2: Database Setup

### 2a. Create Database Tables
The module installs tables on first activation:

**Tables Created:**

1. **`mod_moloni_on_config`** - Settings storage
   ```sql
   CREATE TABLE IF NOT EXISTS `mod_moloni_on_config` (
     `id` INT PRIMARY KEY AUTO_INCREMENT,
     `setting_key` VARCHAR(255) UNIQUE,
     `setting_value` LONGTEXT,
     `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
   );
   ```

1b. **`mod_moloni_on_auth`** - Single-row OAuth2 session (credentials + tokens)
   ```sql
   CREATE TABLE IF NOT EXISTS `mod_moloni_on_auth` (
     `id` INT PRIMARY KEY AUTO_INCREMENT,
     `client_id` TEXT,
     `client_secret` TEXT,
     `access_token` TEXT,
     `refresh_token` TEXT,
     `access_expire` INT DEFAULT 0,
     `refresh_expire` INT DEFAULT 0,
     `company_id` INT DEFAULT 0
   );
   ```

2. **`mod_moloni_on_orders`** - Order tracking
   ```sql
   CREATE TABLE IF NOT EXISTS `mod_moloni_on_orders` (
     `id` INT PRIMARY KEY AUTO_INCREMENT,
     `order_id` INT UNIQUE NOT NULL,
     `moloni_document_id` VARCHAR(255),
     `document_type` VARCHAR(50),
     `status` ENUM('pending','synced','discarded','failed') DEFAULT 'pending',
     `error_message` TEXT,
     `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
     `synced_at` TIMESTAMP NULL,
     `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
     FOREIGN KEY (`order_id`) REFERENCES `tblorders`(`id`) ON DELETE CASCADE
   );
   ```

3. **`mod_moloni_on_logs`** - Application logs
   ```sql
   CREATE TABLE IF NOT EXISTS `mod_moloni_on_logs` (
     `id` INT PRIMARY KEY AUTO_INCREMENT,
     `level` ENUM('debug','info','notice','warning','error','critical') DEFAULT 'info',
     `message` TEXT,
     `context` JSON,
     `order_id` INT,
     `timestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
     KEY `idx_level` (`level`),
     KEY `idx_timestamp` (`timestamp`)
   );
   ```

4. **`mod_moloni_on_documents`** - Created documents (persist each document created in Moloni ON)
   ```sql
   CREATE TABLE IF NOT EXISTS `mod_moloni_on_documents` (
     `id` INT PRIMARY KEY AUTO_INCREMENT,
     `order_id` INT NOT NULL,
     `order_total` FLOAT,
     `invoice_id` INT,
     `invoice_date` DATE,
     `invoice_status` INT,
     `invoice_total` FLOAT,
     `value` FLOAT,
     KEY `idx_order_id` (`order_id`),
     KEY `idx_invoice_id` (`invoice_id`)
   );
   ```

   Equivalent WHMCS/Illuminate schema (used by the Installer):
   ```php
   $table->increments('id');
   $table->integer('order_id');
   $table->float('order_total');
   $table->integer('invoice_id');
   $table->date('invoice_date');
   $table->integer('invoice_status');
   $table->float('invoice_total');
   $table->float('value');
   ```

### 2b. Automate Table Creation (Optional)
Create `/src/MoloniOn/Database/Installer.php`:
```php
<?php
namespace MoloniOn\Database;

class Installer {
    public static function install() {
        // Create tables on module activation
        // Called from moloni_on.php activate_hook
    }
    
    public static function uninstall() {
        // Drop tables on module deactivation (optional)
    }
}
```

---

## Step 3: Activate in WHMCS

### 3a. Module Activation
1. Log into **WHMCS Admin Panel**
2. Navigate: **Setup** → **Addon Modules**
3. Find **Moloni ON** in the list
4. Click **Activate**
5. Confirm message: "Moloni ON addon module activated"

### 3b. Grant Permissions (if needed)
1. Go to **Setup** → **Admin Roles**
2. Select your admin role
3. Check **Addon: Moloni ON** under permissions
4. Save changes

---

## Step 4: Module Configuration

### 4a. Access the Module
1. Navigate: **Addons** → **Moloni ON**
2. You should see the **Login** page

### 4b. Authenticate with Moloni ON (OAuth2)
The module uses the Moloni ON OAuth2 authorization-code flow — there is no
static API key.
1. In your Moloni ON account, create an **API client** to obtain an **API Client ID**
   (developer id) and a **Client Secret**
2. In the WHMCS Moloni ON module login page, enter the **API Client ID** and **Client Secret**
   and click **Connect**
3. You're redirected to the Moloni ON authorization center (https://ac.molonion.pt/) to
   authorize this WHMCS installation
4. After authorizing, Moloni ON redirects back to the module with a `code`, which the module
   exchanges for **access** and **refresh** tokens (stored in `mod_moloni_on_auth`)
5. On success, you're redirected to **Company Select**

### 4c. Select Moloni ON Company
1. Choose your active Moloni ON company from the list
2. Click **Select Company**
3. You're now redirected to the **Dashboard**

---

## Step 5: Configure Module Settings

### 5a. Settings Page
1. Click **Settings** tab
2. Configure:
   - **Default Document Type:** INVOICE, PRO_FORMA_INVOICE, SIMPLIFIED_INVOICE, INVOICE_RECEIPT, PURCHASE_ORDER
   - **Document Status:** Draft, Finalized, etc.
   - **Tax Exemption Setting:** Yes/No (optional)
3. Click **Save Settings**

### 5b. Test Connection
1. Go to **Tools** tab
2. Click **Test Moloni ON Connection**
3. Confirm API key is valid and company is selected

---

## Step 6: Development & Code Quality

### 6a. Install Development Tools
Composer installs:
- `phpunit` for testing
- `squizlabs/php_codesniffer` for linting
- `phpstan` for static analysis (optional)

### 6b. Code Standards Check
```bash
# Run PHP CodeSniffer
composer lint

# Fix auto-fixable issues
composer lint:fix

# Run tests
composer test

# Run static analysis
composer analyse
```

### 6c. Pre-commit Hook (Optional)
Create `.git/hooks/pre-commit`:
```bash
#!/bin/bash
composer lint || exit 1
composer test || exit 1
```

Make executable:
```bash
chmod +x .git/hooks/pre-commit
```

---

## Step 7: GraphQL Operations

Each GraphQL operation is a PHP class under `/src/MoloniOn/GraphQL/` extending
`AbstractOperation`. The GraphQL document lives in the `QUERY` constant; `operation()`
returns the root field name (used to locate `data`/`errors` in the response) and
`variables($data)` builds the payload.

```
/src/MoloniOn/GraphQL/
├── Queries/    GetCompanies, GetCompany, GetCustomers, GetDocument,
│               GetDocumentSets, GetCountries, GetProducts, GetTaxes, ...
└── Mutations/  CreateCustomer, CreateDocument, UpdateDocumentStatus,
                CreateProduct, CreateTax
```

```php
<?php
namespace MoloniOn\GraphQL\Queries;

use MoloniOn\GraphQL\AbstractOperation;

class GetCountries extends AbstractOperation
{
    protected const OPERATION = 'countries';

    protected const QUERY = <<<'GRAPHQL'
    query countries($options: CountryOptions) { countries(options: $options) { data { countryId iso3166_1 } errors { field msg } } }
    GRAPHQL;
}
```

> Queries are embedded as PHP string constants (not separate `.graphql` files), so IDE
> GraphQL schema autocomplete is not available for them by design.

Endpoint and OAuth constants live in `src/MoloniOn/Support/Platform.php`
(`API_URL = https://api.molonion.pt/v1`); there is no `.env` file.

---

## Step 8: Testing the Integration

### 8a. Create a Test Order
1. Create a WHMCS test invoice/order
2. Go to **Moloni ON** → **Orders**
3. See your test order in the pending list
4. Click **Create Document**
5. Check Moloni ON dashboard for created document

### 8b. View Logs
1. Go to **Moloni ON** → **Logs**
2. See all actions logged with timestamps
3. Check for errors if creation failed

### 8c. Download PDF
1. Go to **Moloni ON** → **Documents**
2. Click **Download PDF** for any document
3. Verify PDF is fetched from Moloni ON and downloads successfully

---

## Step 9: Deployment Checklist

Before going live:

- [ ] All tests pass (`composer test`)
- [ ] No CodeSniffer warnings (`composer lint`)
- [ ] OAuth credentials/tokens stored in `mod_moloni_on_auth` (not in code)
- [ ] Database tables created successfully
- [ ] Module activated and permissions set
- [ ] Test order sync works end-to-end
- [ ] PT and EN translations verified
- [ ] Error logging working
- [ ] PDF downloads working
- [ ] Bulk operations tested (5+ orders)
- [ ] Edge cases tested (failed creations, duplicates, etc.)

---

## Extending the module (custom hooks)

The module fires custom WHMCS hooks you can subscribe to from a file in your
WHMCS `/includes/hooks/` directory (e.g. `includes/hooks/moloni_on.php`). No
core files are touched, so your customisations survive module updates.

```php
<?php

// Rename the product the module CREATES in Moloni ON (permanent — a product
// cannot be renamed later; the order-specific name still shows on the document).
add_hook('MoloniOnProductName', 1, function (array $vars) {
    // $vars: type, reference, item, displayName, value (the default name)
    if ($vars['type'] === 'Hosting') {
        return 'Serviço de Alojamento';
    }
    return null; // null / '' = keep the default
});

// Amend the document payload before it is sent to Moloni ON.
add_hook('MoloniOnBeforeCreateDocument', 1, function (array $vars) {
    $payload = $vars['value']; // the <Type>Insert array
    $payload['notes'] = 'WHMCS order #' . $vars['order_id'];
    return $payload;
});

// Keep a matched document as a draft instead of closing it (return false).
add_hook('MoloniOnBeforeCloseDocument', 1, function (array $vars) {
    return $vars['order_total'] <= 1000; // only auto-close orders up to 1000
});

// React after a document is created / closed / failed.
add_hook('MoloniOnAfterCreateDocument', 1, function (array $vars) {
    logActivity('Moloni document ' . $vars['document_id'] . ' created.');
});
```

Available hooks: `MoloniOnProductName`, `MoloniOnBeforeCreateDocument`,
`MoloniOnAfterCreateDocument`, `MoloniOnBeforeCloseDocument`,
`MoloniOnAfterCloseDocument`, `MoloniOnDocumentFailed`. See the hook table in
[CLAUDE.md](CLAUDE.md) for each one's payload and shape (filter / action / veto).

---

## Troubleshooting

### Module Not Appearing in Addons List
- Ensure `moloni_on.php` is in `/modules/addons/moloni_on/`
- Check WHMCS error logs: `/includes/logs/`
- Verify PHP syntax: `php -l moloni_on.php`

### API Connection Failed
- Verify API key is correct
- Check Moloni ON API status (https://status.molonion.pt/)
- Review logs in **Logs** tab for error details
- Test API key from Moloni ON settings page

### Documents Not Creating
- Check **Logs** tab for creation errors
- Verify company is selected
- Check document type is valid
- Ensure customer exists in Moloni ON

### Database Table Errors
- Verify MySQL user has CREATE TABLE permissions
- Check WHMCS database connection
- Review WHMCS error logs

### CodeSniffer Issues
```bash
# See all issues
composer lint

# Auto-fix simple issues
./vendor/bin/phpcs --standard=PSR12 src/ --fix

# Manually fix reported issues
```

---

## File Checklist (Verify All Present)

```
moloni-on-whmcs/
├── moloni_on.php                ✓ Main entry point
├── hooks.php                    ✓ WHMCS hooks
├── composer.json                ✓ Dependencies
├── phpcs.xml                    ✓ Code standards config
├── phpunit.xml                  ✓ Test config
├── src/                         ✓ Source code
├── templates/                   ✓ UI templates
├── public/                      ✓ CSS/JS/images
├── lang/                        ✓ Translations (en.php, pt.php)
├── tests/                       ✓ Unit & integration tests
├── .claude/journal/             ✓ Project journal
└── README.md                    ✓ Project overview
```

---

## Next Steps

1. **Review ARCHITECTURE.md** for detailed system design
2. **Start Development:** Implement features in order
   - Authentication → Company Select → Orders → Documents → Logs
3. **Write Tests:** Add unit tests for each service
4. **Monitor Logs:** Check `/mod_moloni_on_logs` table regularly
5. **Release:** Package and distribute

---

## Support & Questions

For issues or questions:
1. Check logs in WHMCS Moloni ON module
2. Review Moloni ON API docs: https://docs.molonion.pt/
3. Check WHMCS documentation: https://docs.whmcs.com/
4. Review project journal in `.claude/journal/`

---

**Version:** 1.0.0  
**Last Updated:** July 2, 2026
