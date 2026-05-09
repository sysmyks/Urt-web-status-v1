# Urban Terror Server Status — status-v1

Web interface for **Urban Terror 4.3** servers, built with PHP.  
Displays server status, online players, available maps, and includes a full administration panel.

---

## Features

- **Server status** — online players, current map, scores, pings
- **Map vote** — visitors can start a vote to change the map
- **Map gallery** — map list with images, details and jump records
- **Admin panel** — password protected (bcrypt)
  - Online player list with kick / ban actions
  - Ban management (read/write `banlist.txt` + automatic `rehashbans`)
  - Players & bans from Spunkybot's SQLite database
  - Server commands: global message, map change, map restart
- **Multilingual** — French / English

---

## Requirements

### Server
- Debian / Ubuntu (VPS or dedicated)
- **Apache2**
- **PHP 7.4+** with extensions:
  - `php7.4-sqlite3` (Spunkybot database access)
  - `php-sockets` (RCON UDP communication)
  - `php-curl` (optional)

### Game side
- **Urban Terror 4.3** running
- **Spunkybot** (optional, for player/ban database)
- RCON password configured on the URT server (`sv_rconpassword`)

---

## Installation

### 1. Install dependencies

```bash
sudo apt update
sudo apt install apache2 php php7.4-sqlite3 php-sockets -y
sudo systemctl restart apache2
```

### 2. Deploy files

Copy the files to your web directory:

```bash
# Via SCP from your local machine
scp -r ./status-v1/* user@your-server:/var/www/html/status-v1/
```

Or use FileZilla / any SFTP client.

### 3. Set permissions

```bash
sudo chown -R www-data:www-data /var/www/html/status-v1/
sudo chmod -R 755 /var/www/html/status-v1/
sudo chmod -R 775 /var/www/html/status-v1/cache/
sudo chmod -R 775 /var/www/html/status-v1/images/maps/

# Allow www-data to read/write the Spunkybot database
sudo usermod -a -G urt www-data
sudo chmod 664 /home/urt/spunkybot-1.13.0/data.sqlite
sudo chmod 775 /home/urt/spunkybot-1.13.0/

# Allow www-data to write to banlist.txt
sudo chmod 664 /home/urt/UrbanTerror43/q3ut4/banlist.txt
sudo chown urt:www-data /home/urt/UrbanTerror43/q3ut4/banlist.txt

sudo systemctl restart apache2
```

---

## Configuration

Edit `config/config.php`:

```php
return [
    'server_address'  => '127.0.0.1',   // URT server IP
    'server_port'     => 27960,          // URT server UDP port
    'rcon_password'   => 'YOUR_PASSWORD', // sv_rconpassword in server.cfg
    'timeout'         => 5,
    'cache_duration'  => 60,             // Status cache duration (seconds)

    'maps_directory'         => '/home/urt/UrbanTerror43/q3ut4',
    'local_images_directory' => __DIR__ . '/../images/maps',
    'mapinfo_file'           => '/home/urt/spunkybot-1.13.0/mod/mapinfo.json',
    'records_file'           => '/home/urt/spunkybot-1.13.0/mod/jump_records.json',

    // Spunkybot database
    'spunky_sqlite'   => '/home/urt/spunkybot-1.13.0/data.sqlite',

    // URT ban file
    'banlist_file'    => '/home/urt/UrbanTerror43/q3ut4/banlist.txt',

    // Admin panel
    'admin_username'      => 'admin',
    'admin_password_hash' => '',  // ← generated in the next step
];
```

---

## Setting the admin password

Generate the hash directly on the server via SSH:

```bash
php -r "echo password_hash('YOUR_PASSWORD', PASSWORD_BCRYPT) . PHP_EOL;"
```

Copy the result (starts with `$2y$...`) into `config.php` → `admin_password_hash`.

> **If you prefer a web interface**, the `generate_hash.php` file is included.  
> Remove the IP restriction in that file, deploy it, visit the page, generate your hash,  
> then **delete `generate_hash.php` from the server immediately after**.

---

## Project structure

```
status-v1/
├── config/
│   └── config.php          ← Main configuration
├── admin/
│   ├── login.php           ← Admin login page
│   ├── index.php           ← Admin dashboard
│   ├── action.php          ← AJAX endpoint (RCON + database)
│   ├── auth_check.php      ← Session guard
│   └── logout.php
├── lib/
│   ├── Rcon.php            ← RCON UDP communication
│   ├── SpunkyDb.php        ← Spunkybot SQLite access
│   ├── Server.php          ← Server status (getstatus)
│   ├── StatusParser.php    ← Server response parser
│   ├── MapDataManager.php  ← Map data manager
│   └── MapImageManager.php ← Map image manager
├── lang/
│   ├── fr.php              ← French translations
│   └── en.php              ← English translations
├── cache/
│   └── server_status.json  ← Server status cache
├── images/maps/            ← Map images (auto-downloaded)
├── css/
├── index.php               ← Server status page
├── maps.php                ← Map gallery
├── map_details.php         ← Map detail page
├── vote_map.php            ← Map vote AJAX endpoint
├── lang_init.php           ← Language initialization
└── generate_hash.php       ← Hash utility (delete after use)
```

---

## Compatibility notes

- Tested with **PHP 7.4** on Debian — **PHP 8+** also works
- RCON uses **UDP sockets** — make sure the `sockets` extension is enabled:
  ```bash
  php -m | grep sockets
  ```
- Urban Terror uses `banlist.txt` (format `IP:-1`) — the admin panel automatically sends `rehashbans` via RCON after every change to reload bans without restarting the server.

---

## License

Personal project — free to use and modify.
