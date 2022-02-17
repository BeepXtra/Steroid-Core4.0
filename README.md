# node

The Steroid4.0 (BPC) masternode.

## Install

**Hardware Requirements:**
```
2GB RAM
1 CPU Core
50GB DISK
```
**Requirements:**

- PHP 7.2
  - PDO extension
  - GMP extension
  - BCMath extension
- MySQL/MariaDB

1. Install MySQL or MariaDB and create a database and a user.
2. Rename `include/config-sample.inc.php` to  `include/config.inc.php` and set the DB login data
3. Change permissions to tmp and `tmp/db-update` to 777 (`chmod 777 tmp -R`)
4. Access the http://ip-or-domain and refresh once

## Usage

This app should only be run in the main directory of the domain/subdomain, ex: http://111.111.111.111

The node should have a public IP and be accessible over internet.

## Links

- Official website: https://www.steroid.io
- Block explorer: https://explorer.steroid.io
- Blog: https://blog.steroid.io

If you'd like to support the Steroid4.0 development please contact us
devteam@steroid.io