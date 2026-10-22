# PT Inventory Manager

Web app for tracking client application VMs and database VMs. It runs on PHP 8.3, nginx, and MySQL 8.0. People sign in with local accounts. Roles are Admin, OPs, and View-only.

## Requirements

- PHP 8.3 with `pdo_mysql` and `mbstring`
- MySQL 8.0
- nginx, with the site root set to the `public` directory

RHEL 8 does not ship PHP 8.3 in the base repositories. Use the PHP 8.3 php-fpm build already installed on the host, and point nginx at that pool's socket.

## Setup

1. Copy the config and edit the database settings:

   ```bash
   cp config/config.example.php config/config.php
   ```

2. Create an empty database and user, then import the schema:

   ```sql
   CREATE DATABASE pt_inventory CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE USER 'pt_inventory'@'localhost' IDENTIFIED BY 'a-strong-password';
   GRANT SELECT, INSERT, UPDATE, DELETE ON pt_inventory.* TO 'pt_inventory'@'localhost';
   ```

   Import the schema with an administrator account. The application account does not get `FILE`, `SUPER`, or schema-change privileges. Put the application account in `config/config.php`.

   ```bash
   mysql -u root -p pt_inventory < sql/schema.sql
   ```

3. Point nginx at `public` and pass PHP to php-fpm. Replace the socket with the one from the PHP 8.3 pool:

   ```nginx
   server {
       listen 443 ssl;
       http2 on;
       server_name inventory.example.com;
       root /var/www/pt-inventory/public;
       server_tokens off;
       client_max_body_size 2m;

       ssl_protocols TLSv1.2 TLSv1.3;
       ssl_certificate /etc/pki/tls/certs/inventory.crt;
       ssl_certificate_key /etc/pki/tls/private/inventory.key;

       add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
       add_header X-Content-Type-Options nosniff always;
       add_header X-Frame-Options DENY always;
       add_header Referrer-Policy same-origin always;

       location / {
           try_files $uri /index.php?$query_string;
       }

       location = /index.php {
           include fastcgi_params;
           fastcgi_param SCRIPT_FILENAME $document_root/index.php;
           fastcgi_pass unix:/run/php-fpm/www.sock;
       }

       location ~ \.php$ {
           return 404;
       }

       location ~ /\. {
           deny all;
       }
   }
   ```

   Use the host's approved certificate and cipher policy. Only `index.php` is executed.

4. Open the site. The first visit asks for the initial admin username, display name, and password. There is no default password in the repository.

5. Sign in and add clients under Field options. Environment tabs, stages, applications, and operating systems are already loaded.

Set `cookie_secure` to `true` and `debug` to `false` before the site is used. With debug off, the app will not start a session until `cookie_secure` is true. Times in the app use `display_timezone` (default `America/New_York`). The database stores timestamps in UTC.

A session ends after 15 minutes idle, or 12 hours after sign-in, whichever comes first. Passwords are hashed with Argon2id when PHP provides it. Eight failed sign-ins for one username, or 30 from one address, pause further attempts for 15 minutes. The app uses the address nginx connected from. Do not trust `X-Forwarded-For` inside PHP; if a proxy sits in front, configure nginx `real_ip` so `REMOTE_ADDR` is the client.

In the PHP 8.3 pool, set `expose_php = Off`, `display_errors = Off`, `log_errors = On`, `allow_url_include = Off`, and `cgi.fix_pathinfo = 0`. Keep PHP and MySQL on supported security updates.

## Roles

| Role | Access |
|---|---|
| Admin | Everything OPs can do, plus users and dropdown values |
| OPs | Add, edit, delete, and decommission inventory |
| View-only | View, search, filter, and export |

Dropdowns are Environments, Clients, Deployments, Applications, and Operating systems. Client defaults to PT when left unset. OPs pick from those lists. Only an admin can add, rename, retire, or delete the values. Adding an environment creates a tab.

## Inventory

The inventory page has a tab for each environment. APP VMs and DB VMs in the same stack are shown side by side, including stacks with many APP VMs, many DB VMs, or only one side. The header search matches a VM name, a website URL, a full IP, or the last octets of an IP.

An APP VM can still link to many DB VMs, one DB VM, or none. A DB VM can serve many APP VMs. Those links are separate from the stack, which is what places the records next to each other.

APP VM fields: VM name, client, environment, deployment, stack, OS, internal IP, external IP, URL, BrotherHood URL, Status Page, applications, notes, troubleshooting, and linked DB VMs. Troubleshooting is stored with the VM and shown on its detail page, not on the inventory card.

DB VM fields: VM name, client, environment, deployment, stack, OS, internal IP, Redis (yes or no), notes, troubleshooting, and linked APP VMs. Troubleshooting is shown on the detail page, not on the inventory card.

Preloaded values:

- Environments: Z0, Z2, Azure, AWS
- Client: PT
- Deployments: prod, test, staging
- Applications: Moodle, Drupal, LMSX
- Operating systems: RHEL8, RHEL9, Rocky9, Windows

Decommission moves a VM onto the DECOM tab. It can be restored. Delete is permanent for both Admin and OPs, and the audit log keeps the field values from the deleted record.

The dashboard, search, filters, CSV export, audit log, and per-field history are available to every signed-in user. Export follows the current filters and includes every matching row, not only the visible page.

## Local smoke test

From the project directory, with PHP 8.3 on your PATH:

```bash
php -S 127.0.0.1:8080 -t public public/router.php
```

The built-in server is only for a quick check. Use nginx and php-fpm on the RHEL host.
