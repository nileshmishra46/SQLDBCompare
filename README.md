[README.md](https://github.com/user-attachments/files/28850955/README.md)
# 🔍 SQLDBCompare

A PHP-based web tool for Database Administrators to **detect and resolve schema drift** between two Microsoft SQL Server databases — via live connection or uploaded `.sql` DDL files.

---

## 📌 Why This Tool?

In multi-environment SQL Server setups (Dev → UAT → Production) or multi-tenant deployments, databases are expected to share an identical schema but serve different data. Over time, **schema drift** occurs — a missing index here, an unapplied constraint there — causing silent bugs, performance issues, or broken application logic.

**SQLDBCompare** gives DBAs a fast, reliable way to catch these differences and get a ready-to-run T-SQL fix script in one go.

---

## ✨ Features

- 🔌 **Two Input Modes** — connect live to SQL Server or upload two `.sql` DDL files
- 🔐 **TrustServerCertificate Support** — compatible with SQL Server 2019+ and SSMS 19+
- 🧩 **Deep Schema Comparison** across all major object types:
  - Tables & Columns (type, nullability, defaults)
  - Primary Keys & Foreign Keys
  - Unique, Check & Default Constraints
  - Non-Clustered & Clustered Indexes (with included columns + filter definitions)
  - Triggers
- 📊 **Colour-Coded HTML Report** with per-category tabbed view
- 📄 **Downloadable T-SQL Fix Script** — review and run directly against your target DB
- ⚠️ **Missing Table Detection** — flags tables present in one DB but absent in the other
- 🛡️ **Security-First** — parameterised queries, upload validation, XSS-sanitised output

---

## 🖥️ Tech Stack

| Layer | Technology |
|---|---|
| Language | PHP 8.1+ |
| DB Driver | `sqlsrv` / `pdo_sqlsrv` (Microsoft) |
| Frontend | HTML5 + Bootstrap 5 |
| Schema Queries | SQL Server DMVs & `INFORMATION_SCHEMA` views |
| SQL Parsing | Custom PHP DDL parser |
| Output | HTML Report + `.sql` Fix Script |
| Launcher | C# (`Launcher.cs`) — optional desktop launcher |
| Build | PowerShell (`build_dist.ps1`) — distribution packager |

---

## 📋 Prerequisites

### PHP Extensions

```bash
extension=sqlsrv
extension=pdo_sqlsrv
```

**Linux (Ubuntu/Debian):**
```bash
sudo apt install php8.1-dev unixodbc-dev
pecl install sqlsrv pdo_sqlsrv
```

**Windows (XAMPP / Laragon):**
Download `php_sqlsrv_81_ts_x64.dll` and `php_pdo_sqlsrv_81_ts_x64.dll` from the [Microsoft PHP Driver releases](https://github.com/microsoft/msphpsql/releases) and place them in your PHP `ext/` directory.

### Microsoft ODBC Driver
Required by the `sqlsrv` extension.
→ [Install ODBC Driver for SQL Server](https://learn.microsoft.com/en-us/sql/connect/odbc/download-odbc-driver-for-sql-server)

---

## 🚀 Getting Started

```bash
# Clone the repository
git clone https://github.com/nileshmishra46/SQLDBCompare.git
cd SQLDBCompare

# Set permissions for upload and output directories
chmod 750 uploads/
chmod 750 output/fix_scripts/

# Configure settings
cp config/settings.example.php config/settings.php
```

Open `config/settings.php` and adjust as needed:

```php
return [
    'trust_server_certificate' => true,   // Required for SQL Server 2019+ / SSMS 19+
    'encrypt'                  => true,
    'default_port'             => 1433,
    'upload_max_size_mb'       => 20,
    'comparison_ignore_case'   => true,
];
```

Serve via Apache/Nginx or PHP's built-in server:

```bash
php -S localhost:8080
```

Then open `http://localhost:8080` in your browser.

> **Windows users:** You can also use `Launcher.cs` to launch the tool as a desktop application, or run `build_dist.ps1` to package a distributable release.

---

## 🗂️ Project Structure

```
SQLDBCompare/
├── index.php                  # Mode selector (Live DB or File Upload)
├── connect.php                # Live connection form + handler
├── upload.php                 # .sql file upload form + handler
├── compare.php                # Comparison controller
├── report.php                 # HTML diff report renderer
├── run_tests.php              # Test runner for comparison engine
├── Launcher.cs                # Optional C# desktop launcher (Windows)
├── build_dist.ps1             # PowerShell build & distribution packager
├── .gitignore
│
├── engine/
│   ├── DbInspector.php        # Fetches schema via DMVs from live SQL Server
│   ├── SqlFileParser.php      # Parses uploaded .sql DDL files
│   ├── SchemaComparator.php   # Produces structured diff between two schemas
│   └── ScriptGenerator.php   # Generates T-SQL fix scripts from diff
│
├── config/
│   ├── db.php                 # PDO connection helper (TrustServerCertificate)
│   └── settings.php           # App configuration
│
├── assets/
│   ├── css/style.css
│   └── js/app.js
│
├── uploads/                   # Temp storage for uploaded .sql files
└── output/fix_scripts/        # Generated .sql fix files per run
```

---

## 🔄 How It Works

```
┌──────────────────────────────────┐
│  Choose Input Mode               │
│  A) Live SQL Server Connection   │
│  B) Upload .sql DDL Files        │
└──────────────┬───────────────────┘
               │
       ┌───────▼────────┐
       │ Schema Fetcher │  ← DbInspector (live) or SqlFileParser (file)
       └───────┬────────┘
               │
       ┌───────▼────────┐
       │   Comparator   │  ← Compares Source vs Target across all object types
       └───────┬────────┘
               │
    ┌──────────┴──────────┐
    │                     │
┌───▼────────┐   ┌────────▼──────────┐
│ HTML Report│   │ T-SQL Fix Script  │
│ (browser)  │   │ (.sql download)   │
└────────────┘   └───────────────────┘
```

---

## 📊 What Gets Compared

| Object | Compared By |
|---|---|
| Tables | Presence in both DBs |
| Columns | Name, data type, length, nullability, default value |
| Primary Keys | Constraint name, column(s), key order |
| Foreign Keys | Constraint name, parent/reference table & column, ON DELETE/UPDATE action |
| Unique Constraints | Constraint name, column(s) |
| Check Constraints | Constraint name, check expression |
| Default Constraints | Constraint name, column, default value expression |
| Indexes | Name, type (clustered/nonclustered), columns, included columns, filter, uniqueness |
| Triggers | Name, parent table, event type, enabled/disabled state |

---

## 📄 Sample Fix Script Output

```sql
-- Generated by SQLDBCompare
-- Run Date: 2025-06-12
-- Source: ProductionDB | Target: StagingDB
-- WARNING: Review before executing in production

USE [StagingDB];
GO

-- MISSING INDEX: IX_Orders_CustomerId on dbo.Orders
CREATE NONCLUSTERED INDEX [IX_Orders_CustomerId]
ON [dbo].[Orders] ([CustomerId] ASC)
INCLUDE ([OrderDate], [Status]);
GO

-- MISSING FOREIGN KEY: FK_Orders_Customers on dbo.Orders
ALTER TABLE [dbo].[Orders]
ADD CONSTRAINT [FK_Orders_Customers]
FOREIGN KEY ([CustomerId])
REFERENCES [dbo].[Customers] ([CustomerId])
ON DELETE NO ACTION ON UPDATE NO ACTION;
GO
```

---

## 🛡️ Security

- All schema queries use SQL Server system views — no user input is interpolated into SQL
- Credentials are held only in the PHP session and never logged or stored
- Uploaded files are validated by extension and MIME type, stored outside the web root
- All DB object names are `htmlspecialchars()` escaped before rendering in the report

---

## 🗺️ Roadmap

- [ ] Stored procedure & view comparison
- [ ] Three-way compare (Dev / UAT / Prod)
- [ ] Email report delivery
- [ ] Scheduled nightly comparison with drift alerts
- [ ] Apply fix script directly to Target DB (with confirmation step)
- [ ] Comparison history log (SQLite)
- [ ] REST API mode (JSON in / JSON out)

---

## 🤝 Contributing

Pull requests are welcome. For major changes, please open an issue first to discuss what you'd like to change.

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/stored-proc-compare`)
3. Commit your changes (`git commit -m 'Add stored procedure comparison'`)
4. Push to the branch (`git push origin feature/stored-proc-compare`)
5. Open a Pull Request

---

## Screenshots 
<img width="1478" height="753" alt="Screenshot 2026-06-12 003517" src="https://github.com/user-attachments/assets/969ae416-7138-4987-b1f3-f60b3cdfc533" />

<img width="1918" height="1088" alt="Screenshot 2026-06-12 004948" src="https://github.com/user-attachments/assets/d34b987c-f31a-4723-8fa6-0ab60032287a" />

<img width="1918" height="1197" alt="Screenshot 2026-06-12 003824" src="https://github.com/user-attachments/assets/f7beda08-d856-4aa6-b90b-4424a790ee4a" />

<img width="1918" height="1133" alt="Screenshot 2026-06-12 004218" src="https://github.com/user-attachments/assets/61e4f819-96ac-4460-86f7-c19e6d026643" />

<img width="1918" height="1133" alt="Screenshot 2026-06-12 004255" src="https://github.com/user-attachments/assets/8297637c-062f-46d6-b6f2-6260af160c79" />

<img width="1918" height="1137" alt="Screenshot 2026-06-12 004511" src="https://github.com/user-attachments/assets/9e060d0c-4471-4bf0-90b9-2fa03ae4a506" />

<img width="1918" height="1135" alt="Screenshot 2026-06-12 004543" src="https://github.com/user-attachments/assets/821284c2-09c4-4d5c-9958-a4451e1befcb" />



---

## 👤 Author

**Nilesh Mishra** — Database Administrator 
- GitHub: [https://github.com/nileshmishra46]
- LinkedIn: [https://www.linkedin.com/in/nilesh46/]

