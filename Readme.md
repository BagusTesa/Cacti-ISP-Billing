# 9th Percentile Cacti ISP Billing Plugin #

**Disclaimer**

This Repository is only for archival purpose, refer the [original source](http://forums.cacti.net/viewtopic.php?t=19887) for more information.

**Notice**

These code are left as is from the [original source](http://forums.cacti.net/viewtopic.php?t=19887) and the original owner (and developer) already left these code to their own.

**Installation**

These steps area available in pdf documentation in `/docs` (english) and `/manjar` (indonesian) directories. This guide assumes you are using -nix system especially **Ubuntu Server 14.04**.

* **Installing Cacti**

There are several way to install Cacti, the easiest one is using **LAMPP Applications Stack** to provide HTTP + PHP + MySQL capabilities. To install [**LAMP Applications Stack** in Ubuntu Server 14.04](https://help.ubuntu.com/lts/serverguide/lamp-overview.html) you could use:

```
tasksel install lamp-server
```

Command above will present you with several parameter to configure, fortunately it's pretty user friendly. The next step is to install Cacti, just based on [Cacti Documentation](http://cacti.net/download_cacti.php) you could use this command:

```
apt-get install cacti
```

This command will solve dependencies for you and guide you through configuring Cacti. Several configuration done in the Command-Line Interface, while most of it done in Cacti's Web Interface. Just browse to `http://localhost/cacti` to configure path, password, etc.

* **Installing Plugin**

The original plugin is not tightly coupled with Cacti's Plugin Architecture. So that it actually hooks into Cacti for configuration to access database and it reads .rrd files directly. Installation done by copying entire file to Cacti Site subdirectory, for instance make a directory called `isp_billing`.

```
usr/share/cacti/Site
                  |-cli
                  |-docs
                  |-images
                  ...
                  |-isp_billing //make a new directory to store all of this sources, if you download it in zip, you should extract it
                        ...
                        |-isp_billing.php
                        |-Readme.md
                  ...
                  |-resource
                  |-scripts
```
The next step is making sure that it have the same owner and permission with the rest of Cacti installation, you could use [chmod](http://ss64.com/bash/chmod.html) for this. Also, make sure that `isp_billing.php` is added to **$no_http_header_files** variable in `/site/include/global.php`. That's it, to test whether you install it correctly you could make a 95th percentile graph and run this command:

```
php isp_billing.php -list
```

That command should prints all available 95th percentile graph and it's properties.

Read pdf in either `/docs` or `/manjar` if you need more information.

**Repositories Mirror**

Some people just don't like when i used Bitbucket (or GitHub), so, here we go.

Bitbucket: https://bitbucket.org/BagusTesa/cacti-isp-billing

GitHub: https://github.com/BagusTesa/Cacti-ISP-Billing
