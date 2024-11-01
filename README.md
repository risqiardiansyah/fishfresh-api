
# Introduction

Vogaon (user backend).

## Table Of Contents

## Requirements
- Laravel 8.x (PHP 8.2.4)
- Composer
- mySql 8.x version
- nginx or apache


Make sure you have PHP, Composer & mysql installed in your system. Recommended php version >=v 8.2.4 You check your ones by running these commands
```bash
php -v
```
If it's not installed in your system then please install them by checking official documentation of,

https://www.php.net/downloads.php

Before starting the project, you need to configure the 
- .env
file for the of our packages.


## Configuration


## Start the project
After all the configurations, Install Package dependency by running below command at the root directory to get started with the project,

### Install packages
Install vendor using composer

```bash
composer update
```

### Configure .env
Copy .env.example file

```bash
cp .env.example .env
```

For configuring package, go to packages create or edit 
- .env
file and configure it with
your information in the .env file.

- DB_DATABASE=[DATABASE NAME]
- DB_USERNAME=[DATABASE USERNAME]
- DB_PASSWORD=[DATABASE PASSWORD]

- MAIL_HOST=[SMTP HOST]
- MAIL_PORT=[MAIL PORT]
- MAIL_USERNAME=[MAIL USERNAME]
- MAIL_PASSWORD=[MAIL PASSWORD]
- MAIL_ENCRYPTION=[MAIL ENCRYPT]
- MAIL_FROM_ADDRESS=[MAIL ADDRESS]
- MAIL_FROM_NAME=[MAIL FROM NAME]

- UNIPIN_DEV_GUID=[UNIPIN DEV GUID]
- UNIPIN_DEV_SECRET_KEY=[UNIPIN DEV SECRET KEY]

- APIGAME_MERCHANTID=[APIGAME MECHANT ID]
- APIGAME_SIGNATURE=[APIGAME SIGNATURE]
= APIGAME_USER_ID=[APIGAME USER ID]
- APIGAME_SECRETKEY=[APIGAME SECRET KEY]

- MIDTRANS_KEY=[MIDTRANS KEY]
- DIGI_USERNAME=[DIGI USERNAME]
- DIGI_APIKEY=[DIGI APIKEY]
- ADMIN_DOMAIN=[ADMIN BACKEND DOMAIN]

- DUITKU_APIKEY=[DUITKU API KEY]
- DUITKU_MERCHANTID=[DUITKU MERCHANT ID]
- SITE_URL=[USER BACKEND DOMAIN]

### Example
```bash
DB_DATABASE=vogaon
DB_USERNAME=userdb
DB_PASSWORD=b@T[asd21!

MAIL_HOST=smtp.googlemail.com
MAIL_PORT=587
MAIL_USERNAME=email@gmail.com
MAIL_PASSWORD="ktqypsyjj131sasuvaxwtt"
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=email@gmail.com
MAIL_FROM_NAME="vogaon"

UNIPIN_DEV_GUID=129182j0wqe12-12kosd-912391
UNIPIN_DEV_SECRET_KEY=sskKiLAOSDEJS

APIGAME_MERCHANTID=M230121sada231344wHSJQ7910PJ
APIGAME_SIGNATURE=fcbee1483afc9bqweasd0232ffb12980484acac
APIGAME_USER_ID=s44gdsd4d%232313924
APIGAME_SECRETKEY=21kksdi213j90sd123ijsda

MIDTRANS_KEY=U0ItTWlOTdZNGowMkZnX3B5UDJxek0=

DIGI_USERNAME=jusjqushfnni
DIGI_APIKEY=ef7761ba02d35-edb
ADMIN_DOMAIN=https://backadm.vogaon.com/

DUITKU_APIKEY=aff3871sjuaed53ae2556f965
DUITKU_MERCHANTID=IDLKA2234
SITE_URL=https://back.vogaon.com/
```


Then run the following command :

```php
php artisan key:generate
```

### Migrate Data
create an empty database with mysql 8.x version, then setup that fresh db at your .env file, then run the following command to generate all tables and seeding dummy data:

```php
php artisan migrate:fresh --seed
```

### Public Disk
To make these files accessible from the web, you should create a symbolic link from public/storage to storage/app/public.
To create the symbolic link, you may use the storage:link Artisan command:

```php
php artisan storage:link
```
