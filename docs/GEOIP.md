# GeoLite2 country detection

The application stores the optional local MaxMind database at
`storage/geoip/GeoLite2-Country.mmdb`. It never stores raw IP addresses in the
application database. A request country header is accepted only when
`GEOIP_TRUSTED_PROXIES` explicitly contains the connecting reverse-proxy/CDN
CIDR; otherwise the application ignores the header and uses the local database
when available.

Update the database with `php artisan geoip:update`. The command validates the
download as a readable country MMDB and atomically swaps it into place. A
failed download or invalid file leaves the previous active database untouched.
The command accepts a local `--path` for controlled deployments and does not
contain credentials. Configure `MAXMIND_LICENSE_KEY` or a private
`GEOIP_DOWNLOAD_URL` through deployment secrets rather than committing them.

This product includes GeoLite2 data created by MaxMind, available from
https://www.maxmind.com.
