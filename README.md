# Teton County Cemetery Records

Cemetery records application for the City of Choteau, Montana. Built on Laravel 11 with multi-cemetery subdomain routing to support Choteau Municipal Cemetery and future Teton County cemeteries.

## Requirements

- Docker Desktop (or Docker Engine + Compose v2)
- `/etc/hosts` entries for local development (see below)

## Local Development

### 1. Clone and configure

```bash
cp .env.example .env
# Edit .env — set DB_PASSWORD at minimum
```

### 2. Add hosts entries

Add to your `/etc/hosts` (Windows: `C:\Windows\System32\drivers\etc\hosts`):

```
127.0.0.1  choteau.cemetery.test
127.0.0.1  admin.cemetery.test
```

Add one line per additional cemetery when testing multi-cemetery locally. Alternatively, use `dnsmasq` to resolve `*.cemetery.test` automatically — see [dnsmasq wildcard setup](https://passingcuriosity.com/2013/dnsmasq-dev-osx/).

### 3. Build and start

```bash
docker compose build
docker compose up -d
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
docker compose exec app php artisan db:seed
```

### 4. Verify

Open http://choteau.cemetery.test/ — you should see the placeholder page.

## Services

| Service | Purpose |
|---------|---------|
| `app` | PHP-FPM 8.2 — serves requests and runs Artisan commands |
| `nginx` | Nginx — wildcard subdomain routing for `*.cemetery.test` |
| `db` | MySQL 8.0 — primary datastore |
| `queue` | PHP-FPM — runs `php artisan queue:work` for import jobs |

## Import

```bash
# Dry run (no DB writes)
docker compose exec app php artisan cemetery:import \
  --file=/data/City_of_Choteau_02.xls \
  --cemetery=choteau \
  --dry-run

# Live import
docker compose exec app php artisan cemetery:import \
  --file=/data/City_of_Choteau_02.xls \
  --cemetery=choteau
```

Mount import files in `data/` (excluded from git). The compose file mounts this directory into the `app` container at `/data`.

## Domain structure

| Environment | Pattern |
|-------------|---------|
| Production | `{slug}.cemetery.tetonmt.gov` |
| Local dev | `{slug}.cemetery.test` |

The `APP_DOMAIN` env var controls which base domain is used for subdomain route groups.

## License

AGPL-3.0 — see [AGPL.txt](AGPL.txt) and [LICENSE.txt](LICENSE.txt).
